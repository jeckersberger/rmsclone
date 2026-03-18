<?php
/**
 * GET /api/contracts/pdf.php?id=123
 * Download contract as PDF
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

try {
    if (!hasPermission('CONTRACTS:VIEW')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $contractId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($contractId <= 0) {
        throw new \Exception('Invalid contract ID');
    }

    $service = new ContractService($db);

    // Check contract exists
    $contract = $service->getContract($contractId);
    if (!$contract) {
        throw new \Exception('Contract not found', 404);
    }

    $pdf = $service->getContractPdf($contractId);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $contract['title'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
