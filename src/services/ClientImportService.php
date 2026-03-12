<?php
/**
 * Kunden-Import aus CSV/Excel
 *
 * Features:
 * - CSV-Parsing mit konfigurierbarem Delimiter
 * - Spalten-Mapping (CSV-Spalten auf Kunden-Felder)
 * - Duplikaterkennung (Name+E-Mail oder Kundennummer)
 * - Import-Protokoll
 */
class ClientImportService
{
    private $db;

    /**
     * Erwartete Felder fuer den Import mit DB-Spaltennamen
     */
    public const FIELD_MAP = [
        'Firma'        => 'clients_name',
        'Anrede'       => 'clients_salutation',
        'Vorname'      => 'clients_firstName',
        'Nachname'     => 'clients_lastName',
        'Straße'       => 'clients_street',
        'PLZ'          => 'clients_zip',
        'Ort'          => 'clients_city',
        'Land'         => 'clients_country',
        'E-Mail'       => 'clients_email',
        'Telefon'      => 'clients_phone',
        'USt-IdNr'     => 'clients_vatId',
        'Kundennummer' => 'clients_customerNumber',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ═══════════════════════════════════════════════
    //  CSV/EXCEL PARSING
    // ═══════════════════════════════════════════════

    /**
     * CSV-Datei parsen
     *
     * @param string $fileContent Dateiinhalt
     * @param string $delimiter   Trennzeichen (Standard: ;)
     * @return array ['headers' => [...], 'rows' => [[...], ...]]
     */
    public function parseCsv(string $fileContent, string $delimiter = ';'): array
    {
        // Encoding-Erkennung und Konvertierung
        $detected = mb_detect_encoding($fileContent, ['UTF-8', 'ISO-8859-1', 'ISO-8859-15', 'Windows-1252'], true);
        if ($detected && $detected !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $detected);
        }

        // BOM entfernen
        $fileContent = preg_replace('/^\xEF\xBB\xBF/', '', $fileContent);

        $lines = [];
        $rows = explode("\n", str_replace("\r\n", "\n", $fileContent));
        foreach ($rows as $row) {
            $row = trim($row);
            if ($row === '') continue;
            $lines[] = str_getcsv($row, $delimiter);
        }

        if (empty($lines)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_shift($lines);
        // Trim headers
        $headers = array_map('trim', $headers);

        return [
            'headers' => $headers,
            'rows'    => $lines,
        ];
    }

    /**
     * Excel-Datei parsen (CSV mit anderem Delimiter-Handling)
     *
     * @param string $fileContent Dateiinhalt
     * @return array ['headers' => [...], 'rows' => [[...], ...]]
     */
    public function parseExcel(string $fileContent): array
    {
        // Versuche zuerst Tab-getrennt (typisches Excel-Export-Format)
        $tabResult = $this->parseCsv($fileContent, "\t");
        if (!empty($tabResult['headers']) && count($tabResult['headers']) > 1) {
            return $tabResult;
        }

        // Fallback: Semikolon
        $semiResult = $this->parseCsv($fileContent, ';');
        if (!empty($semiResult['headers']) && count($semiResult['headers']) > 1) {
            return $semiResult;
        }

        // Fallback: Komma
        return $this->parseCsv($fileContent, ',');
    }

    /**
     * Einzelne Zeile validieren
     *
     * @param array $row     Datenzeile
     * @param array $mapping Spalten-Mapping [csv_column_index => db_field_name]
     * @return array ['valid' => bool, 'errors' => [...]]
     */
    public function validateRow(array $row, array $mapping): array
    {
        $errors = [];
        $data = $this->applyMapping($row, $mapping);

        // Firma oder Nachname muss vorhanden sein
        $hasName = !empty($data['clients_name']);
        $hasLastName = !empty($data['clients_lastName']);
        if (!$hasName && !$hasLastName) {
            $errors[] = 'Firma oder Nachname ist erforderlich';
        }

        // E-Mail-Format pruefen (wenn vorhanden)
        if (!empty($data['clients_email']) && !filter_var($data['clients_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungueltige E-Mail-Adresse: ' . $data['clients_email'];
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Kunden importieren
     *
     * @param int   $instanceId
     * @param array $rows       Datenzeilen
     * @param array $mapping    Spalten-Mapping [csv_column_index => db_field_name]
     * @param int   $userId
     * @param bool  $skipDuplicates Duplikate ueberspringen
     * @return array Import-Bericht
     */
    public function importClients(int $instanceId, array $rows, array $mapping, int $userId, bool $skipDuplicates = true): array
    {
        $imported = 0;
        $skipped = 0;
        $errorList = [];
        $total = count($rows);

        foreach ($rows as $index => $row) {
            $lineNum = $index + 2; // +2 weil Header = Zeile 1, Index 0 = Zeile 2

            // Validierung
            $validation = $this->validateRow($row, $mapping);
            if (!$validation['valid']) {
                $errorList[] = "Zeile {$lineNum}: " . implode(', ', $validation['errors']);
                continue;
            }

            $data = $this->applyMapping($row, $mapping);

            // Kundenname zusammenbauen falls kein Firmenname
            if (empty($data['clients_name'])) {
                $parts = array_filter([
                    $data['clients_salutation'] ?? '',
                    $data['clients_firstName'] ?? '',
                    $data['clients_lastName'] ?? '',
                ]);
                $data['clients_name'] = implode(' ', $parts);
            }

            // Adresse zusammenbauen
            $addressParts = array_filter([
                $data['clients_street'] ?? '',
                trim(($data['clients_zip'] ?? '') . ' ' . ($data['clients_city'] ?? '')),
                $data['clients_country'] ?? '',
            ]);
            if (!empty($addressParts)) {
                $data['clients_address'] = implode("\n", $addressParts);
            }

            // Duplikaterkennung
            if ($skipDuplicates && $this->isDuplicate($instanceId, $data)) {
                $skipped++;
                continue;
            }

            // Kundennummer generieren falls nicht vorhanden
            if (empty($data['clients_customerNumber'])) {
                $data['clients_customerNumber'] = $this->generateCustomerNumber($instanceId);
            }

            // Kunden anlegen
            $insertData = [
                'instances_id'           => $instanceId,
                'clients_name'           => $data['clients_name'],
                'clients_email'          => $data['clients_email'] ?? null,
                'clients_phone'          => $data['clients_phone'] ?? null,
                'clients_address'        => $data['clients_address'] ?? null,
                'clients_vatId'          => $data['clients_vatId'] ?? null,
                'clients_customerNumber' => $data['clients_customerNumber'],
                'clients_deleted'        => 0,
            ];

            $result = $this->db->insert('clients', $insertData);
            if ($result) {
                $imported++;
            } else {
                $errorList[] = "Zeile {$lineNum}: Datenbankfehler beim Anlegen";
            }
        }

        // Import-Protokoll speichern
        $this->db->insert('client_import_log', [
            'instances_id'     => $instanceId,
            'filename'         => '', // wird vom API-Endpoint gesetzt
            'records_total'    => $total,
            'records_imported' => $imported,
            'records_skipped'  => $skipped,
            'errors'           => !empty($errorList) ? json_encode($errorList, JSON_UNESCAPED_UNICODE) : null,
            'imported_by'      => $userId,
        ]);
        $logId = $this->db->getInsertId();

        return [
            'log_id'   => $logId,
            'total'    => $total,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errorList,
        ];
    }

    /**
     * Import-Protokoll abrufen
     *
     * @param int $instanceId
     * @return array
     */
    public function getImportLog(int $instanceId): array
    {
        $this->db->where('cil.instances_id', $instanceId);
        $this->db->join('users u', 'cil.imported_by=u.users_userid', 'LEFT');
        $this->db->orderBy('cil.imported_at', 'DESC');
        return $this->db->get('client_import_log cil', 50, [
            'cil.*',
            'u.users_name1',
            'u.users_name2',
        ]) ?: [];
    }

    // ═══════════════════════════════════════════════
    //  HILFSFUNKTIONEN
    // ═══════════════════════════════════════════════

    /**
     * Mapping auf eine Zeile anwenden
     *
     * @param array $row     Datenzeile
     * @param array $mapping [csv_column_index => db_field_name]
     * @return array Gemappte Daten
     */
    private function applyMapping(array $row, array $mapping): array
    {
        $data = [];
        foreach ($mapping as $csvIndex => $dbField) {
            if ($dbField === '' || $dbField === null) continue;
            $value = isset($row[$csvIndex]) ? trim($row[$csvIndex]) : '';
            if ($value !== '') {
                $data[$dbField] = $value;
            }
        }
        return $data;
    }

    /**
     * Duplikaterkennung: Name+E-Mail oder Kundennummer
     */
    private function isDuplicate(int $instanceId, array $data): bool
    {
        // Pruefung via Kundennummer
        if (!empty($data['clients_customerNumber'])) {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('clients_customerNumber', $data['clients_customerNumber']);
            $this->db->where('clients_deleted', 0);
            if ($this->db->getOne('clients')) {
                return true;
            }
        }

        // Pruefung via Name + E-Mail
        if (!empty($data['clients_name']) && !empty($data['clients_email'])) {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('clients_name', $data['clients_name']);
            $this->db->where('clients_email', $data['clients_email']);
            $this->db->where('clients_deleted', 0);
            if ($this->db->getOne('clients')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Naechste Kundennummer generieren
     */
    private function generateCustomerNumber(int $instanceId): string
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_customerNumber IS NOT NULL');
        $this->db->orderBy('clients_customerNumber', 'DESC');
        $lastClient = $this->db->getOne('clients', ['clients_customerNumber']);

        $nextNumber = 1;
        if ($lastClient && $lastClient['clients_customerNumber']) {
            $match = [];
            if (preg_match('/(\d+)$/', $lastClient['clients_customerNumber'], $match)) {
                $nextNumber = intval($match[1]) + 1;
            }
        }

        return 'KD-' . sprintf('%04d', $nextNumber);
    }

    /**
     * Import-Log-Dateinamen aktualisieren
     */
    public function updateLogFilename(int $logId, string $filename): void
    {
        $this->db->where('id', $logId);
        $this->db->update('client_import_log', ['filename' => $filename]);
    }
}
