<?php
/**
 * Label printing page for label printers (e.g. Zebra)
 *
 * Generates:
 * 1. ZPL code for direct Zebra printing (via Browser Print or Raw-USB)
 * 2. Printable HTML labels with QR codes as fallback
 *
 * Parameters: ?ids=1,2,3 (comma-separated asset IDs)
 *             &format=zpl (optional: zpl or html, default: html)
 */
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) die('No permission');

$instanceId = $AUTH->data['instance']['instances_id'];
$ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
$format = $_GET['format'] ?? 'html';

if (empty($ids)) die('No asset IDs provided');

// Load assets
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

// ZPL output for Zebra label printers
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

        // ZPL Label (51mm x 25mm at 203dpi = 408x203 dots)
        echo "^XA\n";
        echo "^CI28\n"; // UTF-8
        echo "^PW408\n"; // Label width
        echo "^LL203\n"; // Label height

        // QR code on the left
        echo "^FO10,10^BQN,2,4^FDMA,{$qrData}^FS\n";

        // Text to the right of QR
        echo "^FO140,10^A0N,22,22^FD{$tag}^FS\n";
        echo "^FO140,38^A0N,16,16^FD{$type}^FS\n";
        if ($manufacturer) {
            echo "^FO140,58^A0N,14,14^FD{$manufacturer}^FS\n";
        }
        if ($category) {
            echo "^FO140,76^A0N,14,14^FD{$category}^FS\n";
        }

        // Barcode at bottom
        if ($barcode) {
            echo "^FO10,160^BY1,2,30^BCN,30,N,N^FD{$barcode}^FS\n";
        }

        // Business name small bottom right
        echo "^FO300,180^A0N,12,12^FD{$businessName}^FS\n";

        echo "^XZ\n\n";
    }
    exit;
}

// HTML label output (printable)
$PAGEDATA['assets'] = $assets;
$PAGEDATA['businessName'] = $businessName;
$PAGEDATA['baseUrl'] = $baseUrl;
$PAGEDATA['pageConfig'] = ['TITLE' => 'Print Labels', 'NOMENU' => true, 'BREADCRUMB' => false];

echo $TWIG->render('mobile/labels.twig', $PAGEDATA);
