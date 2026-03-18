<?php
/**
 * POST /api/contracts/delete.php
 * Delete contract
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('CONTRACTS:EDIT')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!isset($data['id'])) {
        throw new \Exception('Missing contract ID');
    }

    $contractId = (int)$data['id'];

    $service = new ContractService($db);
    $success = $service->deleteContract($contractId);

    echo json_encode([
        'success' => $success,
        'message' => 'Vertrag gelöscht',
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
