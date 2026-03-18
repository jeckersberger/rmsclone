<?php
/**
 * GET /api/portal/my_inquiries.php
 * Portal endpoint - requires valid portal session
 * Returns client's own inquiries
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

$portalToken = isset($_POST['portal_session']) ? trim($_POST['portal_session']) : null;

if (!$portalToken) {
    finish(false, ['message' => 'Portal session required']);
}

$portalService = new BookingPortalService($DBLIB);
$session = $portalService->validatePortalSession($portalToken);

if (!$session || !$session['client_id']) {
    finish(false, ['message' => 'Invalid or expired session']);
}

$inquiries = $portalService->getClientInquiries($session['client_id']);

finish(true, null, ['inquiries' => $inquiries]);
