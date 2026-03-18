<?php
/**
 * Sammelrechnung: Mehrere Projekte eines Kunden zu einer Rechnung zusammenfassen.
 *
 * Workflow:
 * 1. Projekte auswaehlen (muessen zum selben Kunden gehoeren)
 * 2. Sammelrechnung generieren (eine PDF mit allen Positionen)
 * 3. Lifecycle-Eintrag fuer jedes Projekt + ein uebergreifender Eintrag
 */
class CollectiveInvoiceService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Sammelrechnung erstellen
     *
     * @param int   $instanceId
     * @param array $projectIds  Array von Projekt-IDs (mindestens 2)
     * @param int   $userId
     * @param array $opts        Optionen (skonto_enabled, discount_pct, etc.)
     * @return array
     */
    public function create(int $instanceId, array $projectIds, int $userId, array $opts = []): array
    {
        if (count($projectIds) < 2) {
            throw new \InvalidArgumentException('Fuer eine Sammelrechnung werden mindestens 2 Projekte benoetigt.');
        }

        // Load dependencies (assume these are auto-loaded via PSR-4 or composer)
        // If explicit requires are needed, uncomment:
        // require_once __DIR__ . '/../repos/ProjectRepo.php';
        // require_once __DIR__ . '/../repos/ClientsRepo.php';
        // require_once __DIR__ . '/../repos/BusinessRepo.php';
        // require_once __DIR__ . '/SequenceService.php';

        // Alle Projekte laden und sicherstellen, dass sie zum selben Kunden gehoeren
        $projects = [];
        $clientId = null;
        foreach ($projectIds as $pid) {
            $project = ProjectRepo::getWithFinance($this->db, $instanceId, (int)$pid);
            if ($clientId === null) {
                $clientId = (int)$project['clients_id'];
            } elseif ((int)$project['clients_id'] !== $clientId) {
                throw new \InvalidArgumentException(
                    'Alle Projekte muessen zum selben Kunden gehoeren. ' .
                    'Projekt ' . $project['projects_name'] . ' gehoert zu einem anderen Kunden.'
                );
            }
            $projects[] = $project;
        }

        $business = BusinessRepo::getSettings($this->db, $instanceId);
        $client = ClientsRepo::getById($this->db, $clientId);

        // KUR-Logik (identisch zu DocumentRenderer)
        $kurEnabled = (bool)($business['instances_kurEnabled'] ?? true);
        if ($kurEnabled && !empty($business['instances_kurTransitionYear'])) {
            if ((int)date('Y') >= (int)$business['instances_kurTransitionYear']) {
                $kurEnabled = false;
            }
        }
        $vatRate = $kurEnabled ? 0 : (float)($business['instances_vatRate'] ?? 19.0);

        // Reverse-Charge pruefen
        $reverseCharge = false;
        if (!$kurEnabled && !empty($client['clients_reverseCharge']) && !empty($client['clients_vatId'])) {
            $reverseCharge = true;
            $vatRate = 0;
        }

        // Positionen aus allen Projekten sammeln
        $allLines = [];
        $projectSummaries = [];
        $grandSubtotal = 0;

        foreach ($projects as $project) {
            $durationDays = self::calcDays(
                new DateTime($project['projects_dateStart']),
                new DateTime($project['projects_dateEnd'])
            );

            $projectLines = [];
            // Assets
            foreach ($project['assets'] as $a) {
                $line = [
                    'kind' => 'asset',
                    'name' => $a['name'],
                    'qty' => (float)($a['qty'] ?? 1),
                    'unit' => $a['unit'] ?? 'Tag',
                    'day_price' => isset($a['price_day_net']) ? (float)$a['price_day_net'] : 0,
                    'days' => $durationDays,
                    'total' => round((float)($a['total_net'] ?? 0), 2),
                    'note' => $a['note'] ?? null,
                    'is_daily' => true,
                    'project_name' => $project['projects_name'],
                ];
                $projectLines[] = $line;
            }
            // Extras
            foreach ($project['extras'] as $e) {
                $projectLines[] = [
                    'kind' => 'extra',
                    'name' => $e['name'],
                    'qty' => (float)($e['qty'] ?? 1),
                    'unit' => $e['unit'] ?? 'Stk',
                    'day_price' => null,
                    'days' => null,
                    'total' => round((float)($e['total_net'] ?? 0), 2),
                    'note' => $e['note'] ?? null,
                    'is_daily' => false,
                    'project_name' => $project['projects_name'],
                ];
            }

            $projectSubtotal = 0;
            foreach ($projectLines as $l) {
                $projectSubtotal += $l['total'];
            }
            $projectSubtotal = round($projectSubtotal, 2);

            $projectSummaries[] = [
                'project_id' => $project['projects_id'],
                'project_name' => $project['projects_name'],
                'date_start' => $project['projects_dateStart'],
                'date_end' => $project['projects_dateEnd'],
                'subtotal' => $projectSubtotal,
                'line_count' => count($projectLines),
            ];

            $allLines = array_merge($allLines, $projectLines);
            $grandSubtotal += $projectSubtotal;
        }
        $grandSubtotal = round($grandSubtotal, 2);

        // Rabatt
        $discountPct = max(0.0, min(100.0, (float)($opts['discount_pct'] ?? 0)));
        $discount = round($grandSubtotal * $discountPct / 100.0, 2);
        $afterDiscount = round($grandSubtotal - $discount, 2);

        // MwSt/KUR
        if ($kurEnabled || $reverseCharge) {
            $vat = 0;
            $gross = $afterDiscount;
        } else {
            $vat = round($afterDiscount * $vatRate / 100.0, 2);
            $gross = round($afterDiscount + $vat, 2);
        }

        $totals = [
            'kur' => $kurEnabled,
            'reverse_charge' => $reverseCharge,
            'subtotal' => $grandSubtotal,
            'discount_pct' => $discountPct,
            'discount' => $discount,
            'net' => $afterDiscount,
            'vat_rate' => $vatRate,
            'vat' => $vat,
            'gross' => $gross,
        ];

        if ($kurEnabled) {
            $totals['kur_notice'] = 'Kein Ausweis von Umsatzsteuer, da Kleinunternehmer gemaess § 19 UStG.';
        }
        if ($reverseCharge) {
            $totals['reverse_charge_notice'] = 'Steuerschuldnerschaft des Leistungsempfaengers (Reverse Charge, Art. 196 MwSt-Richtlinie).';
        }

        // Skonto
        $skontoEnabled = !empty($opts['skonto_enabled']);
        $skontoRate = $skontoEnabled ? (float)($opts['skonto_rate'] ?? $business['instances_skontoRate'] ?? 0) : 0;
        $skontoDays = $skontoEnabled ? (int)($opts['skonto_days'] ?? $business['instances_skontoDays'] ?? 0) : 0;
        $skontoAmount = 0;
        $skontoDate = null;

        if ($skontoEnabled && $skontoRate > 0 && $skontoDays > 0) {
            $skontoAmount = round($gross * $skontoRate / 100, 2);
            $skontoDate = (new DateTime())->modify("+{$skontoDays} days");
            $totals['skonto_rate'] = $skontoRate;
            $totals['skonto_days'] = $skontoDays;
            $totals['skonto_amount'] = $skontoAmount;
            $totals['skonto_gross'] = round($gross - $skontoAmount, 2);
            $totals['skonto_date'] = $skontoDate->format('d.m.Y');
        }

        // Rechnungsnummer generieren
        $docNumber = SequenceService::next($this->db, $instanceId, 'invoice', $userId);
        $docDate = new DateTime();
        $paymentTermDays = (int)($client['clients_paymentTermDays'] ?? $business['instances_paymentTermDays'] ?? 14);
        $dueDate = (clone $docDate)->modify("+{$paymentTermDays} days");

        $docData = [
            'type' => 'invoice',
            'number' => $docNumber,
            'date' => $docDate,
            'due_date' => $dueDate,
            'payment_term_days' => $paymentTermDays,
            'title' => 'Sammelrechnung',
            'number_label' => 'Rechnungsnummer',
            'is_collective' => true,
            'project_summaries' => $projectSummaries,
            'skonto_rate' => $skontoRate,
            'skonto_days' => $skontoDays,
            'skonto_amount' => $skontoAmount,
            'skonto_date' => $skontoDate ? $skontoDate->format('d.m.Y') : null,
        ];

        // Logo laden
        $logoDataUri = null;
        if (!empty($business['instances_logo'])) {
            global $bCMS;
            if (isset($bCMS)) {
                $logoDataUri = $bCMS->s3DataUri($business['instances_logo']);
            }
        }

        // Template rendern
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'),
            ['cache' => false, 'autoescape' => false]
        );
        $twig->addFilter(new \Twig\TwigFilter('numberDe', function ($value, int $decimals = 2) {
            return number_format((float)$value, $decimals, ',', '.');
        }));
        $twig->addFilter(new \Twig\TwigFilter('dateDe', function ($datetime, string $format = 'd.m.Y') {
            if ($datetime instanceof \DateTimeInterface) return $datetime->format($format);
            if (is_string($datetime) && strlen($datetime) > 0) return date($format, strtotime($datetime));
            return '';
        }));

        $templateVars = [
            'business' => $business,
            'client' => $client,
            'doc' => $docData,
            'lines' => $allLines,
            'totals' => $totals,
            'project_summaries' => $projectSummaries,
            'logo' => $logoDataUri ?: null,
            'options' => $opts,
        ];

        $html = $twig->render('collective_invoice_de.twig', $templateVars);

        // PDF erzeugen
        $dompdf = new \Dompdf\Dompdf((new \Dompdf\Options())->set('isRemoteEnabled', true));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        // ZUGFeRD XML
        $zugferdXml = null;
        $zugferdFileId = null;
        // Assume ZugferdService and PdfA3Converter are auto-loaded (PSR-4/composer)
        if (class_exists('ZugferdService')) {
            $zugferdXml = ZugferdService::generateInvoiceXml(
                $business, $client, $docData, $allLines, $totals, $projects[0]
            );
            if ($zugferdXml && class_exists('PdfA3Converter')) {
                $pdf = PdfA3Converter::convert($pdf, $zugferdXml, [
                    'title' => 'Sammelrechnung',
                    'author' => $business['instances_name'] ?? '',
                    'doc_number' => $docNumber,
                    'date' => $docDate->format('Y-m-d'),
                ]);
            }
        }

        // PDF als Datei zum ersten Projekt speichern
        $primaryProjectId = $projectIds[0];
        $fileInfo = S3Files::storeProjectFile($this->db, $instanceId, $primaryProjectId, 20, [
            'name' => 'Sammelrechnung_' . $docNumber . '.pdf',
            'content' => $pdf,
            'extension' => 'pdf',
        ]);

        // ZUGFeRD XML speichern
        if ($zugferdXml) {
            $zugferdFileInfo = S3Files::storeProjectFile($this->db, $instanceId, $primaryProjectId, 20, [
                'name' => 'factur-x_' . $docNumber . '.xml',
                'content' => $zugferdXml,
                'extension' => 'xml',
            ]);
            $zugferdFileId = $zugferdFileInfo['s3files_id'] ?? null;
        }

        // Export protokollieren
        $retentionExpiresAt = date('Y-m-d H:i:s', strtotime('+10 years'));
        $this->db->insert('document_exports', [
            'instances_id' => $instanceId,
            'projects_id' => $primaryProjectId,
            'type' => 'invoice',
            'template_id' => 0,
            'doc_number' => $docNumber,
            'language' => 'de-DE',
            'currency' => 'EUR',
            'totals_json' => json_encode($totals),
            'snapshot_json' => json_encode([
                'lines' => $allLines,
                'doc' => $docData,
                'project_summaries' => $projectSummaries,
            ]),
            's3files_id' => $fileInfo['s3files_id'],
            'generated_by' => $userId,
            'zugferd_xml_s3files_id' => $zugferdFileId,
            'retention_expires_at' => $retentionExpiresAt,
            'archive_status' => 'active',
        ]);

        // Lifecycle-Eintraege fuer jedes Projekt erstellen
        // DocumentLifecycleService should be auto-loaded (PSR-4/composer)
        $lifecycle = new DocumentLifecycleService($this->db);

        $docIds = [];
        foreach ($projectIds as $pid) {
            $summary = null;
            foreach ($projectSummaries as $ps) {
                if ($ps['project_id'] == $pid) { $summary = $ps; break; }
            }
            $docId = $lifecycle->create($instanceId, (int)$pid, 'invoice', [
                'doc_number' => $docNumber,
                's3files_id' => $fileInfo['s3files_id'],
                'net_amount' => $summary ? $summary['subtotal'] : 0,
                'gross_amount' => $summary ? round($summary['subtotal'] * (1 + $vatRate / 100), 2) : 0,
                'due_date' => $dueDate->format('Y-m-d'),
                'notes' => 'Sammelrechnung fuer ' . count($projectIds) . ' Projekte',
                'created_by' => $userId,
            ]);
            $docIds[] = $docId;
        }

        return [
            'doc_number' => $docNumber,
            's3files_id' => $fileInfo['s3files_id'],
            'zugferd_s3files_id' => $zugferdFileId,
            'gross_amount' => $gross,
            'net_amount' => $afterDiscount,
            'project_count' => count($projectIds),
            'project_summaries' => $projectSummaries,
            'doc_ids' => $docIds,
        ];
    }

    /**
     * Projekte fuer Sammelrechnung vorschlagen (selber Kunde, noch nicht abgerechnet)
     */
    public function getEligibleProjects(int $instanceId, int $clientId): array
    {
        $sql = "SELECT p.projects_id, p.projects_name,
                       p.projects_dates_deliver_start, p.projects_dates_deliver_end,
                       p.projects_dates_use_start, p.projects_dates_use_end,
                       (SELECT COUNT(*) FROM document_lifecycle dl
                        WHERE dl.projects_id = p.projects_id
                        AND dl.doc_type = 'invoice'
                        AND dl.status != 'cancelled') AS invoice_count
                FROM projects p
                WHERE p.instances_id = ?
                AND p.clients_id = ?
                AND p.projects_deleted = 0
                HAVING invoice_count = 0
                ORDER BY p.projects_dates_deliver_start DESC";
        return $this->db->rawQuery($sql, [$instanceId, $clientId]) ?: [];
    }

    private static function calcDays(DateTime $start, DateTime $end): int
    {
        $sec = max(0, $end->getTimestamp() - $start->getTimestamp());
        return max(1, (int)ceil($sec / 86400));
    }
}
