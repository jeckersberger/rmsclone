<?php
/**
 * GET /api/portal/config.php
 * Public endpoint - no auth required
 * Returns portal configuration (title, colors, branding)
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;

if (!$instanceId) {
    finish(false, ['message' => 'Instance ID required']);
}

$portalService = new BookingPortalService($DBLIB);
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    finish(false, ['message' => 'Portal not available']);
}

// Return only public data
$response = [
    'portal_title' => $config['portal_title'],
    'portal_description' => $config['portal_description'],
    'logo_path' => $config['logo_path'],
    'primary_color' => $config['primary_color'],
    'show_prices' => (bool) $config['show_prices'],
    'require_registration' => (bool) $config['require_registration'],
    'require_admin_approval' => (bool) $config['require_admin_approval']
];

finish(true, null, $response);
