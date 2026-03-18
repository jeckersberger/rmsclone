<?php
/**
 * POST /api/portal/admin_config.php
 * Admin endpoint - requires authentication and PORTAL:CONFIGURE permission
 * Save portal configuration
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

if (!$AUTH->instancePermissionCheck('PORTAL:CONFIGURE')) {
    finish(false, ['message' => 'Permission denied']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, ['message' => 'POST method required']);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$portalService = new BookingPortalService($DBLIB);

$configData = [
    'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : false,
    'portal_title' => isset($_POST['portal_title']) ? trim($_POST['portal_title']) : null,
    'portal_description' => isset($_POST['portal_description']) ? trim($_POST['portal_description']) : null,
    'logo_path' => isset($_POST['logo_path']) ? trim($_POST['logo_path']) : null,
    'primary_color' => isset($_POST['primary_color']) ? trim($_POST['primary_color']) : '#2563eb',
    'show_prices' => isset($_POST['show_prices']) ? (bool) $_POST['show_prices'] : true,
    'require_registration' => isset($_POST['require_registration']) ? (bool) $_POST['require_registration'] : true,
    'require_admin_approval' => isset($_POST['require_admin_approval']) ? (bool) $_POST['require_admin_approval'] : true,
    'terms_html' => isset($_POST['terms_html']) ? trim($_POST['terms_html']) : null
];

if ($portalService->savePortalConfig($instanceId, $configData)) {
    finish(true, null, ['message' => 'Configuration saved']);
} else {
    finish(false, ['message' => 'Failed to save configuration']);
}
