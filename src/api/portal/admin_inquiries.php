<?php
/**
 * GET /api/portal/admin_inquiries.php
 * Admin endpoint - requires authentication and PORTAL:VIEW permission
 * List all inquiries
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if (!$AUTH->instancePermissionCheck('PORTAL:VIEW')) {
    finish(false, ['message' => 'Permission denied']);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$status = isset($_POST['status']) ? trim($_POST['status']) : null;

$portalService = new BookingPortalService($DBLIB);
$inquiries = $portalService->getInquiries($instanceId, $status);

finish(true, null, ['inquiries' => $inquiries]);
