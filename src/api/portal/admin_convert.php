<?php
/**
 * POST /api/portal/admin_convert.php
 * Admin endpoint - requires authentication and PORTAL:CONFIGURE permission
 * Convert inquiry to project
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if (!$AUTH->instancePermissionCheck('PORTAL:CONFIGURE')) {
    finish(false, ['message' => 'Permission denied']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, ['message' => 'POST method required']);
}

$inquiryId = isset($_POST['inquiry_id']) ? (int) $_POST['inquiry_id'] : 0;

if (!$inquiryId) {
    finish(false, ['message' => 'Inquiry ID required']);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['user']['users_id'];

$portalService = new BookingPortalService($DBLIB);
$inquiry = $portalService->getInquiry($inquiryId);

if (!$inquiry) {
    finish(false, ['message' => 'Inquiry not found']);
}

// Verify inquiry belongs to this instance
if ($inquiry['instances_id'] != $instanceId) {
    finish(false, ['message' => 'Inquiry not found']);
}

$projectId = $portalService->convertToProject($inquiryId, $userId, $instanceId);

if ($projectId) {
    finish(true, null, ['project_id' => $projectId]);
} else {
    finish(false, ['message' => 'Failed to convert inquiry to project']);
}
