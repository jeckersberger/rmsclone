<?php
/**
 * GET /api/contracts/get.php?id=123
 * Get contract with versions and audit log
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('CONTRACTS:VIEW')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $contractId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($contractId <= 0) {
        throw new \Exception('Invalid contract ID');
    }

    $service = new ContractService($db);
    $contract = $service->getContract($contractId);

    if (!$contract) {
        throw new \Exception('Contract not found', 404);
    }

    echo json_encode([
        'success' => true,
        'data' => $contract,
    ]);
} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
