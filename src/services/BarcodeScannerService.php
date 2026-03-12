<?php
/**
 * BarcodeScannerService - Barcode/QR-Code Scanner via Kamera
 *
 * Stellt die serverseitige Logik bereit fuer den browserbasierten
 * Barcode-Scanner (HTML5 MediaDevices API + QuaggaJS/ZXing).
 * Der eigentliche Scan passiert clientseitig per JavaScript.
 */
class BarcodeScannerService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Asset per gescanntem Code finden
     */
    public function lookupByCode(string $code, int $instanceId): array
    {
        $code = trim($code);
        if (empty($code)) {
            return ['found' => false, 'error' => 'Leerer Code'];
        }

        // 1. Exakte Suche nach Asset-Tag
        $sql = "SELECT a.assets_id, a.assets_tag, at.assetTypes_name, at.assetTypes_id,
                       a.assets_lifecycle_status
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE a.assets_tag = ? AND a.assets_deleted = 0
                AND (at.instances_id = ? OR at.instances_id IS NULL)
                LIMIT 1";
        $result = $this->db->rawQuery($sql, [$code, $instanceId]);
        if ($result) {
            return ['found' => true, 'type' => 'asset', 'data' => $result[0]];
        }

        // 2. Suche nach Seriennummer
        $sql = "SELECT a.assets_id, a.assets_tag, a.assets_serialNumber, at.assetTypes_name
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE a.assets_serialNumber = ? AND a.assets_deleted = 0
                AND (at.instances_id = ? OR at.instances_id IS NULL)
                LIMIT 1";
        $result = $this->db->rawQuery($sql, [$code, $instanceId]);
        if ($result) {
            return ['found' => true, 'type' => 'serial', 'data' => $result[0]];
        }

        // 3. RFID-Tag Suche
        $sql = "SELECT rt.tag_epc, a.assets_id, a.assets_tag, at.assetTypes_name
                FROM rfid_tags rt
                JOIN assets a ON rt.asset_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE rt.tag_epc = ? AND rt.status = 'active' AND a.assets_deleted = 0
                LIMIT 1";
        $result = $this->db->rawQuery($sql, [strtoupper($code)]);
        if ($result) {
            return ['found' => true, 'type' => 'rfid', 'data' => $result[0]];
        }

        return ['found' => false, 'error' => 'Kein Asset mit diesem Code gefunden'];
    }

    /**
     * Bulk-Scan verarbeiten (mehrere Codes auf einmal)
     */
    public function bulkLookup(array $codes, int $instanceId): array
    {
        $found = [];
        $notFound = [];

        foreach ($codes as $code) {
            $result = $this->lookupByCode(trim($code), $instanceId);
            if ($result['found']) {
                $found[] = $result['data'];
            } else {
                $notFound[] = $code;
            }
        }

        return [
            'found' => $found,
            'not_found' => $notFound,
            'total_scanned' => count($codes),
            'total_found' => count($found),
        ];
    }

    /**
     * JavaScript fuer den Browser-Scanner generieren
     * Nutzt die QuaggaJS Library fuer Barcode-Scanning
     */
    public static function getScannerScript(): string
    {
        return <<<'JS'
class AdamRMSScanner {
    constructor(containerId, onScanCallback) {
        this.container = document.getElementById(containerId);
        this.callback = onScanCallback;
        this.active = false;
        this.lastCode = '';
        this.lastScanTime = 0;
    }

    async start() {
        if (this.active) return;

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
            });

            const video = document.createElement('video');
            video.srcObject = stream;
            video.setAttribute('playsinline', '');
            video.style.width = '100%';
            video.style.maxWidth = '500px';
            video.style.borderRadius = '8px';

            this.container.innerHTML = '';
            this.container.appendChild(video);
            await video.play();

            this.stream = stream;
            this.video = video;
            this.active = true;

            // Canvas fuer Frame-Capture
            this.canvas = document.createElement('canvas');
            this.ctx = this.canvas.getContext('2d');

            // Scan-Loop mit BarcodeDetector API (Chrome/Edge)
            if ('BarcodeDetector' in window) {
                this.detector = new BarcodeDetector({
                    formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a']
                });
                this.scanLoop();
            } else {
                this.container.insertAdjacentHTML('afterbegin',
                    '<div class="alert alert-warning">Barcode-Erkennung nicht unterstuetzt. Bitte Chrome/Edge verwenden oder Code manuell eingeben.</div>');
            }
        } catch (err) {
            this.container.innerHTML = '<div class="alert alert-danger">Kamera-Zugriff verweigert: ' + err.message + '</div>';
        }
    }

    async scanLoop() {
        if (!this.active) return;

        try {
            const barcodes = await this.detector.detect(this.video);
            if (barcodes.length > 0) {
                const code = barcodes[0].rawValue;
                const now = Date.now();
                // Debounce: gleicher Code innerhalb 2 Sekunden ignorieren
                if (code !== this.lastCode || now - this.lastScanTime > 2000) {
                    this.lastCode = code;
                    this.lastScanTime = now;
                    if (this.callback) this.callback(code, barcodes[0].format);
                }
            }
        } catch (e) { /* ignore detection errors */ }

        requestAnimationFrame(() => this.scanLoop());
    }

    stop() {
        this.active = false;
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
        }
        if (this.container) {
            this.container.innerHTML = '<p class="text-muted">Scanner gestoppt.</p>';
        }
    }
}
JS;
    }
}
