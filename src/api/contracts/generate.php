<?php
/**
 * POST /api/contracts/generate.php
 * Generate contract from project + template
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('CONTRACTS:EDIT')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!isset($data['project_id'], $data['template_id'])) {
        throw new \Exception('Missing required fields: project_id, template_id');
    }

    $service = new ContractService($db);
    $contractId = $service->generateFromProject(
        (int)$data['project_id'],
        (int)$data['template_id'],
        $CurrentInstance['instances_id'],
        $CurrentUser['users_id']
    );

    echo json_encode([
        'success' => true,
        'id' => $contractId,
        'message' => 'Vertrag aus Projekt generiert',
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
