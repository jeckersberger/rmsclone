<?php
/**
 * POST /api/portal/admin_inquiry_update.php
 * Admin endpoint - requires authentication and PORTAL:VIEW permission
 * Update inquiry status
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if (!$AUTH->instancePermissionCheck('PORTAL:VIEW')) {
    finish(false, ['message' => 'Permission denied']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, ['message' => 'POST method required']);
}

$inquiryId = isset($_POST['inquiry_id']) ? (int) $_POST['inquiry_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : null;
$projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : null;

if (!$inquiryId || !$status) {
    finish(false, ['message' => 'Missing required fields']);
}

$portalService = new BookingPortalService($DBLIB);
$inquiry = $portalService->getInquiry($inquiryId);

if (!$inquiry) {
    finish(false, ['message' => 'Inquiry not found']);
}

// Verify inquiry belongs to this instance
if ($inquiry['instances_id'] != $AUTH->data['instance']['instances_id']) {
    finish(false, ['message' => 'Inquiry not found']);
}

if ($portalService->updateInquiryStatus($inquiryId, $status, $projectId)) {
    finish(true, null, ['message' => 'Inquiry updated']);
} else {
    finish(false, ['message' => 'Failed to update inquiry']);
}
