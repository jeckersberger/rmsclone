<?php
/**
 * VirusScanService - ClamAV-Integration fuer Upload-Scanning
 *
 * Prueft hochgeladene Dateien mit ClamAV (clamdscan oder clamscan).
 * Wenn ClamAV nicht verfuegbar ist, wird eine Warnung geloggt und
 * der Upload trotzdem zugelassen (fail-open mit Logging).
 *
 * Usage:
 *   $scanner = new VirusScanService();
 *   $result = $scanner->scan('/tmp/uploaded_file.pdf');
 *   if (!$result['clean']) {
 *       // Datei entfernen, Upload ablehnen
 *   }
 */
class VirusScanService
{
    private bool $available;
    private string $scannerPath;

    public function __construct()
    {
        // Bevorzuge clamdscan (Daemon-Modus, schneller)
        $this->scannerPath = $this->findScanner();
        $this->available = !empty($this->scannerPath);
    }

    /**
     * Scannt eine Datei auf Viren.
     *
     * @param string $filePath Pfad zur Datei
     * @return array ['clean' => bool, 'threat' => string|null, 'scanner_available' => bool]
     */
    public function scan(string $filePath): array
    {
        if (!$this->available) {
            error_log('[VirusScan] ClamAV nicht verfuegbar — Datei wird ohne Scan akzeptiert: ' . basename($filePath));
            return ['clean' => true, 'threat' => null, 'scanner_available' => false];
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['clean' => false, 'threat' => 'file_not_readable', 'scanner_available' => true];
        }

        $output = [];
        $returnCode = 0;

        // --no-summary: Kein Summary am Ende
        // --infected: Nur infizierte Dateien anzeigen
        $cmd = sprintf(
            '%s --no-summary --infected %s 2>&1',
            escapeshellcmd($this->scannerPath),
            escapeshellarg($filePath)
        );

        exec($cmd, $output, $returnCode);

        // ClamAV Return Codes: 0 = clean, 1 = infected, 2 = error
        if ($returnCode === 0) {
            return ['clean' => true, 'threat' => null, 'scanner_available' => true];
        }

        if ($returnCode === 1) {
            // Infiziert — Bedrohungsname extrahieren
            $threat = 'unknown';
            foreach ($output as $line) {
                if (preg_match('/:\s*(.+)\s+FOUND$/', $line, $matches)) {
                    $threat = trim($matches[1]);
                    break;
                }
            }
            error_log('[VirusScan] Bedrohung erkannt in ' . basename($filePath) . ': ' . $threat);
            return ['clean' => false, 'threat' => $threat, 'scanner_available' => true];
        }

        // returnCode 2 = Fehler
        error_log('[VirusScan] Scanner-Fehler: ' . implode(' ', $output));
        return ['clean' => true, 'threat' => null, 'scanner_available' => true];
    }

    /**
     * Prueft ob ClamAV verfuegbar ist.
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Findet den ClamAV-Scanner-Pfad.
     */
    private function findScanner(): string
    {
        // Bevorzuge clamdscan (verbindet sich mit clamd Daemon — viel schneller)
        $scanners = ['clamdscan', 'clamscan'];
        foreach ($scanners as $scanner) {
            $path = trim(shell_exec('which ' . escapeshellarg($scanner) . ' 2>/dev/null') ?? '');
            if (!empty($path) && is_executable($path)) {
                return $path;
            }
        }
        return '';
    }
}
