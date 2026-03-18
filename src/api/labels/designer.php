<?php
/**
 * Label Designer API
 *
 * Manages label templates and generates ZPL from templates.
 * Supports the Zebra LP2824 (2-inch, 203dpi, direct thermal).
 *
 * POST Parameters:
 *   action = list_templates | get_template | save_template | delete_template | preview_zpl | print_zpl
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
    finish(false, ["message" => "Permission denied"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list_templates':
        handleListTemplates($instanceId);
        break;

    case 'get_template':
        handleGetTemplate($instanceId);
        break;

    case 'save_template':
        handleSaveTemplate($instanceId);
        break;

    case 'delete_template':
        handleDeleteTemplate($instanceId);
        break;

    case 'preview_zpl':
        handlePreviewZpl();
        break;

    case 'print_zpl':
        handlePrintZpl();
        break;

    case 'list_label_sizes':
        handleListLabelSizes($instanceId);
        break;

    case 'save_label_size':
        handleSaveLabelSize($instanceId);
        break;

    case 'delete_label_size':
        handleDeleteLabelSize($instanceId);
        break;

    case 'printer_settings':
        // Return LP2824 specifications
        finish(true, null, [
            'printer' => [
                'model'       => 'Zebra LP2824',
                'dpi'         => 203,
                'max_width_dots'  => 464,   // 2.28 inch
                'max_width_mm'    => 58,
                'print_method'    => 'Direct Thermal',
                'language'        => 'ZPL II',
                'default_label' => [
                    'width'  => 464,  // dots
                    'height' => 200,  // dots (1 inch)
                ],
                'label_sizes' => [
                    ['name' => '57x32mm', 'width' => 464, 'height' => 260],
                    ['name' => '57x25mm (Standard)', 'width' => 464, 'height' => 200],
                    ['name' => '57x19mm', 'width' => 464, 'height' => 152],
                    ['name' => '57x51mm', 'width' => 464, 'height' => 410],
                ],
                'fonts' => [
                    ['id' => 'A', 'name' => 'Standard (proportional)', 'sizes' => [16, 18, 20, 22, 24, 26, 28, 32, 36, 40, 48]],
                    ['id' => 'B', 'name' => 'Kompakt', 'sizes' => [12, 14, 16, 18, 20]],
                    ['id' => '0', 'name' => 'Standard (skalierbar)', 'sizes' => [16, 18, 20, 22, 24, 26, 28, 32, 36, 40, 48, 56, 64]],
                ],
                'element_types' => [
                    'text'    => 'Textfeld (statisch oder Datenfeld)',
                    'barcode' => 'Barcode (Code 128, Code 39, EAN)',
                    'qrcode'  => 'QR-Code',
                    'line'    => 'Linie (horizontal/vertikal)',
                    'box'     => 'Rahmen (Rechteck)',
                    'image'   => 'Grafik (Base64)'
                ],
                'barcode_types' => [
                    ['id' => 'C', 'name' => 'Code 128 (Standard)', 'zpl' => '^BC'],
                    ['id' => '3', 'name' => 'Code 39', 'zpl' => '^B3'],
                    ['id' => 'E', 'name' => 'EAN-13', 'zpl' => '^BE'],
                    ['id' => 'Q', 'name' => 'QR-Code', 'zpl' => '^BQ'],
                ],
                'data_fields' => [
                    'asset' => [
                        'type_name'      => 'Gerätetyp',
                        'asset_tag'      => 'Asset-Nummer',
                        'rfid_tag'       => 'RFID-Tag',
                        'rfid_indicator' => 'RF-Kennzeichen',
                        'barcode_data'   => 'Barcode (RMS-A-xxxxxx)',
                        'location'       => 'Lagerort',
                        'serial_number'  => 'Seriennummer',
                        'value'          => 'Wert',
                    ],
                    'stock' => [
                        'item_name'       => 'Artikelname',
                        'category'        => 'Kategorie',
                        'instance_number' => 'Instanz-Nummer',
                        'rfid_tag'        => 'RFID-Tag',
                        'rfid_indicator'  => 'RF-Kennzeichen',
                        'barcode_data'    => 'Barcode (RMS-I-xxxxxx)',
                        'sku'             => 'SKU',
                    ],
                    'external' => [
                        'description'    => 'Beschreibung',
                        'owner_name'     => 'Eigentümer',
                        'barcode_data'   => 'Barcode (EXT-xxxxxx)',
                        'rfid_tag'       => 'RFID-Tag',
                        'rfid_indicator' => 'RF-Kennzeichen',
                        'return_date'    => 'Rückgabedatum',
                        'project_name'   => 'Projektname',
                        'quantity'       => 'Menge',
                    ],
                ],
            ]
        ]);
        break;

    default:
        finish(false, ["message" => "Unknown action"]);
}

function handleListTemplates(int $instanceId): void
{
    global $DBLIB;
    $DBLIB->where('(instances_id = ? OR instances_id = 0)', [$instanceId]);
    $DBLIB->orderBy('entity_type', 'ASC');
    $DBLIB->orderBy('name', 'ASC');
    $templates = $DBLIB->get('label_templates', null, [
        'id', 'name', 'description', 'entity_type', 'label_width', 'label_height', 'is_default'
    ]);
    finish(true, null, ['templates' => $templates ?: []]);
}

function handleGetTemplate(int $instanceId): void
{
    global $DBLIB;
    $id = (int)($_POST['template_id'] ?? 0);
    if (!$id) finish(false, ['message' => 'template_id required']);

    $DBLIB->where('id', $id);
    $DBLIB->where('(instances_id = ? OR instances_id = 0)', [$instanceId]);
    $template = $DBLIB->getOne('label_templates');

    if ($template) {
        $template['elements'] = json_decode($template['elements'], true);
        finish(true, null, ['template' => $template]);
    } else {
        finish(false, ['message' => 'Template nicht gefunden']);
    }
}

function handleSaveTemplate(int $instanceId): void
{
    global $DBLIB;

    $id = (int)($_POST['template_id'] ?? 0);
    $data = [
        'instances_id' => $instanceId,
        'name'         => trim($_POST['name'] ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'entity_type'  => $_POST['entity_type'] ?? 'custom',
        'label_width'  => (int)($_POST['label_width'] ?? 464),
        'label_height' => (int)($_POST['label_height'] ?? 200),
        'elements'     => $_POST['elements'] ?? '[]',
        'is_default'   => (int)($_POST['is_default'] ?? 0),
    ];

    if (empty($data['name'])) finish(false, ['message' => 'Name ist Pflichtfeld']);

    // Validate elements JSON
    $decoded = json_decode($data['elements'], true);
    if ($decoded === null && $data['elements'] !== '[]') {
        finish(false, ['message' => 'Ungültiges JSON in elements']);
    }

    // If setting as default, unset previous default for same entity_type
    if ($data['is_default']) {
        $DBLIB->where('entity_type', $data['entity_type']);
        $DBLIB->where('(instances_id = ? OR instances_id = 0)', [$instanceId]);
        if ($id) $DBLIB->where('id', $id, '!=');
        $DBLIB->update('label_templates', ['is_default' => 0]);
    }

    if ($id > 0) {
        // Update
        $DBLIB->where('id', $id);
        $DBLIB->where('instances_id', $instanceId);
        $result = $DBLIB->update('label_templates', $data);
        finish($result, $result ? null : ['message' => 'Update fehlgeschlagen'], ['id' => $id, 'message' => 'Template aktualisiert']);
    } else {
        // Insert
        $newId = $DBLIB->insert('label_templates', $data);
        if ($newId) {
            finish(true, null, ['id' => $newId, 'message' => 'Template erstellt']);
        } else {
            finish(false, ['message' => 'Erstellen fehlgeschlagen']);
        }
    }
}

function handleDeleteTemplate(int $instanceId): void
{
    global $DBLIB;
    $id = (int)($_POST['template_id'] ?? 0);
    if (!$id) finish(false, ['message' => 'template_id required']);

    $DBLIB->where('id', $id);
    $DBLIB->where('instances_id', $instanceId);
    $result = $DBLIB->delete('label_templates');
    finish($result, $result ? null : ['message' => 'Löschen fehlgeschlagen'], ['message' => 'Template gelöscht']);
}

function handlePreviewZpl(): void
{
    $elements = json_decode($_POST['elements'] ?? '[]', true);
    $labelWidth = (int)($_POST['label_width'] ?? 464);
    $labelHeight = (int)($_POST['label_height'] ?? 200);
    $sampleData = json_decode($_POST['sample_data'] ?? '{}', true);

    $zpl = generateZplFromElements($elements, $labelWidth, $labelHeight, $sampleData);
    finish(true, null, ['zpl' => $zpl]);
}

function handlePrintZpl(): void
{
    $zpl = $_POST['zpl'] ?? '';
    $printerIp = $_POST['printer_ip'] ?? '';

    if (empty($zpl)) finish(false, ['message' => 'ZPL-Code fehlt']);

    if (!empty($printerIp)) {
        if (!filter_var($printerIp, FILTER_VALIDATE_IP)) {
            finish(false, ['message' => 'Ungültige Drucker-IP']);
        }
        $socket = @fsockopen($printerIp, 9100, $errno, $errstr, 5);
        if ($socket) {
            fwrite($socket, $zpl);
            fclose($socket);
            finish(true, null, ['message' => 'Label an Drucker gesendet']);
        } else {
            finish(false, ['message' => 'Drucker nicht erreichbar: ' . $errstr]);
        }
    } else {
        finish(true, null, ['message' => 'ZPL generiert (kein Drucker konfiguriert)', 'zpl' => $zpl]);
    }
}

/**
 * Generate ZPL from template elements array + data
 */
function generateZplFromElements(array $elements, int $width, int $height, array $data): string
{
    $zpl = "^XA\n";
    $zpl .= "^CI28\n";
    $zpl .= "^PW" . $width . "\n";
    $zpl .= "^LL" . $height . "\n";

    foreach ($elements as $el) {
        $type = $el['type'] ?? 'text';
        $x = (int)($el['x'] ?? 0);
        $y = (int)($el['y'] ?? 0);

        switch ($type) {
            case 'text':
                $fontSize = (int)($el['font_size'] ?? 20);
                $font = $el['font'] ?? '0';
                $rotation = $el['rotation'] ?? 'N';

                // Get content: static text or data field
                $content = '';
                if (!empty($el['text'])) {
                    $content = $el['text'];
                } elseif (!empty($el['field']) && isset($data[$el['field']])) {
                    $content = (string)$data[$el['field']];
                }
                $prefix = $el['prefix'] ?? '';
                $suffix = $el['suffix'] ?? '';
                $fullText = $prefix . $content . $suffix;

                // Sanitize
                $fullText = preg_replace('/[^\x20-\x7EäöüÄÖÜß]/', '', $fullText);

                if (!empty($fullText)) {
                    $fontCmd = ($el['bold'] ?? false) ? "^A{$font}{$rotation},{$fontSize},{$fontSize}" : "^A{$font}{$rotation},{$fontSize},{$fontSize}";
                    $zpl .= "^FO{$x},{$y}{$fontCmd}^FD{$fullText}^FS\n";
                }
                break;

            case 'barcode':
                $barcodeHeight = (int)($el['height'] ?? 50);
                $barcodeType = $el['barcode_type'] ?? 'C'; // Code 128 default

                $content = '';
                if (!empty($el['field']) && isset($data[$el['field']])) {
                    $content = (string)$data[$el['field']];
                } elseif (!empty($el['text'])) {
                    $content = $el['text'];
                }

                if (!empty($content)) {
                    $content = preg_replace('/[^a-zA-Z0-9\-\._\/]/', '', $content);
                    $zpl .= "^FO{$x},{$y}^B{$barcodeType}N,{$barcodeHeight},N,N,N^FD{$content}^FS\n";
                }
                break;

            case 'qrcode':
                $magnification = (int)($el['magnification'] ?? 4);
                $content = '';
                if (!empty($el['field']) && isset($data[$el['field']])) {
                    $content = (string)$data[$el['field']];
                } elseif (!empty($el['text'])) {
                    $content = $el['text'];
                }
                if (!empty($content)) {
                    $zpl .= "^FO{$x},{$y}^BQN,2,{$magnification}^FDMA,{$content}^FS\n";
                }
                break;

            case 'line':
                $x2 = (int)($el['x2'] ?? $x + 100);
                $y2 = (int)($el['y2'] ?? $y);
                $thickness = (int)($el['thickness'] ?? 2);
                $lineWidth = abs($x2 - $x) ?: 1;
                $lineHeight = abs($y2 - $y) ?: $thickness;
                if ($y2 == $y) $lineHeight = $thickness; // horizontal
                $zpl .= "^FO{$x},{$y}^GB{$lineWidth},{$lineHeight},{$thickness}^FS\n";
                break;

            case 'box':
                $boxWidth = (int)($el['width'] ?? 100);
                $boxHeight = (int)($el['height'] ?? 50);
                $thickness = (int)($el['thickness'] ?? 2);
                $zpl .= "^FO{$x},{$y}^GB{$boxWidth},{$boxHeight},{$thickness}^FS\n";
                break;
        }
    }

    $zpl .= "^XZ\n";
    return $zpl;
}

// ══════════════════════════════════════
// Label Size Presets Management
// ══════════════════════════════════════

function handleListLabelSizes(int $instanceId): void
{
    global $DBLIB;
    $DBLIB->where('(instances_id = 0 OR instances_id = ?)', [$instanceId]);
    $DBLIB->orderBy('sort_order', 'ASC');
    $DBLIB->orderBy('name', 'ASC');
    $sizes = $DBLIB->get('label_size_presets');
    finish(true, null, ['sizes' => $sizes ?: []]);
}

function handleSaveLabelSize(int $instanceId): void
{
    global $DBLIB;

    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'instances_id' => $instanceId,
        'name'         => trim($_POST['name'] ?? ''),
        'width_mm'     => (float)($_POST['width_mm'] ?? 57),
        'height_mm'    => (float)($_POST['height_mm'] ?? 25),
        'dpi'          => (int)($_POST['dpi'] ?? 203),
        'is_system'    => 0,
        'sort_order'   => (int)($_POST['sort_order'] ?? 99),
    ];

    // Calculate dots from mm and dpi
    $data['width_dots'] = (int)round($data['width_mm'] * $data['dpi'] / 25.4);
    $data['height_dots'] = (int)round($data['height_mm'] * $data['dpi'] / 25.4);

    if (empty($data['name'])) finish(false, ['message' => 'Name ist Pflichtfeld']);

    if ($id > 0) {
        // Don't allow editing system presets
        $DBLIB->where('id', $id);
        $existing = $DBLIB->getOne('label_size_presets', ['is_system']);
        if ($existing && $existing['is_system']) {
            finish(false, ['message' => 'System-Presets können nicht bearbeitet werden']);
        }
        $DBLIB->where('id', $id);
        $DBLIB->where('instances_id', $instanceId);
        $result = $DBLIB->update('label_size_presets', $data);
        finish($result, $result ? null : ['message' => 'Update fehlgeschlagen'], ['id' => $id]);
    } else {
        $newId = $DBLIB->insert('label_size_presets', $data);
        finish((bool)$newId, $newId ? null : ['message' => 'Erstellen fehlgeschlagen'], ['id' => $newId]);
    }
}

function handleDeleteLabelSize(int $instanceId): void
{
    global $DBLIB;
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) finish(false, ['message' => 'id required']);

    // Don't allow deleting system presets
    $DBLIB->where('id', $id);
    $existing = $DBLIB->getOne('label_size_presets', ['is_system']);
    if ($existing && $existing['is_system']) {
        finish(false, ['message' => 'System-Presets können nicht gelöscht werden']);
    }

    $DBLIB->where('id', $id);
    $DBLIB->where('instances_id', $instanceId);
    $result = $DBLIB->delete('label_size_presets');
    finish($result, $result ? null : ['message' => 'Löschen fehlgeschlagen']);
}
