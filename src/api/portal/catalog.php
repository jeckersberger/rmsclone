<?php
/**
 * GET /api/portal/catalog.php
 * Public endpoint - no auth required
 * Returns equipment catalog with availability and pricing
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/BookingPortalService.php';

$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;
$categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
$search = isset($_POST['search']) ? trim($_POST['search']) : null;

if (!$instanceId) {
    finish(false, ['message' => 'Instance ID required']);
}

$portalService = new BookingPortalService($DBLIB);
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    finish(false, ['message' => 'Portal not available']);
}

$catalog = $portalService->getPublicCatalog($instanceId, $categoryId, $search);

// Filter response based on pricing visibility
if (!$config['show_prices']) {
    foreach ($catalog as &$item) {
        unset($item['assetTypes_dayRate']);
        unset($item['assetTypes_weekRate']);
    }
}

finish(true, null, ['items' => $catalog]);
