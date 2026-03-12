<?php
/**
 * Kunden-Duplikate erkennen und zusammenfuehren
 *
 * Erkennt potentielle Duplikate anhand von:
 * - Aehnlichem Firmennamen (Levenshtein / Soundex)
 * - Gleicher E-Mail-Adresse
 * - Gleicher Telefonnummer
 * - Gleicher Kundennummer
 * - Gleicher USt-IdNr.
 *
 * Zusammenfuehrung: Quell-Kunde wird in Ziel-Kunde zusammengefuehrt,
 * alle Projekte, Rechnungen, Kontakte etc. werden uebernommen.
 */
class ClientMergeService
{
    private $db;

    /** Levenshtein-Schwellwert fuer Namensaehnlichkeit */
    private const LEVENSHTEIN_THRESHOLD = 3;

    /** Minimaler Aehnlichkeitsscore fuer Duplikat-Erkennung */
    private const MIN_SIMILARITY_SCORE = 30;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ═══════════════════════════════════════════════
    //  DUPLIKATERKENNUNG
    // ═══════════════════════════════════════════════

    /**
     * Potentielle Duplikate finden
     *
     * @param int $instanceId Instanz-ID
     * @return array Liste von Duplikat-Paaren mit Score
     */
    public function findDuplicates(int $instanceId): array
    {
        // Alle aktiven Kunden laden
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);
        $this->db->where('clients_merged', 0, 'IS NULL', 'AND', true); // Nicht bereits zusammengefuehrt
        $this->db->orderBy('clients_name', 'ASC');
        $clients = $this->db->get('clients') ?: [];

        // Workaround: clients_merged existiert evtl. noch nicht
        if (empty($clients)) {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('clients_deleted', 0);
            $this->db->orderBy('clients_name', 'ASC');
            $clients = $this->db->get('clients') ?: [];
        }

        $duplicates = [];
        $processed = [];

        for ($i = 0; $i < count($clients); $i++) {
            for ($j = $i + 1; $j < count($clients); $j++) {
                $a = $clients[$i];
                $b = $clients[$j];

                $pairKey = min($a['clients_id'], $b['clients_id']) . '-' . max($a['clients_id'], $b['clients_id']);
                if (isset($processed[$pairKey])) continue;

                $score = $this->calculateSimilarityScore($a, $b);

                if ($score >= self::MIN_SIMILARITY_SCORE) {
                    $reasons = $this->getSimilarityReasons($a, $b);
                    $duplicates[] = [
                        'client_a'  => $a,
                        'client_b'  => $b,
                        'score'     => $score,
                        'reasons'   => $reasons,
                    ];
                    $processed[$pairKey] = true;
                }
            }
        }

        // Nach Score absteigend sortieren
        usort($duplicates, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $duplicates;
    }

    /**
     * Duplikat-Paare mit Aehnlichkeitsscore zurueckgeben
     */
    public function getDuplicatePairs(int $instanceId): array
    {
        return $this->findDuplicates($instanceId);
    }

    /**
     * Aehnlichkeitsscore berechnen (0-100)
     */
    private function calculateSimilarityScore(array $a, array $b): int
    {
        $score = 0;

        // Firmenname: Levenshtein-Distanz
        $nameA = mb_strtolower(trim($a['clients_name'] ?? ''));
        $nameB = mb_strtolower(trim($b['clients_name'] ?? ''));

        if (!empty($nameA) && !empty($nameB)) {
            $lev = levenshtein($nameA, $nameB);
            if ($lev === 0) {
                $score += 50; // Exakte Uebereinstimmung
            } elseif ($lev <= self::LEVENSHTEIN_THRESHOLD) {
                $score += 40; // Sehr aehnlich
            }

            // Soundex-Vergleich
            if (soundex($nameA) === soundex($nameB)) {
                $score += 15;
            }
        }

        // E-Mail
        $emailA = mb_strtolower(trim($a['clients_email'] ?? ''));
        $emailB = mb_strtolower(trim($b['clients_email'] ?? ''));
        if (!empty($emailA) && !empty($emailB) && $emailA === $emailB) {
            $score += 30;
        }

        // Telefon (normalisiert)
        $phoneA = preg_replace('/[^0-9+]/', '', $a['clients_phone'] ?? '');
        $phoneB = preg_replace('/[^0-9+]/', '', $b['clients_phone'] ?? '');
        if (!empty($phoneA) && !empty($phoneB) && $phoneA === $phoneB) {
            $score += 25;
        }

        // Kundennummer
        $custA = trim($a['clients_customerNumber'] ?? '');
        $custB = trim($b['clients_customerNumber'] ?? '');
        if (!empty($custA) && !empty($custB) && $custA === $custB) {
            $score += 40;
        }

        // USt-IdNr.
        $vatA = strtoupper(preg_replace('/[\s.\-]/', '', $a['clients_vatId'] ?? ''));
        $vatB = strtoupper(preg_replace('/[\s.\-]/', '', $b['clients_vatId'] ?? ''));
        if (!empty($vatA) && !empty($vatB) && $vatA === $vatB) {
            $score += 40;
        }

        return min($score, 100);
    }

    /**
     * Aehnlichkeitsgruende als lesbare Liste
     */
    private function getSimilarityReasons(array $a, array $b): array
    {
        $reasons = [];

        $nameA = mb_strtolower(trim($a['clients_name'] ?? ''));
        $nameB = mb_strtolower(trim($b['clients_name'] ?? ''));
        if (!empty($nameA) && !empty($nameB)) {
            $lev = levenshtein($nameA, $nameB);
            if ($lev === 0) {
                $reasons[] = 'Identischer Firmenname';
            } elseif ($lev <= self::LEVENSHTEIN_THRESHOLD) {
                $reasons[] = 'Aehnlicher Firmenname (Levenshtein: ' . $lev . ')';
            }
            if (soundex($nameA) === soundex($nameB) && $lev > 0) {
                $reasons[] = 'Aehnliche Aussprache (Soundex)';
            }
        }

        $emailA = mb_strtolower(trim($a['clients_email'] ?? ''));
        $emailB = mb_strtolower(trim($b['clients_email'] ?? ''));
        if (!empty($emailA) && !empty($emailB) && $emailA === $emailB) {
            $reasons[] = 'Gleiche E-Mail-Adresse';
        }

        $phoneA = preg_replace('/[^0-9+]/', '', $a['clients_phone'] ?? '');
        $phoneB = preg_replace('/[^0-9+]/', '', $b['clients_phone'] ?? '');
        if (!empty($phoneA) && !empty($phoneB) && $phoneA === $phoneB) {
            $reasons[] = 'Gleiche Telefonnummer';
        }

        $custA = trim($a['clients_customerNumber'] ?? '');
        $custB = trim($b['clients_customerNumber'] ?? '');
        if (!empty($custA) && !empty($custB) && $custA === $custB) {
            $reasons[] = 'Gleiche Kundennummer';
        }

        $vatA = strtoupper(preg_replace('/[\s.\-]/', '', $a['clients_vatId'] ?? ''));
        $vatB = strtoupper(preg_replace('/[\s.\-]/', '', $b['clients_vatId'] ?? ''));
        if (!empty($vatA) && !empty($vatB) && $vatA === $vatB) {
            $reasons[] = 'Gleiche USt-IdNr.';
        }

        return $reasons;
    }

    // ═══════════════════════════════════════════════
    //  ZUSAMMENFUEHRUNG
    // ═══════════════════════════════════════════════

    /**
     * Vorschau der Zusammenfuehrung
     *
     * @param int $sourceId Quell-Kunde (wird zusammengefuehrt)
     * @param int $targetId Ziel-Kunde (bleibt bestehen)
     * @return array Vorschau der Aenderungen
     */
    public function previewMerge(int $sourceId, int $targetId): array
    {
        $this->db->where('clients_id', $sourceId);
        $source = $this->db->getOne('clients');

        $this->db->where('clients_id', $targetId);
        $target = $this->db->getOne('clients');

        if (!$source || !$target) {
            return ['error' => 'Kunde nicht gefunden.'];
        }

        // Projekte zaehlen
        $this->db->where('clients_id', $sourceId);
        $this->db->where('projects_deleted', 0);
        $sourceProjects = $this->db->getValue('projects', 'count(*)');

        // Dokumente zaehlen
        $this->db->where('dl.instances_id', $source['instances_id']);
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'INNER');
        $this->db->where('p.clients_id', $sourceId);
        $sourceDocs = $this->db->getValue('document_lifecycle dl', 'count(*)');

        // Kontakte zaehlen
        $this->db->where('clients_id', $sourceId);
        $sourceContacts = $this->db->getValue('client_contacts', 'count(*)') ?: 0;

        // Kommunikation zaehlen
        $this->db->where('clients_id', $sourceId);
        $sourceComms = $this->db->getValue('client_communications', 'count(*)') ?: 0;

        // Felder, die beim Ziel leer sind und vom Quell uebernommen werden
        $fillableFields = [];
        $fieldLabels = [
            'clients_address'        => 'Adresse',
            'clients_email'          => 'E-Mail',
            'clients_phone'          => 'Telefon',
            'clients_website'        => 'Website',
            'clients_vatId'          => 'USt-IdNr.',
            'clients_customerNumber' => 'Kundennummer',
            'clients_notes'          => 'Notizen',
            'clients_deliveryAddress'=> 'Lieferadresse',
            'clients_deliveryContact'=> 'Lieferkontakt',
            'clients_deliveryPhone'  => 'Liefertelefon',
            'clients_deliveryNotes'  => 'Lieferhinweise',
        ];

        foreach ($fieldLabels as $field => $label) {
            if (empty($target[$field]) && !empty($source[$field])) {
                $fillableFields[] = [
                    'field' => $field,
                    'label' => $label,
                    'value' => $source[$field],
                ];
            }
        }

        return [
            'source'          => $source,
            'target'          => $target,
            'projects_count'  => (int)$sourceProjects,
            'documents_count' => (int)$sourceDocs,
            'contacts_count'  => (int)$sourceContacts,
            'communications_count' => (int)$sourceComms,
            'fillable_fields' => $fillableFields,
        ];
    }

    /**
     * Kunden zusammenfuehren
     *
     * @param int $sourceId Quell-Kunde (wird als zusammengefuehrt markiert)
     * @param int $targetId Ziel-Kunde (erhaelt alle Daten)
     * @param int $userId   Benutzer, der die Zusammenfuehrung ausfuehrt
     * @return array Ergebnis
     */
    public function mergeClients(int $sourceId, int $targetId, int $userId): array
    {
        if ($sourceId === $targetId) {
            return ['success' => false, 'error' => 'Quell- und Ziel-Kunde duerfen nicht identisch sein.'];
        }

        $this->db->where('clients_id', $sourceId);
        $source = $this->db->getOne('clients');

        $this->db->where('clients_id', $targetId);
        $target = $this->db->getOne('clients');

        if (!$source || !$target) {
            return ['success' => false, 'error' => 'Kunde nicht gefunden.'];
        }

        if ($source['instances_id'] !== $target['instances_id']) {
            return ['success' => false, 'error' => 'Kunden gehoeren zu unterschiedlichen Instanzen.'];
        }

        $details = [
            'source_name' => $source['clients_name'],
            'target_name' => $target['clients_name'],
            'actions'     => [],
        ];

        // 1. Projekte umziehen
        $this->db->where('clients_id', $sourceId);
        $movedProjects = $this->db->update('projects', ['clients_id' => $targetId]);
        $details['actions'][] = 'Projekte verschoben: ' . ($movedProjects ? 'ja' : 'keine');

        // 2. Kontakte umziehen
        $this->db->where('clients_id', $sourceId);
        $movedContacts = $this->db->update('client_contacts', ['clients_id' => $targetId]);
        $details['actions'][] = 'Kontakte verschoben: ' . ($movedContacts ? 'ja' : 'keine');

        // 3. Kommunikation umziehen
        $this->db->where('clients_id', $sourceId);
        $movedComms = $this->db->update('client_communications', ['clients_id' => $targetId]);
        $details['actions'][] = 'Kommunikation verschoben: ' . ($movedComms ? 'ja' : 'keine');

        // 4. Leere Felder beim Ziel aus Quelle befuellen
        $fillableFields = [
            'clients_address', 'clients_email', 'clients_phone', 'clients_website',
            'clients_vatId', 'clients_customerNumber', 'clients_notes',
            'clients_deliveryAddress', 'clients_deliveryContact',
            'clients_deliveryPhone', 'clients_deliveryNotes',
        ];

        $fieldsToUpdate = [];
        foreach ($fillableFields as $field) {
            if (empty($target[$field]) && !empty($source[$field])) {
                $fieldsToUpdate[$field] = $source[$field];
                $details['actions'][] = 'Feld uebernommen: ' . $field;
            }
        }

        if (!empty($fieldsToUpdate)) {
            $this->db->where('clients_id', $targetId);
            $this->db->update('clients', $fieldsToUpdate);
        }

        // 5. Quell-Kunde als zusammengefuehrt markieren (Soft-Delete)
        $this->db->where('clients_id', $sourceId);
        $this->db->update('clients', [
            'clients_deleted'  => 1,
            'clients_notes'    => ($source['clients_notes'] ? $source['clients_notes'] . "\n" : '')
                                 . '[Zusammengefuehrt mit ' . $target['clients_name'] . ' (ID: ' . $targetId . ') am ' . date('d.m.Y H:i') . ']',
        ]);
        $details['actions'][] = 'Quell-Kunde als geloescht markiert';

        // 6. Merge-Protokoll
        $this->db->insert('client_merge_log', [
            'instances_id'     => $source['instances_id'],
            'source_client_id' => $sourceId,
            'target_client_id' => $targetId,
            'merged_by'        => $userId,
            'merged_at'        => date('Y-m-d H:i:s'),
            'merge_details'    => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'success' => true,
            'details' => $details,
        ];
    }

    // ═══════════════════════════════════════════════
    //  MERGE-HISTORIE
    // ═══════════════════════════════════════════════

    /**
     * Merge-Historie fuer eine Instanz laden
     */
    public function getMergeHistory(int $instanceId): array
    {
        $this->db->where('ml.instances_id', $instanceId);
        $this->db->join('clients cs', 'ml.source_client_id=cs.clients_id', 'LEFT');
        $this->db->join('clients ct', 'ml.target_client_id=ct.clients_id', 'LEFT');
        $this->db->orderBy('ml.merged_at', 'DESC');
        return $this->db->get('client_merge_log ml', null, [
            'ml.*',
            'cs.clients_name AS source_name',
            'ct.clients_name AS target_name',
        ]) ?: [];
    }
}
