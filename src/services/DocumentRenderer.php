<?php
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment as TwigEnv;
use Twig\Loader\ArrayLoader;

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
    // 1) Stammdaten
    $business = BusinessRepo::getSettings($db, $instanceId);
    $project  = ProjectRepo::getWithFinance($db, $instanceId, $projectId);
    $client   = ClientsRepo::getById($db, $project['clients_id']);

    // 2) Miettage / Leistungszeitraum
    $durationDays = isset($opts['duration_days']) && $opts['duration_days']>0
      ? (int)$opts['duration_days']
      : self::calcDays(new DateTime($project['projects_dateStart']), new DateTime($project['projects_dateEnd']));

    // 3) Zeilen (Sets, Tagespreis, Istpreis)
    $lines = self::buildLines($project, $durationDays, $opts);

    // 4) KUR aus Business-Settings
    $kurEnabled = (bool)($business['instances_kurEnabled'] ?? true);
    $vatRate = $kurEnabled ? 0 : (float)($business['instances_vatRate'] ?? 19.0);

    // 5) Summen + Rabatt + KUR/MwSt
    $discountPct = max(0.0, min(100.0, (float)($opts['discount_pct'] ?? 0)));
    $totals = self::calcTotals($lines, [
      'kur' => $kurEnabled,
      'vat_rate' => $vatRate,
      'discount_pct' => $discountPct,
    ]);

    // 6) Nummer ziehen
    $docNumber = SequenceService::next($db, $instanceId, $type);

    // 7) Faelligkeitsdatum berechnen
    $paymentTermDays = (int)($client['clients_paymentTermDays'] ?? $business['instances_paymentTermDays'] ?? 14);
    $docDate = new DateTime();
    $dueDate = (clone $docDate)->modify("+{$paymentTermDays} days");

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
    ];

    // 10) Try custom template first, fall back to built-in
    $tpl = $db->fetchRow("SELECT * FROM document_templates WHERE instances_id=? AND type=? AND `key`=?",
      [$instanceId, $type, $templateKey]);

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

    $templateVars = [
      'business' => $business,
      'client'   => $client,
      'project'  => $project,
      'doc'      => $docData,
      'lines'    => $lines,
      'totals'   => $totals,
      'options'  => $opts,
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

    // 12) Speichern als Projektdokument
    $fileType = ['invoice'=>20,'quote'=>21,'delivery_note'=>22][$type];
    $fileInfo = S3Files::storeProjectFile($db, $instanceId, $projectId, $fileType, [
      'name' => self::fileName($type, $docNumber), 'content'=>$pdf, 'extension'=>'pdf'
    ]);

    // 13) Export protokollieren (unveraenderbar = GoBD)
    $db->insert('document_exports', [
      'instances_id'=>$instanceId,'projects_id'=>$projectId,'type'=>$type,
      'template_id'=>$tpl ? $tpl['id'] : 0,
      'doc_number'=>$docNumber,'language'=>'de-DE','currency'=>'EUR',
      'totals_json'=>json_encode($totals),'snapshot_json'=>json_encode(['lines'=>$lines,'doc'=>$docData]),
      's3files_id'=>$fileInfo['s3files_id'],'generated_by'=>$userId
    ]);
    return ['doc_number'=>$docNumber,'s3files_id'=>$fileInfo['s3files_id']];
  }

  private static function calcDays(DateTime $start, DateTime $end): int {
    $sec = max(0, $end->getTimestamp() - $start->getTimestamp());
    return max(1, (int)ceil($sec / 86400));
  }

  private static function buildLines(array $project, int $days, array $opts): array {
    $groupPrefix = $opts['set_group_prefix'] ?? 'Set:';
    $showCompPrices = $opts['show_component_prices'] ?? true;
    $sets = [];
    $singles = [];

    foreach ($project['assets'] as $a) {
      $qty = (float)($a['qty'] ?? 1);
      $total = (float)($a['total_net'] ?? 0);
      $dayPrice = isset($a['price_day_net']) ? (float)$a['price_day_net'] : ($qty>0 && $days>0 ? round($total/($qty*$days),2) : $total);
      $line = [
        'kind'=>'asset','name'=>$a['name'],'qty'=>$qty,'unit'=>$a['unit'] ?? 'Tag',
        'day_price'=>$dayPrice,'days'=>$days,'total'=>round($dayPrice*$days*$qty,2),
        'note'=>$a['note'] ?? null,'is_daily'=>true
      ];
      $setName = null;
      foreach (($a['groups'] ?? []) as $g) {
        if (stripos($g['name'],$groupPrefix)===0) { $setName = trim(substr($g['name'], strlen($groupPrefix))); break; }
      }
      if ($setName) { $sets[$setName]['components'][] = $line; } else { $singles[] = $line; }
    }

    foreach ($project['extras'] as $e) {
      $singles[] = ['kind'=>'extra','name'=>$e['name'],'qty'=>(float)($e['qty'] ?? 1),'unit'=>$e['unit'] ?? '',
        'day_price'=>null,'days'=>null,'total'=>round((float)($e['total_net'] ?? 0),2),'note'=>$e['note'] ?? null,'is_daily'=>false];
    }

    $lines = [];
    foreach ($sets as $name=>$payload) {
      $sumDay=0; $sumTotal=0;
      foreach ($payload['components'] as $c) { $sumDay += ($c['day_price']??0)*($c['qty']??1); $sumTotal += ($c['total']??0); }
      $lines[] = ['kind'=>'set','name'=>$name,'qty'=>1,'unit'=>'Set','day_price'=>round($sumDay,2),'days'=>$days,'total'=>round($sumTotal,2),
        'components'=>$payload['components'],'show_component_prices'=>$showCompPrices];
    }
    return array_merge($lines, $singles);
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
}
