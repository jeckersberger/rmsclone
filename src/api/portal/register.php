<?php
/**
 * POST /api/portal/register.php
 * Public endpoint - no auth required
 * Register as a portal client
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, ['message' => 'POST method required']);
}

$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$password = isset($_POST['password']) ? $_POST['password'] : null;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$company = isset($_POST['company']) ? trim($_POST['company']) : null;

if (!$instanceId || !$name || !$email || !$password) {
    finish(false, ['message' => 'Missing required fields']);
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish(false, ['message' => 'Invalid email format']);
}

// Validate password strength
if (strlen($password) < 8) {
    finish(false, ['message' => 'Password must be at least 8 characters']);
}

$portalService = new BookingPortalService($DBLIB);
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    finish(false, ['message' => 'Portal not available']);
}

// Check if email already exists
$DBLIB->where('clients_email', $email);
$DBLIB->where('instances_id', $instanceId);
if ($DBLIB->getOne('clients', null, ['clients_id'])) {
    finish(false, ['message' => 'Email already registered']);
}

$clientData = [
    'name' => $name,
    'email' => $email,
    'password' => $password,
    'phone' => $phone,
    'company' => $company
];

$clientId = $portalService->registerPortalClient($clientData, $instanceId);

if ($clientId) {
    // Create session token
    $token = $portalService->createPortalSession($clientId);
    finish(true, null, ['client_id' => $clientId, 'token' => $token]);
} else {
    finish(false, ['message' => 'Registration failed']);
}
