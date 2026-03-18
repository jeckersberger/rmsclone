<?php
/**
 * External Items (Fremdmaterial) API
 *
 * POST Parameters:
 *   action = list | get | create | update | delete | scan_lookup | mark_returned | mark_lost | stats | print_label
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ExternalItemService.php';
require_once __DIR__ . '/../../services/TagFormatService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users']['users_id'];
$action = $_POST['action'] ?? '';
$extService = new ExternalItemService($DBLIB);

switch ($action) {
    case 'list':
        $filters = [];
        if (!empty($_POST['status'])) $filters['status'] = $_POST['status'];
        if (!empty($_POST['owner_name'])) $filters['owner_name'] = $_POST['owner_name'];
        if (!empty($_POST['project_id'])) $filters['project_id'] = (int)$_POST['project_id'];
        if (!empty($_POST['search'])) $filters['search'] = $_POST['search'];

        $items = $extService->listItems($instanceId, $filters);
        $stats = $extService->getStats($instanceId);
        echo json_encode(['success' => true, 'items' => $items, 'stats' => $stats]);
        exit;

    case 'get':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); exit; }
        $item = $extService->get($id);
        if ($item) {
            echo json_encode(['success' => true, 'item' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nicht gefunden']);
        }
        exit;

    case 'create':
        if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:CREATE")) {
            finish(false, ["message" => "Permission denied"]);
        }
        // Initialize TagFormatService for barcode generation
        $tagService = new TagFormatService($DBLIB, $instanceId);
        $extService->setTagFormatService($tagService);

        $data = [
            'description'     => $_POST['description'] ?? '',
            'owner_name'      => $_POST['owner_name'] ?? '',
            'owner_contact'   => $_POST['owner_contact'] ?? null,
            'quantity'        => (int)($_POST['quantity'] ?? 1),
            'barcode'         => $_POST['barcode'] ?? null,
            'rfid_tag'        => $_POST['rfid_tag'] ?? null,
            'project_id'      => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
            'location_id'     => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
            'location_custom' => $_POST['location_custom'] ?? null,
            'return_date'     => $_POST['return_date'] ?? null,
            'notes'           => $_POST['notes'] ?? null,
            'received_by'     => $userId,
        ];
        $id = $extService->create($instanceId, $data);
        if ($id) {
            $item = $extService->get($id);
            echo json_encode(['success' => true, 'id' => $id, 'item' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erstellen fehlgeschlagen. Beschreibung und Eigentümer sind Pflichtfelder.']);
        }
        exit;

    case 'update':
        if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:EDIT")) {
            finish(false, ["message" => "Permission denied"]);
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); exit; }

        $data = [];
        $fields = ['description','owner_name','owner_contact','quantity','barcode','rfid_tag',
                    'project_id','location_id','location_custom','status','return_date','notes'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) $data[$f] = $_POST[$f];
        }

        $result = $extService->update($id, $data);
        echo json_encode(['success' => $result, 'message' => $result ? 'Aktualisiert' : 'Fehler']);
        exit;

    case 'delete':
        if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:DELETE")) {
            finish(false, ["message" => "Permission denied"]);
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); exit; }
        $result = $extService->delete($id);
        echo json_encode(['success' => $result]);
        exit;

    case 'scan_lookup':
        $scanValue = trim($_POST['scan_value'] ?? '');
        if (empty($scanValue)) { echo json_encode(['success' => false, 'message' => 'scan_value required']); exit; }
        $item = $extService->findByScan($scanValue);
        if ($item) {
            echo json_encode(['success' => true, 'item' => $item, 'entity_type' => 'external']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kein Fremdmaterial mit diesem Code gefunden']);
        }
        exit;

    case 'mark_returned':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); exit; }
        $result = $extService->markReturned($id, $userId);
        echo json_encode(['success' => $result, 'message' => $result ? 'Als zurueckgegeben markiert' : 'Fehler']);
        exit;

    case 'mark_lost':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); exit; }
        $result = $extService->markLost($id);
        echo json_encode(['success' => $result, 'message' => $result ? 'Als verloren markiert' : 'Fehler']);
        exit;

    case 'stats':
        $stats = $extService->getStats($instanceId);
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;

    case 'print_label':
        $id = (int)($_POST['id'] ?? 0);
        $printerIp = $_POST['printer_ip'] ?? '';
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); exit; }

        $item = $extService->get($id);
        if (!$item) { echo json_encode(['success' => false, 'message' => 'Nicht gefunden']); exit; }

        // Generate ZPL for external item label
        $zpl = generateExternalZpl($item);

        if (!empty($printerIp) && filter_var($printerIp, FILTER_VALIDATE_IP)) {
            $socket = @fsockopen($printerIp, 9100, $errno, $errstr, 5);
            if ($socket) {
                fwrite($socket, $zpl);
                fclose($socket);
                echo json_encode(['success' => true, 'message' => 'Label gedruckt', 'zpl' => $zpl]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Drucker nicht erreichbar: ' . $errstr, 'zpl' => $zpl]);
            }
        } else {
            echo json_encode(['success' => true, 'message' => 'ZPL generiert', 'zpl' => $zpl]);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}

function generateExternalZpl(array $item): string
{
    $desc = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $item['description'] ?? '');
    $owner = preg_replace('/[^a-zA-Z0-9\s\-\.\/_äöüÄÖÜß]/', '', $item['owner_name'] ?? '');
    $barcode = $item['barcode'] ?? 'EXT-' . str_pad($item['id'], 6, '0', STR_PAD_LEFT);
    $returnDate = $item['return_date'] ?? '';
    $project = $item['project_name'] ?? '';

    $zpl = "^XA\n";
    $zpl .= "^CI28\n";
    $zpl .= "^PW464\n";
    $zpl .= "^LL200\n";

    // Row 1: FREMDMATERIAL header (bold)
    $zpl .= "^FO10,5^A0N,24,24^FDFREMDMATERIAL^FS\n";

    // Separator line
    $zpl .= "^FO10,32^GB444,2,2^FS\n";

    // Row 2: Description (large)
    $zpl .= "^FO10,38^A0N,26,26^FD" . substr($desc, 0, 30) . "^FS\n";

    // Row 3: Owner
    $zpl .= "^FO10,68^A0N,20,20^FDEigent.: " . substr($owner, 0, 25) . "^FS\n";

    // Row 4: Barcode (Code 128)
    $zpl .= "^FO10,95^BCN,45,N,N,N^FD" . $barcode . "^FS\n";

    // Row 5: Barcode text
    $zpl .= "^FO10,146^A0N,16,16^FD" . $barcode . "^FS\n";

    // Row 6: Return date + Project
    if (!empty($returnDate)) {
        $zpl .= "^FO10,168^A0N,16,16^FDRueckgabe: " . $returnDate . "^FS\n";
    }
    if (!empty($project)) {
        $zpl .= "^FO250,168^A0N,16,16^FDProjekt: " . substr($project, 0, 15) . "^FS\n";
    }

    // RFID indicator
    if (!empty($item['rfid_tag'])) {
        $zpl .= "^FO410,5^A0N,20,20^FDRF^FS\n";
    }

    $zpl .= "^XZ\n";
    return $zpl;
}
