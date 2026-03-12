<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW"))
    finish(false, ["code" => "PERMISSIONS", "message" => "No permission"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$twigHtml = $_POST['twig_html'] ?? '';
$css      = $_POST['css'] ?? '';

if (strlen(trim($twigHtml)) < 1)
    finish(false, ["code" => "INVALID", "message" => "Template HTML is empty"]);

// Build sample data for preview
$business = [
    'instances_name' => $AUTH->data['instance']['instances_name'] ?? 'Musterfirma GmbH',
    'instances_address' => $AUTH->data['instance']['instances_address'] ?? "Musterstr. 1\n12345 Musterstadt",
    'instances_phone' => $AUTH->data['instance']['instances_phone'] ?? '+49 123 456789',
    'instances_email' => $AUTH->data['instance']['instances_email'] ?? 'info@musterfirma.de',
    'instances_website' => $AUTH->data['instance']['instances_website'] ?? 'www.musterfirma.de',
    'instances_taxNumber' => $AUTH->data['instance']['instances_taxNumber'] ?? '123/456/78901',
    'instances_vatId' => $AUTH->data['instance']['instances_vatId'] ?? '',
    'instances_kurEnabled' => $AUTH->data['instance']['instances_kurEnabled'] ?? 1,
    'instances_bankName' => $AUTH->data['instance']['instances_bankName'] ?? 'Sparkasse Musterstadt',
    'instances_bankIban' => $AUTH->data['instance']['instances_bankIban'] ?? 'DE89 3704 0044 0532 0130 00',
    'instances_bankBic' => $AUTH->data['instance']['instances_bankBic'] ?? 'COBADEFFXXX',
    'instances_ceoName' => $AUTH->data['instance']['instances_ceoName'] ?? 'Max Mustermann',
    'instances_companyRegNumber' => $AUTH->data['instance']['instances_companyRegNumber'] ?? '',
    'instances_courtOfJurisdiction' => $AUTH->data['instance']['instances_courtOfJurisdiction'] ?? 'Musterstadt',
    'instances_termsAndPayment' => $AUTH->data['instance']['instances_termsAndPayment'] ?? '',
    'instances_quoteTerms' => $AUTH->data['instance']['instances_quoteTerms'] ?? '',
];

$client = [
    'clients_name' => 'Beispielkunde GmbH',
    'clients_address' => "Kundenweg 42\n54321 Kundenstadt",
    'clients_vatId' => '',
    'clients_customerNumber' => 'KD-0001',
];

$project = [
    'projects_name' => 'Beispielveranstaltung 2026',
    'projects_invoiceNotes' => 'Vielen Dank fuer Ihren Auftrag!',
];

$docDate = new DateTime();
$dueDate = (clone $docDate)->modify('+14 days');
$doc = [
    'type' => 'invoice',
    'number' => 'RE-2026-0001',
    'date' => $docDate,
    'due_date' => $dueDate,
    'payment_term_days' => 14,
    'duration_days' => 3,
    'discount_pct' => 0,
    'service_period_start' => new DateTime('2026-03-15'),
    'service_period_end' => new DateTime('2026-03-17'),
    'title' => 'Rechnung',
    'number_label' => 'Rechnungsnummer',
];

// Sample line items grouped by category
$lines = [
    ['kind'=>'asset','name'=>'Moving Head Wash 600W','qty'=>4,'unit'=>'Tag','day_price'=>25.00,'days'=>3,'total'=>300.00,'note'=>null,'is_daily'=>true,'category_name'=>'Licht','category_rank'=>1],
    ['kind'=>'asset','name'=>'LED PAR 64 RGBW','qty'=>8,'unit'=>'Tag','day_price'=>8.00,'days'=>3,'total'=>192.00,'note'=>null,'is_daily'=>true,'category_name'=>'Licht','category_rank'=>1],
    ['kind'=>'asset','name'=>'DMX Controller GrandMA','qty'=>1,'unit'=>'Tag','day_price'=>45.00,'days'=>3,'total'=>135.00,'note'=>null,'is_daily'=>true,'category_name'=>'Licht','category_rank'=>1],
    ['kind'=>'asset','name'=>'Line Array Modul','qty'=>6,'unit'=>'Tag','day_price'=>35.00,'days'=>3,'total'=>630.00,'note'=>null,'is_daily'=>true,'category_name'=>'Audio','category_rank'=>2],
    ['kind'=>'asset','name'=>'Subwoofer 18"','qty'=>4,'unit'=>'Tag','day_price'=>28.00,'days'=>3,'total'=>336.00,'note'=>null,'is_daily'=>true,'category_name'=>'Audio','category_rank'=>2],
    ['kind'=>'asset','name'=>'Digitalmischpult 32ch','qty'=>1,'unit'=>'Tag','day_price'=>60.00,'days'=>3,'total'=>180.00,'note'=>null,'is_daily'=>true,'category_name'=>'Audio','category_rank'=>2],
    ['kind'=>'asset','name'=>'Kettenzug 1t','qty'=>4,'unit'=>'Tag','day_price'=>15.00,'days'=>3,'total'=>180.00,'note'=>null,'is_daily'=>true,'category_name'=>'Rigging','category_rank'=>3],
    ['kind'=>'asset','name'=>'Traverse 3m','qty'=>8,'unit'=>'Tag','day_price'=>6.00,'days'=>3,'total'=>144.00,'note'=>null,'is_daily'=>true,'category_name'=>'Rigging','category_rank'=>3],
    ['kind'=>'extra','name'=>'Anlieferung & Abholung','qty'=>1,'unit'=>'pauschal','day_price'=>null,'days'=>null,'total'=>250.00,'note'=>null,'is_daily'=>false,'category_name'=>'Sonstige Leistungen','category_rank'=>9999],
];

$categories = [
    ['name'=>'Licht','icon'=>'fas fa-lightbulb','rank'=>1,'lines'=>array_slice($lines,0,3),'subtotal'=>627.00],
    ['name'=>'Audio','icon'=>'fas fa-volume-up','rank'=>2,'lines'=>array_slice($lines,3,3),'subtotal'=>1146.00],
    ['name'=>'Rigging','icon'=>'fas fa-link','rank'=>3,'lines'=>array_slice($lines,6,2),'subtotal'=>324.00],
    ['name'=>'Sonstige Leistungen','icon'=>'fas fa-plus-circle','rank'=>9999,'lines'=>array_slice($lines,8,1),'subtotal'=>250.00],
];

$subtotal = 2347.00;
$totals = [
    'kur' => true,
    'subtotal' => $subtotal,
    'discount_pct' => 0,
    'discount' => 0,
    'net' => $subtotal,
    'vat_rate' => 0,
    'vat' => 0,
    'gross' => $subtotal,
    'kur_notice' => 'Kein Ausweis von Umsatzsteuer, da Kleinunternehmer gemaess § 19 UStG.',
];

try {
    $twig = new \Twig\Environment(
        new \Twig\Loader\ArrayLoader(['preview' => $twigHtml]),
        ['cache' => false, 'autoescape' => false, 'strict_variables' => false]
    );

    // Add the same filters as DocumentRenderer
    $twig->addFilter(new \Twig\TwigFilter('numberDe', function ($value, int $decimals = 2) {
        return number_format((float)$value, $decimals, ',', '.');
    }));
    $twig->addFilter(new \Twig\TwigFilter('dateDe', function ($datetime, string $format = 'd.m.Y') {
        if ($datetime instanceof \DateTimeInterface) return $datetime->format($format);
        if (is_string($datetime) && strlen($datetime) > 0) return date($format, strtotime($datetime));
        return '';
    }));

    $html = $twig->render('preview', [
        'business'   => $business,
        'client'     => $client,
        'project'    => $project,
        'doc'        => $doc,
        'lines'      => $lines,
        'categories' => $categories,
        'totals'     => $totals,
        'options'    => [],
        'css'        => $css,
    ]);

    finish(true, null, ["html" => $html]);
} catch (\Twig\Error\Error $e) {
    finish(false, ["code" => "TWIG_ERROR", "message" => "Template-Fehler: " . $e->getMessage()]);
} catch (\Exception $e) {
    finish(false, ["code" => "ERROR", "message" => "Fehler: " . $e->getMessage()]);
}
