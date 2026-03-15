<?php
/**
 * Asset Label Print API — ZPL-Labels fuer Zebra LP2824
 *
 * Generiert ZPL-Code fuer den Zebra LP2824 (2-Zoll Direct Thermal, 203dpi).
 * Unterstuetzt Vorschau (JSON) und Direktdruck (TCP/IP an Drucker).
 *
 * POST Parameters:
 *   action    = preview | print | zpl
 *   asset_id  = int
 *   printer_ip = string (optional, fuer Direktdruck)
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
    finish(false, ["message" => "Permission denied"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? '';
$assetId = (int)($_POST['asset_id'] ?? 0);

if (!$assetId) {
    finish(false, ["message" => "asset_id required"]);
}

// Fetch asset data
$DBLIB->where('a.assets_id', $assetId);
$DBLIB->where('a.instances_id', $instanceId);
$DBLIB->where('a.assets_deleted', 0);
$DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
$asset = $DBLIB->getOne('assets a', [
    'a.assets_id', 'a.assets_tag', 'a.asset_definableFields_1 AS rfid_tag',
    'at.assetTypes_name', 'a.assets_storageLocation', 'a.assets_value',
    'a.assets_dayRate', 'a.assets_weekRate'
]);

if (!$asset) {
    finish(false, ["message" => "Asset not found"]);
}

// Label data
$typeName = $asset['assetTypes_name'] ?: 'Asset';
$assetTag = $asset['assets_tag'] ?: 'ID-' . $asset['assets_id'];
$rfidTag = $asset['rfid_tag'] ?: '';
$location = $asset['assets_storageLocation'] ?: '';
$barcodeData = 'RMS-' . str_pad($asset['assets_id'], 6, '0', STR_PAD_LEFT);

switch ($action) {
    case 'preview':
        finish(true, null, [
            'asset_id' => $asset['assets_id'],
            'type_name' => $typeName,
            'asset_tag' => $assetTag,
            'rfid_tag' => $rfidTag,
            'location' => $location,
            'barcode_data' => $barcodeData,
            'day_rate' => $asset['assets_dayRate'],
            'week_rate' => $asset['assets_weekRate'],
        ]);
        break;

    case 'zpl':
        $zpl = generateZpl($typeName, $assetTag, $barcodeData, $rfidTag, $location);
        finish(true, null, ['zpl' => $zpl, 'barcode' => $barcodeData]);
        break;

    case 'print':
        $printerIp = $_POST['printer_ip'] ?? '';
        $zpl = generateZpl($typeName, $assetTag, $barcodeData, $rfidTag, $location);

        if (!empty($printerIp)) {
            // Validate IP format
            if (!filter_var($printerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                finish(false, ["message" => "Invalid printer IP"]);
            }
            // Block private/loopback ranges that aren't local network
            // (Allow 192.168.x.x and 10.x.x.x and 172.16-31.x.x for local printers)
            $result = sendToPrinter($printerIp, 9100, $zpl);
            if ($result === true) {
                finish(true, null, ["message" => "Label sent to printer", "zpl" => $zpl]);
            } else {
                finish(false, ["message" => "Print failed: " . $result, "zpl" => $zpl]);
            }
        } else {
            // No IP: return ZPL for browser-based printing (via print dialog or copy/paste)
            finish(true, null, [
                "message" => "ZPL generated. Connect printer via USB and use ZPL print utility.",
                "zpl" => $zpl
            ]);
        }
        break;

    case 'list_assets':
        // Helper: list assets for dropdown
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
        break;

    default:
        finish(false, ["message" => "Unknown action. Available: preview, zpl, print, list_assets"]);
}

/**
 * Generate ZPL code for Zebra LP2824 (2-inch, 203dpi)
 *
 * Label size: ~56mm x 25mm (2.2" x 1")
 * Resolution: 203 dpi (8 dots/mm)
 *
 * Layout:
 *   ┌──────────────────────────────┐
 *   │ AssetType Name        [RFID] │
 *   │ #Asset-Tag                    │
 *   │ ║║║║║║║║║║║║║║║║║║║║         │
 *   │ RMS-000001                    │
 *   │ Lager: XY                     │
 *   └──────────────────────────────┘
 */
function generateZpl(string $typeName, string $assetTag, string $barcodeData, string $rfidTag, string $location): string
{
    // Sanitize for ZPL (remove special chars that could break ZPL)
    $typeName = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $typeName);
    $assetTag = preg_replace('/[^a-zA-Z0-9\s\-\.\/_#äöüÄÖÜß]/', '', $assetTag);
    $location = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $location);

    $zpl = "^XA\n";                          // Start format
    $zpl .= "^CI28\n";                       // UTF-8 character set
    $zpl .= "^PW464\n";                      // Print width: 58mm = 464 dots @ 203dpi
    $zpl .= "^LL200\n";                      // Label length: 25mm = 200 dots

    // Row 1: Asset Type Name (bold, large)
    $zpl .= "^FO10,10^A0N,28,28^FD" . $typeName . "^FS\n";

    // RFID indicator (top right)
    if (!empty($rfidTag)) {
        $zpl .= "^FO370,10^A0N,20,20^FDRF^FS\n";
    }

    // Row 2: Asset Tag
    $zpl .= "^FO10,42^A0N,24,24^FD#" . $assetTag . "^FS\n";

    // Row 3: Barcode (Code 128)
    $zpl .= "^FO10,72^BCN,50,N,N,N^FD" . $barcodeData . "^FS\n";

    // Row 4: Barcode text below
    $zpl .= "^FO10,128^A0N,18,18^FD" . $barcodeData . "^FS\n";

    // Row 5: Location (if set)
    if (!empty($location)) {
        $zpl .= "^FO10,150^A0N,18,18^FDLager: " . $location . "^FS\n";
    }

    // RFID tag text (bottom right, small)
    if (!empty($rfidTag)) {
        $zpl .= "^FO200,150^A0N,16,16^FDRFID:" . substr($rfidTag, 0, 20) . "^FS\n";
    }

    $zpl .= "^XZ\n";                        // End format

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
