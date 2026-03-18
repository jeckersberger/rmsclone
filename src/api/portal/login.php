<?php
/**
 * POST /api/portal/login.php
 * Public endpoint - no auth required
 * Login as a portal client
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, ['message' => 'POST method required']);
}

$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$password = isset($_POST['password']) ? $_POST['password'] : null;

if (!$instanceId || !$email || !$password) {
    finish(false, ['message' => 'Missing required fields']);
}

$portalService = new BookingPortalService($DBLIB);
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    finish(false, ['message' => 'Portal not available']);
}

$client = $portalService->loginPortalClient($email, $password, $instanceId);

if ($client) {
    // Create session token
    $token = $portalService->createPortalSession($client['clients_id']);
    finish(true, null, [
        'client_id' => $client['clients_id'],
        'name' => $client['clients_name'],
        'email' => $client['clients_email'],
        'token' => $token
    ]);
} else {
    finish(false, ['message' => 'Invalid email or password']);
}
