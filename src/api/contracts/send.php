<?php
/**
 * POST /api/contracts/send.php
 * Send contract for signing via email
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('CONTRACTS:SEND')) {
        throw new \Exception('Zugriff verweigert', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!isset($data['id'], $data['recipient_email'])) {
        throw new \Exception('Missing required fields: id, recipient_email');
    }

    $contractId = (int)$data['id'];
    $recipientEmail = $data['recipient_email'];

    // Validate email
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new \Exception('Invalid email address');
    }

    $service = new ContractService($db);
    $success = $service->sendContract($contractId, $recipientEmail, $CurrentUser['users_id']);

    echo json_encode([
        'success' => $success,
        'message' => 'Vertrag versendet',
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
