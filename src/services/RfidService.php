<?php
/**
 * RfidService - RFID-Integration fuer Equipment-Tracking
 *
 * Unterstuetzt:
 * - UHF RFID-Tags (EPC Gen2)
 * - RFID-Gateway Anbindung (TCP/HTTP)
 * - Automatische Inventur beim Gate-Durchgang
 * - Tag-Zuordnung zu Assets
 * - Label-Druck mit RFID-Tag-ID
 */
class RfidService
{
    private $db;

    const TAG_STATUS_ACTIVE = 'active';
    const TAG_STATUS_LOST = 'lost';
    const TAG_STATUS_DECOMMISSIONED = 'decommissioned';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * RFID-Tag einem Asset zuordnen
     */
    public function assignTag(int $assetId, string $tagEpc, int $userId): array
    {
        $tagEpc = strtoupper(trim($tagEpc));
        if (!preg_match('/^[0-9A-F]{24,48}$/', $tagEpc)) {
            return ['success' => false, 'error' => 'Ungueltiges EPC-Format (24-48 Hex-Zeichen)'];
        }

        // Duplikatpruefung
        $this->db->where('tag_epc', $tagEpc);
        $this->db->where('status', self::TAG_STATUS_ACTIVE);
        $existing = $this->db->getOne('rfid_tags', ['id', 'asset_id']);
        if ($existing) {
            return ['success' => false, 'error' => 'Tag bereits Asset #' . $existing['asset_id'] . ' zugeordnet'];
        }

        // Altes Tag deaktivieren
        $this->db->where('asset_id', $assetId);
        $this->db->where('status', self::TAG_STATUS_ACTIVE);
        $this->db->update('rfid_tags', ['status' => self::TAG_STATUS_DECOMMISSIONED]);

        $id = $this->db->insert('rfid_tags', [
            'asset_id' => $assetId,
            'tag_epc' => $tagEpc,
            'status' => self::TAG_STATUS_ACTIVE,
            'assigned_by' => $userId,
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        return $id
            ? ['success' => true, 'id' => $id]
            : ['success' => false, 'error' => 'Fehler beim Speichern'];
    }

    /**
     * Asset per RFID-Tag finden
     */
    public function findAssetByTag(string $tagEpc): ?array
    {
        $tagEpc = strtoupper(trim($tagEpc));
        $sql = "SELECT rt.*, a.assets_id, a.assets_tag, at.assetTypes_name
                FROM rfid_tags rt
                JOIN assets a ON rt.asset_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE rt.tag_epc = ? AND rt.status = ?
                AND a.assets_deleted = 0";
        $result = $this->db->rawQuery($sql, [$tagEpc, self::TAG_STATUS_ACTIVE]);
        return $result ? $result[0] : null;
    }

    /**
     * Bulk-Scan verarbeiten (Gateway-Meldung)
     */
    public function processBulkScan(array $tagEpcs, string $gatewayId, int $instanceId): array
    {
        $found = [];
        $unknown = [];

        foreach ($tagEpcs as $epc) {
            $asset = $this->findAssetByTag($epc);
            if ($asset) {
                $found[] = [
                    'tag_epc' => $epc,
                    'asset_id' => $asset['assets_id'],
                    'asset_tag' => $asset['assets_tag'],
                    'asset_name' => $asset['assetTypes_name'],
                ];
            } else {
                $unknown[] = $epc;
            }
        }

        // Scan-Event loggen
        $this->db->insert('rfid_scan_log', [
            'gateway_id' => $gatewayId,
            'instances_id' => $instanceId,
            'tags_scanned' => count($tagEpcs),
            'tags_found' => count($found),
            'tags_unknown' => count($unknown),
            'scan_data' => json_encode(['found' => $found, 'unknown' => $unknown]),
            'scanned_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'total_scanned' => count($tagEpcs),
            'found' => $found,
            'unknown' => $unknown,
        ];
    }

    /**
     * RFID-basierte Inventur: Soll-Ist-Vergleich
     */
    public function inventoryCheck(array $scannedEpcs, int $instanceId): array
    {
        // Alle aktiven Tags der Instanz
        $sql = "SELECT rt.tag_epc, a.assets_id, a.assets_tag, at.assetTypes_name
                FROM rfid_tags rt
                JOIN assets a ON rt.asset_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE rt.status = ? AND a.assets_deleted = 0
                AND at.instances_id = ?";
        $allTags = $this->db->rawQuery($sql, [self::TAG_STATUS_ACTIVE, $instanceId]) ?: [];

        $expectedEpcs = array_column($allTags, 'tag_epc');
        $scannedUpper = array_map('strtoupper', $scannedEpcs);

        $present = [];
        $missing = [];
        $unexpected = [];

        foreach ($allTags as $tag) {
            if (in_array($tag['tag_epc'], $scannedUpper)) {
                $present[] = $tag;
            } else {
                $missing[] = $tag;
            }
        }

        foreach ($scannedUpper as $epc) {
            if (!in_array($epc, $expectedEpcs)) {
                $unexpected[] = $epc;
            }
        }

        return [
            'total_expected' => count($allTags),
            'total_scanned' => count($scannedEpcs),
            'present' => count($present),
            'missing' => $missing,
            'unexpected' => $unexpected,
            'match_rate' => count($allTags) > 0
                ? round(count($present) / count($allTags) * 100, 1)
                : 100,
        ];
    }

    /**
     * Gateway registrieren/aktualisieren
     */
    public function registerGateway(int $instanceId, string $gatewayId, string $name, string $location = ''): array
    {
        $this->db->where('gateway_id', $gatewayId);
        $this->db->where('instances_id', $instanceId);
        $existing = $this->db->getOne('rfid_gateways', ['id']);

        $data = [
            'instances_id' => $instanceId,
            'gateway_id' => $gatewayId,
            'name' => $name,
            'location' => $location,
            'last_seen_at' => date('Y-m-d H:i:s'),
            'active' => 1,
        ];

        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update('rfid_gateways', $data);
            return ['success' => true, 'id' => $existing['id']];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $this->db->insert('rfid_gateways', $data);
        return $id ? ['success' => true, 'id' => $id] : ['success' => false, 'error' => 'Fehler'];
    }

    /**
     * ZPL-Label mit RFID-Tag fuer Zebra-Drucker
     */
    public function generateRfidLabel(int $assetId): ?string
    {
        $sql = "SELECT a.assets_tag, at.assetTypes_name, rt.tag_epc
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                LEFT JOIN rfid_tags rt ON rt.asset_id = a.assets_id AND rt.status = ?
                WHERE a.assets_id = ? AND a.assets_deleted = 0";
        $asset = $this->db->rawQuery($sql, [self::TAG_STATUS_ACTIVE, $assetId]);
        if (!$asset) return null;
        $asset = $asset[0];

        $tag = $asset['assets_tag'] ?? '';
        $name = $asset['assetTypes_name'] ?? '';
        $epc = $asset['tag_epc'] ?? '';

        // ZPL mit RFID-Encoding
        $zpl = "^XA\n";
        $zpl .= "^CF0,30\n";
        $zpl .= "^FO50,30^FD{$name}^FS\n";
        $zpl .= "^CF0,25\n";
        $zpl .= "^FO50,70^FDID: {$tag}^FS\n";
        if ($epc) {
            $zpl .= "^FO50,100^FDRFID: {$epc}^FS\n";
            // RFID-Tag schreiben
            $zpl .= "^RFW,H^FD{$epc}^FS\n";
        }
        $zpl .= "^FO50,140^BQN,2,4^FDMA,{$tag}^FS\n"; // QR-Code
        $zpl .= "^XZ\n";

        return $zpl;
    }
}
