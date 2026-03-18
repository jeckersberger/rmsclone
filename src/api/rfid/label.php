<?php
/**
 * Asset & Stock Label Print API — ZPL-Labels fuer Zebra LP2824
 *
 * Generiert ZPL-Code fuer den Zebra LP2824 (2-Zoll Direct Thermal, 203dpi).
 * Unterstuetzt sowohl Assets (Geraete) als auch Stock-Instanzen (Artikel).
 *
 * POST Parameters:
 *   action        = preview | zpl | print | list_assets | preview_stock | print_stock
 *   asset_id      = int (fuer Asset-Labels)
 *   stock_item_id = int (fuer Artikel-Labels)
 *   printer_ip    = string (optional, fuer Direktdruck)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/StockItemService.php';
require_once __DIR__ . '/../../services/TagFormatService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
    finish(false, ["message" => "Permission denied"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    // ═══════════════════════════════════
    // Asset Labels (Geraete)
    // ═══════════════════════════════════
    case 'preview':
    case 'zpl':
    case 'print':
        handleAssetLabel($action, $instanceId);
        break;

    case 'list_assets':
        handleListAssets($instanceId);
        break;

    // ═══════════════════════════════════
    // Stock Instance Labels (Artikel)
    // ═══════════════════════════════════
    case 'preview_stock':
        handleStockPreview($instanceId);
        break;

    case 'print_stock':
        handleStockPrint($instanceId);
        break;

    default:
        finish(false, ["message" => "Unknown action. Available: preview, zpl, print, list_assets, preview_stock, print_stock"]);
}

// ══════════════════════════════════════
// Asset Label Handlers
// ══════════════════════════════════════
function handleAssetLabel(string $action, int $instanceId): void
{
    global $DBLIB;

    $assetId = (int)($_POST['asset_id'] ?? 0);
    if (!$assetId) {
        finish(false, ["message" => "asset_id required"]);
    }

    $DBLIB->where('a.assets_id', $assetId);
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
    $asset = $DBLIB->getOne('assets a', [
        'a.assets_id', 'a.assets_tag', 'a.asset_definableFields_1 AS rfid_tag',
        'at.assetTypes_name', 'a.assets_storageLocation', 'a.assets_value',
        'a.assets_dayRate', 'a.assets_weekRate', 'a.assets_serialInternal'
    ]);

    if (!$asset) {
        finish(false, ["message" => "Asset not found"]);
    }

    $typeName = $asset['assetTypes_name'] ?: 'Asset';
    $assetTag = $asset['assets_tag'] ?: 'ID-' . $asset['assets_id'];
    $rfidTag = $asset['rfid_tag'] ?: '';
    $location = $asset['assets_storageLocation'] ?: '';
    $serialInternal = $asset['assets_serialInternal'] ?: '';

    // Generate barcode using TagFormatService
    $tagService = new TagFormatService($DBLIB, $instanceId);
    $barcodeData = $tagService->generateAssetEpc($asset['assets_id']);

    switch ($action) {
        case 'preview':
            finish(true, null, [
                'asset_id'        => $asset['assets_id'],
                'type_name'       => $typeName,
                'asset_tag'       => $assetTag,
                'rfid_tag'        => $rfidTag,
                'location'        => $location,
                'serial_internal' => $serialInternal,
                'barcode_data'    => $barcodeData,
                'day_rate'        => $asset['assets_dayRate'],
                'week_rate'       => $asset['assets_weekRate'],
                'entity_type'     => 'asset',
            ]);
            break;

        case 'zpl':
            $zpl = generateAssetZpl($typeName, $assetTag, $barcodeData, $rfidTag, $location, $serialInternal);
            finish(true, null, ['zpl' => $zpl, 'barcode' => $barcodeData]);
            break;

        case 'print':
            $printerIp = $_POST['printer_ip'] ?? '';
            $zpl = generateAssetZpl($typeName, $assetTag, $barcodeData, $rfidTag, $location, $serialInternal);
            printZpl($zpl, $printerIp);
            break;
    }
}

// ══════════════════════════════════════
// Stock Label Handlers
// ══════════════════════════════════════
function handleStockPreview(int $instanceId): void
{
    global $DBLIB;
    $stockService = new StockItemService($DBLIB);

    $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
    if (!$stockItemId) {
        finish(false, ["message" => "stock_item_id required"]);
    }

    $item = $stockService->getItem($stockItemId);
    if (!$item) {
        finish(false, ["message" => "Stock item not found"]);
    }

    finish(true, null, [
        'stock_item_id'  => $item['id'],
        'item_name'      => $item['name'],
        'category'       => $item['category'],
        'sku'            => $item['sku'],
        'instance_count' => $item['counts']['total'] . ' total, ' . $item['counts']['available'] . ' verfuegbar',
        'entity_type'    => 'stock_item',
    ]);
}

function handleStockPrint(int $instanceId): void
{
    global $DBLIB;
    $stockService = new StockItemService($DBLIB);

    $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
    $printerIp = $_POST['printer_ip'] ?? '';

    if (!$stockItemId) {
        finish(false, ["message" => "stock_item_id required"]);
    }

    $item = $stockService->getItem($stockItemId);
    if (!$item) {
        finish(false, ["message" => "Stock item not found"]);
    }

    // Get all instances that have RFID tags
    $instances = $stockService->listInstances($stockItemId);
    $instancesWithRfid = array_filter($instances, function ($i) {
        return !empty($i['rfid_tag']);
    });

    if (empty($instancesWithRfid)) {
        finish(false, ["message" => "Keine Instanzen mit RFID-Tags vorhanden"]);
    }

    // Generate ZPL for each instance
    $allZpl = '';
    $printed = 0;
    foreach ($instancesWithRfid as $inst) {
        $zpl = generateStockZpl(
            $item['name'],
            $item['category'],
            $inst['instance_number'],
            $inst['rfid_tag'],
            $item['sku']
        );
        $allZpl .= $zpl;
        $printed++;
    }

    if (!empty($printerIp)) {
        if (!filter_var($printerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            finish(false, ["message" => "Invalid printer IP"]);
        }
        $result = sendToPrinter($printerIp, 9100, $allZpl);
        if ($result === true) {
            finish(true, null, [
                "message" => $printed . " Labels an Drucker gesendet",
                "printed" => $printed,
                "zpl"     => $allZpl,
            ]);
        } else {
            finish(false, ["message" => "Print failed: " . $result, "zpl" => $allZpl]);
        }
    } else {
        finish(true, null, [
            "message" => $printed . " Labels generiert (ZPL)",
            "printed" => $printed,
            "zpl"     => $allZpl,
        ]);
    }
}

function handleListAssets(int $instanceId): void
{
    global $DBLIB;
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
    $DBLIB->orderBy('at.assetTypes_name', 'ASC');
    $DBLIB->orderBy('a.assets_tag', 'ASC');
    $assets = $DBLIB->get('assets a', 500, [
        'a.assets_id', 'a.assets_tag', 'a.asset_definableFields_1 AS rfid_tag',
        'at.assetTypes_name'
    ]);
    finish(true, null, ["assets" => $assets ?: []]);
}

// ══════════════════════════════════════
// ZPL Generation
// ══════════════════════════════════════

/**
 * Generate ZPL for Asset label (Geraet)
 *
 * Layout (2.2" x 1", 203dpi):
 *   ┌──────────────────────────────┐
 *   │ GERAET  AssetType Name [RF] │
 *   │ S/N: MH-001    #Asset-Tag    │
 *   │ ║║║║║║║║║║║║║║║║║║║║         │
 *   │ RMS-A-000001                  │
 *   │ Lager: XY                     │
 *   └──────────────────────────────┘
 */
function generateAssetZpl(string $typeName, string $assetTag, string $barcodeData, string $rfidTag, string $location, string $serialInternal = ''): string
{
    $typeName = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $typeName);
    $assetTag = preg_replace('/[^a-zA-Z0-9\s\-\.\/_#äöüÄÖÜß]/', '', $assetTag);
    $location = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $location);
    $serialInternal = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $serialInternal);

    $zpl = "^XA\n";
    $zpl .= "^CI28\n";
    $zpl .= "^PW464\n";
    $zpl .= "^LL200\n";

    // Row 1: Asset Type Name (bold)
    $zpl .= "^FO10,10^A0N,28,28^FD" . $typeName . "^FS\n";

    // RFID indicator (top right)
    if (!empty($rfidTag)) {
        $zpl .= "^FO370,10^A0N,20,20^FDRF^FS\n";
    }

    // Row 2: Internal Serial (if exists) + Asset Tag
    if (!empty($serialInternal)) {
        $zpl .= "^FO10,42^A0N,20,20^FDS/N: " . $serialInternal . "^FS\n";
        $zpl .= "^FO250,42^A0N,20,20^FD#" . $assetTag . "^FS\n";
    } else {
        $zpl .= "^FO10,42^A0N,24,24^FD#" . $assetTag . "^FS\n";
    }

    // Row 3: Barcode (Code 128)
    $zpl .= "^FO10,72^BCN,50,N,N,N^FD" . $barcodeData . "^FS\n";

    // Row 4: Barcode text
    $zpl .= "^FO10,128^A0N,18,18^FD" . $barcodeData . "^FS\n";

    // Row 5: Location
    if (!empty($location)) {
        $zpl .= "^FO10,150^A0N,18,18^FDLager: " . $location . "^FS\n";
    }

    // RFID tag text (bottom right)
    if (!empty($rfidTag)) {
        $zpl .= "^FO200,150^A0N,16,16^FDRFID:" . substr($rfidTag, 0, 20) . "^FS\n";
    }

    $zpl .= "^XZ\n";

    return $zpl;
}

/**
 * Generate ZPL for Stock Instance label (Artikel)
 *
 * Layout (2.2" x 1", 203dpi) — kompakter, ohne Seriennummer:
 *   ┌──────────────────────────────┐
 *   │ ARTIKEL  Kabelname      [RF] │
 *   │ Kategorie        #0023       │
 *   │ ║║║║║║║║║║║║║║║║║║║║         │
 *   │ RMS-I-000023                  │
 *   │ SKU: HDMI-3M                  │
 *   └──────────────────────────────┘
 */
function generateStockZpl(string $itemName, string $category, int $instanceNumber, string $rfidTag, string $sku): string
{
    $itemName = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $itemName);
    $category = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $category);
    $sku = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $sku);

    $zpl = "^XA\n";
    $zpl .= "^CI28\n";
    $zpl .= "^PW464\n";
    $zpl .= "^LL200\n";

    // Row 1: Item Name
    $zpl .= "^FO10,10^A0N,28,28^FD" . $itemName . "^FS\n";

    // RFID indicator (top right)
    if (!empty($rfidTag)) {
        $zpl .= "^FO370,10^A0N,20,20^FDRF^FS\n";
    }

    // Row 2: Category + Instance Number
    $instanceLabel = '#' . str_pad($instanceNumber, 4, '0', STR_PAD_LEFT);
    if (!empty($category)) {
        $zpl .= "^FO10,42^A0N,22,22^FD" . $category . "^FS\n";
    }
    $zpl .= "^FO350,42^A0N,22,22^FD" . $instanceLabel . "^FS\n";

    // Row 3: Barcode (Code 128) using RFID tag as data
    $barcodeData = !empty($rfidTag) ? $rfidTag : 'RMS-I-' . str_pad($instanceNumber, 6, '0', STR_PAD_LEFT);
    $zpl .= "^FO10,72^BCN,50,N,N,N^FD" . $barcodeData . "^FS\n";

    // Row 4: Barcode text
    $zpl .= "^FO10,128^A0N,18,18^FD" . $barcodeData . "^FS\n";

    // Row 5: SKU (if set)
    if (!empty($sku)) {
        $zpl .= "^FO10,150^A0N,18,18^FDSKU: " . $sku . "^FS\n";
    }

    // RFID tag text (bottom right)
    if (!empty($rfidTag)) {
        $zpl .= "^FO200,150^A0N,16,16^FDRFID:" . substr($rfidTag, 0, 20) . "^FS\n";
    }

    $zpl .= "^XZ\n";

    return $zpl;
}

/**
 * Send ZPL to printer via raw TCP socket (port 9100)
 */
function sendToPrinter(string $ip, int $port, string $zpl)
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, 5);
    if (!$socket) {
        return "Connection failed: $errstr ($errno)";
    }
    fwrite($socket, $zpl);
    fclose($socket);
    return true;
}
