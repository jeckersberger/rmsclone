<?php
/**
 * GET /api/contracts/list.php
 * List contracts with optional filtering
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    // Check permissions
    if (!hasPermission('CONTRACTS:VIEW')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $status = $_GET['status'] ?? null;
    $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;

    $service = new ContractService($db);
    $contracts = $service->getContracts($CurrentInstance['instances_id'], $status, $clientId);

    // Sanitize output
    foreach ($contracts as &$c) {
        unset($c['content_html'], $c['signature_data']);
    }

    echo json_encode([
        'success' => true,
        'data' => $contracts,
        'count' => count($contracts),
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
