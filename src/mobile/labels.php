<?php
/**
 * Label-Druckseite für Zebra-Drucker
 *
 * Generiert:
 * 1. ZPL-Code für direkten Zebra-Druck (via Browser Print oder Raw-USB)
 * 2. Druckbare HTML-Labels mit QR-Codes als Fallback
 *
 * Parameter: ?ids=1,2,3 (kommagetrennte Asset-IDs)
 *            &format=zpl (optional: zpl oder html, default: html)
 */
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) die('Keine Berechtigung');

$instanceId = $AUTH->data['instance']['instances_id'];
$ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
$format = $_GET['format'] ?? 'html';

if (empty($ids)) die('Keine Asset-IDs angegeben');

// Assets laden
$assets = [];
foreach ($ids as $id) {
    $DBLIB->where('a.assets_id', $id);
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
    $DBLIB->join('assetCategories ac', 'at.assetCategories_id=ac.assetCategories_id', 'LEFT');
    $DBLIB->join('manufacturers m', 'at.manufacturers_id=m.manufacturers_id', 'LEFT');
    $asset = $DBLIB->getOne('assets a', [
        'a.assets_id', 'a.assets_tag', 'a.assets_barcode',
        'at.assetTypes_name', 'ac.assetCategories_name',
        'm.manufacturers_name'
    ]);
    if ($asset) $assets[] = $asset;
}

$businessName = $AUTH->data['instance']['instances_name'] ?? 'RMS';
$baseUrl = $CONFIG['ROOTURL'];

// ZPL-Ausgabe für Zebra-Drucker
if ($format === 'zpl') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="labels.zpl"');

    foreach ($assets as $a) {
        $tag = $a['assets_tag'] ?? '#' . $a['assets_id'];
        $type = $a['assetTypes_name'] ?? '';
        $category = $a['assetCategories_name'] ?? '';
        $manufacturer = $a['manufacturers_name'] ?? '';
        $qrData = "{$baseUrl}/asset/?id={$a['assets_id']}";
        $barcode = $a['assets_barcode'] ?? $tag;

        // ZPL Label (51mm x 25mm bei 203dpi = 408x203 dots)
        echo "^XA\n";
        echo "^CI28\n"; // UTF-8
        echo "^PW408\n"; // Labelbreite
        echo "^LL203\n"; // Labelhöhe

        // QR-Code links
        echo "^FO10,10^BQN,2,4^FDMA,{$qrData}^FS\n";

        // Text rechts neben QR
        echo "^FO140,10^A0N,22,22^FD{$tag}^FS\n";
        echo "^FO140,38^A0N,16,16^FD{$type}^FS\n";
        if ($manufacturer) {
            echo "^FO140,58^A0N,14,14^FD{$manufacturer}^FS\n";
        }
        if ($category) {
            echo "^FO140,76^A0N,14,14^FD{$category}^FS\n";
        }

        // Barcode unten
        if ($barcode) {
            echo "^FO10,160^BY1,2,30^BCN,30,N,N^FD{$barcode}^FS\n";
        }

        // Firmenname klein unten rechts
        echo "^FO300,180^A0N,12,12^FD{$businessName}^FS\n";

        echo "^XZ\n\n";
    }
    exit;
}

// HTML Label-Ausgabe (druckbar)
$PAGEDATA['assets'] = $assets;
$PAGEDATA['businessName'] = $businessName;
$PAGEDATA['baseUrl'] = $baseUrl;
$PAGEDATA['pageConfig'] = ['TITLE' => 'Labels drucken', 'NOMENU' => true, 'BREADCRUMB' => false];

echo $TWIG->render('mobile/labels.twig', $PAGEDATA);
