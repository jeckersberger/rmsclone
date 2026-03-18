<?php
/**
 * POST /api/contracts/update.php
 * Update contract (creates new version)
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

    $success = $service->updateContract($contractId, [
        'title' => $data['title'] ?? null,
        'content_html' => $data['content_html'] ?? null,
        'status' => $data['status'] ?? null,
        'valid_from' => $data['valid_from'] ?? null,
        'valid_until' => $data['valid_until'] ?? null,
        'change_notes' => $data['change_notes'] ?? null,
    ], $CurrentUser['users_id']);

    if (!$success) {
        throw new \Exception('Contract not found');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Vertrag aktualisiert',
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
