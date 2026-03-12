<?php
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment as TwigEnv;
use Twig\Loader\ArrayLoader;

require_once __DIR__ . '/GiroCodeService.php';
require_once __DIR__ . '/QrCodeGenerator.php';

class DocumentRenderer {
  /**
   * Renders a GoBD-compliant German document (invoice/quote/delivery_note) as PDF.
   *
   * GoBD required fields on invoices (§ 14 UStG):
   *  - Vollstaendiger Name und Anschrift des Rechnungsstellers
   *  - Vollstaendiger Name und Anschrift des Rechnungsempfaengers
   *  - Steuernummer oder USt-IdNr. des Rechnungsstellers
   *  - Rechnungsdatum (Ausstellungsdatum)
   *  - Fortlaufende Rechnungsnummer
   *  - Menge und Art der gelieferten Gegenstaende / Umfang der Leistung
   *  - Zeitpunkt der Lieferung / Leistungszeitraum
   *  - Entgelt (Nettobetrag)
   *  - Steuersatz + Steuerbetrag (oder Hinweis auf Steuerbefreiung bei KUR)
   *  - Im Voraus vereinbarte Entgeltminderungen (Rabatte, Skonto)
   */
  public static function renderAndStore($db, int $instanceId, int $projectId, string $type, string $templateKey, array $opts, int $userId) {
    // 0) GoBD-Unveraenderbarkeit: Pruefen ob bereits eine Rechnung fuer dieses Projekt existiert
    if ($type === 'invoice' && empty($opts['force_regenerate'])) {
        $db->where('instances_id', $instanceId);
        $db->where('projects_id', $projectId);
        $db->where('type', 'invoice');
        $existing = $db->getOne('document_exports', ['doc_number', 'generated_at']);
        if ($existing) {
            throw new \RuntimeException(
                'GoBD-Sperre: Fuer dieses Projekt existiert bereits Rechnung ' . $existing['doc_number']
                . ' vom ' . date('d.m.Y', strtotime($existing['generated_at']))
                . '. Rechnungen duerfen nach Erstellung nicht veraendert werden. '
                . 'Erstellen Sie stattdessen eine Stornorechnung (Gutschrift) und dann eine neue Rechnung.'
            );
        }
    }

    // 1) Stammdaten
    $business = BusinessRepo::getSettings($db, $instanceId);
    $project  = ProjectRepo::getWithFinance($db, $instanceId, $projectId);
    $client   = ClientsRepo::getById($db, $project['clients_id']);

    // 2) Miettage / Leistungszeitraum
    $durationDays = isset($opts['duration_days']) && $opts['duration_days']>0
      ? (int)$opts['duration_days']
      : self::calcDays(new DateTime($project['projects_dateStart']), new DateTime($project['projects_dateEnd']));

    // 3) Zeilen (Sets, Tagespreis, Istpreis) - gruppiert nach Kategorie
    $lineData = self::buildLines($project, $durationDays, $opts);
    $flatLines  = $lineData['_flat'];
    $categories = $lineData['_grouped'];

    // 4) KUR aus Business-Settings (mit automatischer Uebergangslogik)
    $kurEnabled = (bool)($business['instances_kurEnabled'] ?? true);
    // KUR-Uebergang: Wenn Uebergangsjahr erreicht, automatisch auf Regelbesteuerung wechseln
    if ($kurEnabled && !empty($business['instances_kurTransitionYear'])) {
      if ((int)date('Y') >= (int)$business['instances_kurTransitionYear']) {
        $kurEnabled = false;
        // KUR in DB deaktivieren (einmaliger automatischer Wechsel)
        $db->where('instances_id', $instanceId);
        $db->update('instances', ['instances_kurEnabled' => 0]);
      }
    }
    $vatRate = $kurEnabled ? 0 : (float)($business['instances_vatRate'] ?? 19.0);

    // 4b) Reverse-Charge-Verfahren fuer EU-Ausland (Art. 196 MwSt-Richtlinie)
    $reverseCharge = false;
    $reverseChargeNotice = null;
    if (!$kurEnabled
        && !empty($client['clients_reverseCharge'])
        && !empty($client['clients_vatId'])
    ) {
        $reverseCharge = true;
        $vatRate = 0;
        $reverseChargeNotice = 'Steuerschuldnerschaft des Leistungsempfaengers (Reverse Charge, Art. 196 MwSt-Richtlinie). '
            . 'Die Umsatzsteuer ist vom Leistungsempfaenger zu entrichten.';
    }

    // 5) Summen + Rabatt + KUR/MwSt
    $discountPct = max(0.0, min(100.0, (float)($opts['discount_pct'] ?? 0)));
    $totals = self::calcTotals($flatLines, [
      'kur' => $kurEnabled,
      'vat_rate' => $vatRate,
      'discount_pct' => $discountPct,
    ]);

    // 5b) Reverse-Charge-Hinweis in Totals einfuegen
    if ($reverseCharge) {
        $totals['reverse_charge'] = true;
        $totals['reverse_charge_notice'] = $reverseChargeNotice;
        $totals['client_vat_id'] = $client['clients_vatId'];
    } else {
        $totals['reverse_charge'] = false;
    }

    // 6) Nummer ziehen
    $docNumber = SequenceService::next($db, $instanceId, $type);

    // 7) Faelligkeitsdatum und Skonto berechnen
    $paymentTermDays = (int)($client['clients_paymentTermDays'] ?? $business['instances_paymentTermDays'] ?? 14);
    $docDate = new DateTime();
    $dueDate = (clone $docDate)->modify("+{$paymentTermDays} days");

    // Skonto nur wenn explizit fuer dieses Dokument aktiviert (nicht pauschal)
    $skontoEnabled = !empty($opts['skonto_enabled']);
    $skontoRate = $skontoEnabled ? (float)($opts['skonto_rate'] ?? $client['clients_skontoRate'] ?? $business['instances_skontoRate'] ?? 0) : 0;
    $skontoDays = $skontoEnabled ? (int)($opts['skonto_days'] ?? $client['clients_skontoDays'] ?? $business['instances_skontoDays'] ?? 0) : 0;

    $skontoAmount = 0;
    $skontoDate = null;
    if ($skontoEnabled && $skontoRate > 0 && $skontoDays > 0 && $type === 'invoice') {
      $skontoAmount = round($totals['gross'] * $skontoRate / 100, 2);
      $skontoDate = (clone $docDate)->modify("+{$skontoDays} days");
      $totals['skonto_rate'] = $skontoRate;
      $totals['skonto_days'] = $skontoDays;
      $totals['skonto_amount'] = $skontoAmount;
      $totals['skonto_gross'] = round($totals['gross'] - $skontoAmount, 2);
      $totals['skonto_date'] = $skontoDate->format('d.m.Y');
    }

    // 8) Leistungszeitraum
    $servicePeriodStart = new DateTime($project['projects_dateStart'] ?? $project['projects_dates_deliver_start'] ?? 'now');
    $servicePeriodEnd   = new DateTime($project['projects_dateEnd'] ?? $project['projects_dates_deliver_end'] ?? 'now');

    // 9) Document data for template
    $typeLabels = [
      'invoice'       => ['title' => 'Rechnung',     'number_label' => 'Rechnungsnummer'],
      'quote'         => ['title' => 'Angebot',       'number_label' => 'Angebotsnummer'],
      'delivery_note' => ['title' => 'Lieferschein',  'number_label' => 'Lieferscheinnummer'],
    ];
    $docData = [
      'type'          => $type,
      'number'        => $docNumber,
      'date'          => $docDate,
      'due_date'      => $dueDate,
      'payment_term_days' => $paymentTermDays,
      'duration_days' => $durationDays,
      'discount_pct'  => $discountPct,
      'service_period_start' => $servicePeriodStart,
      'service_period_end'   => $servicePeriodEnd,
      'title'         => $typeLabels[$type]['title'] ?? $type,
      'number_label'  => $typeLabels[$type]['number_label'] ?? 'Dokumentnummer',
      'skonto_rate'   => $skontoRate,
      'skonto_days'   => $skontoDays,
      'skonto_amount' => $skontoAmount,
      'skonto_date'   => $skontoDate ? $skontoDate->format('d.m.Y') : null,
      'valid_until'   => null,
    ];

    // For quotes: add valid_until from opts or default +30 days
    if ($type === 'quote') {
      $validUntilStr = $opts['valid_until'] ?? null;
      if ($validUntilStr) {
        $docData['valid_until'] = new DateTime($validUntilStr);
      } else {
        $db->where('instances_id', $instanceId);
        $instRow = $db->getOne('instances', ['valid_until_default_days']);
        $defaultDays = (int)($instRow['valid_until_default_days'] ?? 30) ?: 30;
        $docData['valid_until'] = (clone $docDate)->modify("+{$defaultDays} days");
      }
    }

    // 10) Try custom template first, fall back to built-in
    $db->where('instances_id', $instanceId);
    $db->where('type', $type);
    $db->where('key', $templateKey);
    $tpl = $db->getOne('document_templates');

    if ($tpl) {
      $twig = new TwigEnv(new ArrayLoader(['tpl' => $tpl['twig_html']]), ['cache'=>false,'autoescape'=>false]);
    } else {
      $twig = new TwigEnv(new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'), ['cache'=>false,'autoescape'=>false]);
    }

    // Add German number/date filters
    $twig->addFilter(new \Twig\TwigFilter('numberDe', function ($value, int $decimals = 2) {
      return number_format((float)$value, $decimals, ',', '.');
    }));
    $twig->addFilter(new \Twig\TwigFilter('dateDe', function ($datetime, string $format = 'd.m.Y') {
      if ($datetime instanceof \DateTimeInterface) return $datetime->format($format);
      if (is_string($datetime) && strlen($datetime) > 0) return date($format, strtotime($datetime));
      return '';
    }));

    // Logo als Data-URI laden (falls vorhanden)
    $logoDataUri = null;
    if (!empty($business['instances_logo'])) {
      global $bCMS;
      if (isset($bCMS)) {
        $logoDataUri = $bCMS->s3DataUri($business['instances_logo']);
      }
    }

    // QR-Codes erzeugen
    $giroCodeDataUri = null;
    $deliveryNoteQrDataUri = null;

    if ($type === 'invoice') {
      // GiroCode / EPC-QR fuer Rechnungen
      $giroCodeDataUri = GiroCodeService::generateFromDocument($business, $docData, $totals);
    }

    if ($type === 'delivery_note') {
      // QR-Code fuer Lieferschein -> Packauftrag-Link
      $deliveryNoteQrDataUri = self::generateDeliveryNoteQr($db, $instanceId, $projectId, $docNumber);
    }

    $templateVars = [
      'business'   => $business,
      'client'     => $client,
      'project'    => $project,
      'doc'        => $docData,
      'lines'      => $flatLines,
      'categories' => $categories,
      'totals'     => $totals,
      'options'    => $opts,
      'logo'       => $logoDataUri ?: null,
      'giro_code'  => $giroCodeDataUri,
      'delivery_note_qr' => $deliveryNoteQrDataUri,
    ];

    $html = $tpl
      ? $twig->render('tpl', $templateVars)
      : $twig->render('document_de.twig', $templateVars);

    // 11) PDF erzeugen
    $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', true));
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    // 11b) ZUGFeRD XML fuer Rechnungen generieren
    $zugferdXml = null;
    $xrechnungXml = null;
    if ($type === 'invoice') {
      require_once __DIR__ . '/ZugferdService.php';
      $zugferdXml = ZugferdService::generateInvoiceXml(
        $business, $client, $docData, $flatLines, $totals, $project
      );

      // XRechnung (UBL 2.1) zusaetzlich generieren wenn Leitweg-ID vorhanden
      if (!empty($client['clients_leitwegId'])) {
        require_once __DIR__ . '/XRechnungService.php';
        $xrechnungXml = XRechnungService::generateInvoiceXml(
          $business, $client, $docData, $flatLines, $totals, $project
        );
      }
    }

    // 11c) PDF/A-3b Konvertierung mit eingebettetem ZUGFeRD XML
    if ($zugferdXml) {
      require_once __DIR__ . '/PdfA3Converter.php';
      $pdf = PdfA3Converter::convert($pdf, $zugferdXml, [
        'title'      => $docData['title'] ?? 'Rechnung',
        'author'     => $business['instances_name'] ?? '',
        'doc_number' => $docNumber,
        'date'       => $docDate->format('Y-m-d'),
      ]);
    }

    // 12) Speichern als Projektdokument
    $fileType = ['invoice'=>20,'quote'=>21,'delivery_note'=>22][$type];
    $fileInfo = S3Files::storeProjectFile($db, $instanceId, $projectId, $fileType, [
      'name' => self::fileName($type, $docNumber), 'content'=>$pdf, 'extension'=>'pdf'
    ]);

    // 12b) ZUGFeRD XML auch als separate Datei speichern (fuer Buchhaltungssoftware)
    $zugferdFileId = null;
    if ($zugferdXml) {
      $zugferdFileInfo = S3Files::storeProjectFile($db, $instanceId, $projectId, $fileType, [
        'name' => 'factur-x_' . $docNumber . '.xml', 'content' => $zugferdXml, 'extension' => 'xml'
      ]);
      $zugferdFileId = $zugferdFileInfo['s3files_id'] ?? null;
    }

    // 12c) XRechnung XML separat speichern (fuer oeffentliche Auftraggeber)
    $xrechnungFileId = null;
    if ($xrechnungXml) {
      $xrechnungFileInfo = S3Files::storeProjectFile($db, $instanceId, $projectId, $fileType, [
        'name' => 'xrechnung_' . $docNumber . '.xml', 'content' => $xrechnungXml, 'extension' => 'xml'
      ]);
      $xrechnungFileId = $xrechnungFileInfo['s3files_id'] ?? null;
    }

    // 13) Export protokollieren (unveraenderbar = GoBD)
    // Aufbewahrungsfrist automatisch setzen (10 Jahre)
    $retentionExpiresAt = date('Y-m-d H:i:s', strtotime('+10 years'));
    $db->insert('document_exports', [
      'instances_id'=>$instanceId,'projects_id'=>$projectId,'type'=>$type,
      'template_id'=>$tpl ? $tpl['id'] : 0,
      'doc_number'=>$docNumber,'language'=>'de-DE','currency'=>'EUR',
      'totals_json'=>json_encode($totals),'snapshot_json'=>json_encode(['lines'=>$flatLines,'categories'=>$categories,'doc'=>$docData]),
      's3files_id'=>$fileInfo['s3files_id'],'generated_by'=>$userId,
      'zugferd_xml_s3files_id'=>$zugferdFileId,
      'retention_expires_at'=>$retentionExpiresAt,
      'archive_status'=>'active',
    ]);
    return [
      'doc_number'=>$docNumber,
      's3files_id'=>$fileInfo['s3files_id'],
      'zugferd_s3files_id'=>$zugferdFileId,
      'xrechnung_s3files_id'=>$xrechnungFileId,
    ];
  }

  /**
   * Renders a preview HTML (no save, no sequence number, watermark overlay).
   * Returns HTML string for iframe display.
   */
  public static function renderPreview($db, int $instanceId, int $projectId, string $type, string $templateKey, array $opts): string {
    // 1) Stammdaten
    $business = BusinessRepo::getSettings($db, $instanceId);
    $project  = ProjectRepo::getWithFinance($db, $instanceId, $projectId);
    $client   = ClientsRepo::getById($db, $project['clients_id']);

    // 2) Miettage / Leistungszeitraum
    $durationDays = isset($opts['duration_days']) && $opts['duration_days']>0
      ? (int)$opts['duration_days']
      : self::calcDays(new DateTime($project['projects_dateStart']), new DateTime($project['projects_dateEnd']));

    // 3) Zeilen
    $lineData = self::buildLines($project, $durationDays, $opts);
    $flatLines  = $lineData['_flat'];
    $categories = $lineData['_grouped'];

    // 4) KUR
    $kurEnabled = (bool)($business['instances_kurEnabled'] ?? true);
    if ($kurEnabled && !empty($business['instances_kurTransitionYear'])) {
      if ((int)date('Y') >= (int)$business['instances_kurTransitionYear']) {
        $kurEnabled = false;
      }
    }
    $vatRate = $kurEnabled ? 0 : (float)($business['instances_vatRate'] ?? 19.0);

    // 5) Summen
    $discountPct = max(0.0, min(100.0, (float)($opts['discount_pct'] ?? 0)));
    $totals = self::calcTotals($flatLines, [
      'kur' => $kurEnabled, 'vat_rate' => $vatRate, 'discount_pct' => $discountPct,
    ]);

    // 6) Preview number
    $docNumber = 'VORSCHAU';

    // 7) Dates
    $paymentTermDays = (int)($client['clients_paymentTermDays'] ?? $business['instances_paymentTermDays'] ?? 14);
    $docDate = new DateTime();
    $dueDate = (clone $docDate)->modify("+{$paymentTermDays} days");

    // Skonto
    $skontoEnabled = !empty($opts['skonto_enabled']);
    $skontoRate = $skontoEnabled ? (float)($opts['skonto_rate'] ?? $client['clients_skontoRate'] ?? $business['instances_skontoRate'] ?? 0) : 0;
    $skontoDays = $skontoEnabled ? (int)($opts['skonto_days'] ?? $client['clients_skontoDays'] ?? $business['instances_skontoDays'] ?? 0) : 0;
    $skontoAmount = 0;
    $skontoDate = null;
    if ($skontoEnabled && $skontoRate > 0 && $skontoDays > 0 && $type === 'invoice') {
      $skontoAmount = round($totals['gross'] * $skontoRate / 100, 2);
      $skontoDate = (clone $docDate)->modify("+{$skontoDays} days");
      $totals['skonto_rate'] = $skontoRate;
      $totals['skonto_days'] = $skontoDays;
      $totals['skonto_amount'] = $skontoAmount;
      $totals['skonto_gross'] = round($totals['gross'] - $skontoAmount, 2);
      $totals['skonto_date'] = $skontoDate->format('d.m.Y');
    }

    // 8) Leistungszeitraum
    $servicePeriodStart = new DateTime($project['projects_dateStart'] ?? $project['projects_dates_deliver_start'] ?? 'now');
    $servicePeriodEnd   = new DateTime($project['projects_dateEnd'] ?? $project['projects_dates_deliver_end'] ?? 'now');

    // 9) Doc data
    $typeLabels = [
      'invoice'       => ['title' => 'Rechnung',     'number_label' => 'Rechnungsnummer'],
      'quote'         => ['title' => 'Angebot',       'number_label' => 'Angebotsnummer'],
      'delivery_note' => ['title' => 'Lieferschein',  'number_label' => 'Lieferscheinnummer'],
    ];
    $docData = [
      'type'          => $type,
      'number'        => $docNumber,
      'date'          => $docDate,
      'due_date'      => $dueDate,
      'payment_term_days' => $paymentTermDays,
      'duration_days' => $durationDays,
      'discount_pct'  => $discountPct,
      'service_period_start' => $servicePeriodStart,
      'service_period_end'   => $servicePeriodEnd,
      'title'         => ($typeLabels[$type]['title'] ?? $type) . ' (VORSCHAU)',
      'number_label'  => $typeLabels[$type]['number_label'] ?? 'Dokumentnummer',
      'skonto_rate'   => $skontoRate,
      'skonto_days'   => $skontoDays,
      'skonto_amount' => $skontoAmount,
      'skonto_date'   => $skontoDate ? $skontoDate->format('d.m.Y') : null,
      'valid_until'   => null,
    ];

    // For quotes: add valid_until
    if ($type === 'quote') {
      $validUntilStr = $opts['valid_until'] ?? null;
      if ($validUntilStr) {
        $docData['valid_until'] = new DateTime($validUntilStr);
      } else {
        $db->where('instances_id', $instanceId);
        $instRow = $db->getOne('instances', ['valid_until_default_days']);
        $defaultDays = (int)($instRow['valid_until_default_days'] ?? 30) ?: 30;
        $docData['valid_until'] = (clone $docDate)->modify("+{$defaultDays} days");
      }
    }

    // 10) Template
    $db->where('instances_id', $instanceId);
    $db->where('type', $type);
    $db->where('key', $templateKey);
    $tpl = $db->getOne('document_templates');

    if ($tpl) {
      $twig = new TwigEnv(new ArrayLoader(['tpl' => $tpl['twig_html']]), ['cache'=>false,'autoescape'=>false]);
    } else {
      $twig = new TwigEnv(new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'), ['cache'=>false,'autoescape'=>false]);
    }

    $twig->addFilter(new \Twig\TwigFilter('numberDe', function ($value, int $decimals = 2) {
      return number_format((float)$value, $decimals, ',', '.');
    }));
    $twig->addFilter(new \Twig\TwigFilter('dateDe', function ($datetime, string $format = 'd.m.Y') {
      if ($datetime instanceof \DateTimeInterface) return $datetime->format($format);
      if (is_string($datetime) && strlen($datetime) > 0) return date($format, strtotime($datetime));
      return '';
    }));

    $logoDataUri = null;
    if (!empty($business['instances_logo'])) {
      global $bCMS;
      if (isset($bCMS)) {
        $logoDataUri = $bCMS->s3DataUri($business['instances_logo']);
      }
    }

    // QR-Codes erzeugen (auch in Vorschau)
    $giroCodeDataUri = null;
    $deliveryNoteQrDataUri = null;

    if ($type === 'invoice') {
      $giroCodeDataUri = GiroCodeService::generateFromDocument($business, $docData, $totals);
    }

    if ($type === 'delivery_note') {
      $deliveryNoteQrDataUri = self::generateDeliveryNoteQr($db, $instanceId, $projectId, 'VORSCHAU');
    }

    $templateVars = [
      'business'   => $business,
      'client'     => $client,
      'project'    => $project,
      'doc'        => $docData,
      'lines'      => $flatLines,
      'categories' => $categories,
      'totals'     => $totals,
      'options'    => $opts,
      'logo'       => $logoDataUri ?: null,
      'giro_code'  => $giroCodeDataUri,
      'delivery_note_qr' => $deliveryNoteQrDataUri,
    ];

    $html = $tpl
      ? $twig->render('tpl', $templateVars)
      : $twig->render('document_de.twig', $templateVars);

    // Add watermark overlay
    $watermark = '<style>
      .preview-watermark {
        position: fixed; top: 35%; left: 10%; z-index: 9999;
        font-size: 72pt; color: rgba(255,0,0,0.12); font-weight: bold;
        transform: rotate(-35deg); pointer-events: none;
        white-space: nowrap; letter-spacing: 8px;
      }
    </style>
    <div class="preview-watermark">VORSCHAU / PREVIEW</div>';

    // Insert watermark before closing </body>
    $html = str_replace('</body>', $watermark . '</body>', $html);

    return $html;
  }

  private static function calcDays(DateTime $start, DateTime $end): int {
    $sec = max(0, $end->getTimestamp() - $start->getTimestamp());
    return max(1, (int)ceil($sec / 86400));
  }

  private static function buildLines(array $project, int $days, array $opts): array {
    $groupPrefix = $opts['set_group_prefix'] ?? 'Set:';
    $showCompPrices = $opts['show_component_prices'] ?? true;

    // Collect all lines with their category info
    $allLines = [];

    foreach ($project['assets'] as $a) {
      $qty = (float)($a['qty'] ?? 1);
      $total = (float)($a['total_net'] ?? 0);
      $dayPrice = isset($a['price_day_net']) ? (float)$a['price_day_net'] : ($qty>0 && $days>0 ? round($total/($qty*$days),2) : $total);
      $line = [
        'kind'=>'asset','name'=>$a['name'],'qty'=>$qty,'unit'=>$a['unit'] ?? 'Tag',
        'day_price'=>$dayPrice,'days'=>$days,'total'=>round($dayPrice*$days*$qty,2),
        'note'=>$a['note'] ?? null,'is_daily'=>true,
        'category_name' => $a['assetCategories_name'] ?? $a['category_name'] ?? 'Sonstiges',
        'category_rank' => (int)($a['assetCategories_rank'] ?? $a['category_rank'] ?? 999),
        'category_icon' => $a['assetCategories_fontAwesome'] ?? $a['category_icon'] ?? '',
      ];
      $setName = null;
      foreach (($a['groups'] ?? []) as $g) {
        if (stripos($g['name'],$groupPrefix)===0) { $setName = trim(substr($g['name'], strlen($groupPrefix))); break; }
      }
      if ($setName) {
        if (!isset($allLines['_sets'][$setName])) {
          $allLines['_sets'][$setName] = [
            'components' => [],
            'category_name' => $line['category_name'],
            'category_rank' => $line['category_rank'],
            'category_icon' => $line['category_icon'],
          ];
        }
        $allLines['_sets'][$setName]['components'][] = $line;
      } else {
        $allLines[] = $line;
      }
    }

    // Extras go into "Sonstige Leistungen" category
    foreach ($project['extras'] as $e) {
      $allLines[] = [
        'kind'=>'extra','name'=>$e['name'],'qty'=>(float)($e['qty'] ?? 1),'unit'=>$e['unit'] ?? '',
        'day_price'=>null,'days'=>null,'total'=>round((float)($e['total_net'] ?? 0),2),
        'note'=>$e['note'] ?? null,'is_daily'=>false,
        'category_name' => 'Sonstige Leistungen',
        'category_rank' => 9999,
        'category_icon' => 'fas fa-plus-circle',
      ];
    }

    // Build sets as single lines with their category from first component
    $sets = $allLines['_sets'] ?? [];
    unset($allLines['_sets']);
    $flatLines = [];
    foreach ($sets as $name => $payload) {
      $sumDay=0; $sumTotal=0;
      foreach ($payload['components'] as $c) { $sumDay += ($c['day_price']??0)*($c['qty']??1); $sumTotal += ($c['total']??0); }
      $flatLines[] = [
        'kind'=>'set','name'=>$name,'qty'=>1,'unit'=>'Set',
        'day_price'=>round($sumDay,2),'days'=>$days,'total'=>round($sumTotal,2),
        'components'=>$payload['components'],'show_component_prices'=>$showCompPrices,
        'category_name' => $payload['category_name'],
        'category_rank' => $payload['category_rank'],
        'category_icon' => $payload['category_icon'],
      ];
    }
    $flatLines = array_merge($flatLines, array_values($allLines));

    // Sort by category_rank, then by name within category
    usort($flatLines, function ($a, $b) {
      $cmp = ($a['category_rank'] ?? 999) <=> ($b['category_rank'] ?? 999);
      if ($cmp !== 0) return $cmp;
      $cmp = ($a['category_name'] ?? '') <=> ($b['category_name'] ?? '');
      if ($cmp !== 0) return $cmp;
      return ($a['name'] ?? '') <=> ($b['name'] ?? '');
    });

    // Group into categories for the template
    $grouped = [];
    foreach ($flatLines as $line) {
      $cat = $line['category_name'] ?? 'Sonstiges';
      if (!isset($grouped[$cat])) {
        $grouped[$cat] = [
          'name'  => $cat,
          'icon'  => $line['category_icon'] ?? '',
          'rank'  => $line['category_rank'] ?? 999,
          'lines' => [],
          'subtotal' => 0.0,
        ];
      }
      $grouped[$cat]['lines'][] = $line;
      $grouped[$cat]['subtotal'] += (float)$line['total'];
    }
    // Round subtotals
    foreach ($grouped as &$g) { $g['subtotal'] = round($g['subtotal'], 2); }
    unset($g);

    // Sort groups by rank
    uasort($grouped, function ($a, $b) { return $a['rank'] <=> $b['rank']; });

    // Return both flat lines (for totals calculation) and grouped (for template)
    // We embed the grouped data as a special structure
    return ['_flat' => $flatLines, '_grouped' => array_values($grouped)];
  }

  private static function calcTotals(array $lines, array $cfg): array {
    $kur = (bool)($cfg['kur'] ?? true);
    $vatRate = (float)($cfg['vat_rate'] ?? 0);
    $discountPct = (float)($cfg['discount_pct'] ?? 0);
    $sub = 0.0; foreach ($lines as $l) { $sub += (float)$l['total']; } $sub = round($sub,2);
    $discount = round($sub * $discountPct / 100.0, 2);
    $after = round($sub - $discount, 2);
    if ($kur) return [
      'kur'=>true,'subtotal'=>$sub,'discount_pct'=>$discountPct,'discount'=>$discount,
      'net'=>$after,'vat_rate'=>0,'vat'=>0,'gross'=>$after,
      'kur_notice'=>'Kein Ausweis von Umsatzsteuer, da Kleinunternehmer gemaess § 19 UStG.'
    ];
    $vat = round($after * $vatRate / 100.0, 2);
    return [
      'kur'=>false,'subtotal'=>$sub,'discount_pct'=>$discountPct,'discount'=>$discount,
      'net'=>$after,'vat_rate'=>$vatRate,'vat'=>$vat,'gross'=>round($after+$vat,2),
      'kur_notice'=>null
    ];
  }

  private static function fileName(string $type, string $num): string {
    return ($type==='invoice'?'Rechnung':($type==='quote'?'Angebot':'Lieferschein')).'_'.$num.'.pdf';
  }

  /**
   * Erzeugt QR-Code fuer Lieferschein mit Link zum Packauftrag.
   *
   * Der QR-Code enthaelt eine URL zur mobilen Packansicht.
   * Ein sicherer Token wird generiert und in der Datenbank gespeichert,
   * damit der Link ohne Login geprueft werden kann.
   *
   * @param object $db         Datenbank-Verbindung
   * @param int    $instanceId Instanz-ID
   * @param int    $projectId  Projekt-ID
   * @param string $docNumber  Lieferscheinnummer
   * @return string|null       Data-URI des QR-Codes oder null
   */
  private static function generateDeliveryNoteQr($db, int $instanceId, int $projectId, string $docNumber): ?string
  {
    // Packliste fuer dieses Projekt suchen
    $db->where('instances_id', $instanceId);
    $db->where('projects_id', $projectId);
    $db->orderBy('created_at', 'DESC');
    $packingList = $db->getOne('packing_lists', ['id']);

    if (!$packingList) {
      return null;
    }

    $packingListId = (int)$packingList['id'];

    // Sicheren Token generieren und speichern
    $token = bin2hex(random_bytes(16));
    $db->where('id', $packingListId);
    $db->update('packing_lists', [
      'delivery_note_qr_token' => $token,
    ]);

    // Base-URL aus Konfiguration oder Server-Variablen ermitteln
    $baseUrl = '';
    if (defined('BASE_URL')) {
      $baseUrl = BASE_URL;
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }

    $url = rtrim($baseUrl, '/') . '/mobile/packing.php?id=' . $packingListId . '&token=' . $token;

    return QrCodeGenerator::generateDataUri($url, 180, 'M');
  }
}
