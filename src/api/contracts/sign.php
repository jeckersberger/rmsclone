<?php
/**
 * POST /api/contracts/sign.php
 * Record signature on contract (public endpoint, no auth required)
 */

require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!isset($data['contract_id'], $data['signer_name'], $data['signature_data'])) {
        throw new \Exception('Missing required fields: contract_id, signer_name, signature_data');
    }

    $contractId = (int)$data['contract_id'];
    $signerName = trim($data['signer_name']);
    $signatureData = $data['signature_data'];

    if (strlen($signerName) < 2) {
        throw new \Exception('Signer name must be at least 2 characters');
    }

    if (strlen($signatureData) < 50) {
        throw new \Exception('Invalid signature data');
    }

    $service = new ContractService($db);
    $success = $service->signContract(
        $contractId,
        $signerName,
        $signatureData,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    );

    echo json_encode([
        'success' => $success,
        'message' => 'Vertrag unterzeichnet',
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
