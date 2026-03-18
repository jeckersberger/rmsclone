<?php
/**
 * GET/POST /api/contracts/agb.php
 * List and create AGB sets
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    $service = new ContractService($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // List AGB sets
        if (!hasPermission('CONTRACTS:VIEW')) {
            throw new \Exception('Zugriff verweigert', 403);
        }

        $sets = $service->getAgbSets($CurrentInstance['instances_id']);

        echo json_encode([
            'success' => true,
            'data' => $sets,
            'count' => count($sets),
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create or update AGB set
        if (!hasPermission('CONTRACTS:EDIT')) {
            throw new \Exception('Zugriff verweigert', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!isset($data['name'], $data['content_html'])) {
            throw new \Exception('Missing required fields: name, content_html');
        }

        // Check for set_default action
        if (!empty($data['set_default']) && isset($data['id'])) {
            $success = $service->setDefaultAgb((int)$data['id'], $CurrentInstance['instances_id']);
            echo json_encode([
                'success' => $success,
                'message' => 'AGB als Standard gesetzt',
            ]);
            exit;
        }

        $setId = $service->createAgbSet([
            'instances_id' => $CurrentInstance['instances_id'],
            'name' => $data['name'],
            'content_html' => $data['content_html'],
        ]);

        echo json_encode([
            'success' => true,
            'id' => $setId,
            'message' => 'AGB-Set erstellt',
        ]);
    } else {
        throw new \Exception('Method not allowed', 405);
    }
} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
