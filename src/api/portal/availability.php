<?php
/**
 * GET /api/portal/availability.php
 * Public endpoint - no auth required
 * Check availability for specific asset type and date range
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;
$assetTypeId = isset($_POST['asset_type_id']) ? (int) $_POST['asset_type_id'] : 0;
$startDate = isset($_POST['start_date']) ? trim($_POST['start_date']) : null;
$endDate = isset($_POST['end_date']) ? trim($_POST['end_date']) : null;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;

if (!$instanceId || !$assetTypeId || !$startDate || !$endDate) {
    finish(false, ['message' => 'Missing required parameters']);
}

// Validate dates
if (!strtotime($startDate) || !strtotime($endDate)) {
    finish(false, ['message' => 'Invalid date format']);
}

if ($startDate >= $endDate) {
    finish(false, ['message' => 'Start date must be before end date']);
}

$portalService = new BookingPortalService($DBLIB);
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    finish(false, ['message' => 'Portal not available']);
}

$availability = $portalService->checkAvailability($assetTypeId, $startDate, $endDate, $quantity, $instanceId);

finish(true, null, $availability);
