<?php
/**
 * POST /api/contracts/create.php
 * Create new contract
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('CONTRACTS:EDIT')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!isset($data['title'], $data['content_html'], $data['clients_id'])) {
        throw new \Exception('Missing required fields: title, content_html, clients_id');
    }

    $service = new ContractService($DBLIB);
    $contractId = $service->createContract([
        'instances_id' => $AUTH->data['instance']['instances_id'],
        'projects_id' => $data['projects_id'] ?? null,
        'clients_id' => (int)$data['clients_id'],
        'template_id' => isset($data['template_id']) ? (int)$data['template_id'] : null,
        'title' => $data['title'],
        'content_html' => $data['content_html'],
        'status' => $data['status'] ?? 'draft',
        'valid_from' => $data['valid_from'] ?? null,
        'valid_until' => $data['valid_until'] ?? null,
        'created_by' => $AUTH->data['users_userid'],
    ]);

    echo json_encode([
        'success' => true,
        'id' => $contractId,
        'message' => 'Vertrag erstellt',
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
