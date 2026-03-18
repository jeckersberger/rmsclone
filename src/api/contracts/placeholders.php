<?php
/**
 * GET /api/contracts/placeholders.php
 * Get available placeholders for contract templates
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('CONTRACTS:VIEW')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $service = new ContractService($db);
    $placeholders = $service->getAvailablePlaceholders();

    echo json_encode([
        'success' => true,
        'placeholders' => $placeholders,
    ]);
} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
