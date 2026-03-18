<?php
/**
 * POST /api/portal/inquiry.php
 * Public endpoint - no auth required
 * Submit a booking inquiry (guest or logged-in client)
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, ['message' => 'POST method required']);
}

$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;
$portalSession = isset($_POST['portal_session']) ? trim($_POST['portal_session']) : null;
$rentalStart = isset($_POST['rental_start']) ? trim($_POST['rental_start']) : null;
$rentalEnd = isset($_POST['rental_end']) ? trim($_POST['rental_end']) : null;
$items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
$message = isset($_POST['message']) ? trim($_POST['message']) : null;

if (!$instanceId || !$rentalStart || !$rentalEnd || empty($items)) {
    finish(false, ['message' => 'Missing required fields']);
}

$portalService = new BookingPortalService($DBLIB);
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    finish(false, ['message' => 'Portal not available']);
}

$inquiryData = [
    'items' => $items,
    'rental_start' => $rentalStart,
    'rental_end' => $rentalEnd,
    'message' => $message
];

// Check if logged-in via portal session
$clientId = null;
if ($portalSession) {
    $session = $portalService->validatePortalSession($portalSession);
    if ($session && $session['client_id']) {
        $clientId = $session['client_id'];
        $inquiryData['client_id'] = $clientId;
    }
}

// If not logged in, require guest info
if (!$clientId) {
    $guestName = isset($_POST['guest_name']) ? trim($_POST['guest_name']) : null;
    $guestEmail = isset($_POST['guest_email']) ? trim($_POST['guest_email']) : null;

    if (!$guestName || !$guestEmail) {
        finish(false, ['message' => 'Guest name and email required']);
    }

    $inquiryData['guest_name'] = $guestName;
    $inquiryData['guest_email'] = $guestEmail;
    $inquiryData['guest_phone'] = isset($_POST['guest_phone']) ? trim($_POST['guest_phone']) : null;
    $inquiryData['guest_company'] = isset($_POST['guest_company']) ? trim($_POST['guest_company']) : null;
}

$inquiryId = $portalService->submitInquiry($inquiryData, $instanceId);

if ($inquiryId) {
    finish(true, null, ['inquiry_id' => $inquiryId]);
} else {
    finish(false, ['message' => 'Failed to create inquiry']);
}
