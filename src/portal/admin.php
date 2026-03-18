<?php
/**
 * Portal Admin Controller
 * Manages portal configuration and inquiries
 */

require_once __DIR__ . '/../common/head.php';

// Check permissions
if (!$AUTH->instancePermissionCheck('PORTAL:VIEW')) {
    die('Access denied');
}

// Load services
require_once __DIR__ . '/../services/BookingPortalService.php';
$portalService = new BookingPortalService($DBLIB);

$instanceId = $AUTH->data['instance']['instances_id'];

// Get current config
$config = $portalService->getPortalConfig($instanceId) ?? [];

// Handle form submission
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $AUTH->instancePermissionCheck('PORTAL:CONFIGURE')) {
    $configData = [
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'portal_title' => trim($_POST['portal_title'] ?? ''),
        'portal_description' => trim($_POST['portal_description'] ?? ''),
        'logo_path' => trim($_POST['logo_path'] ?? ''),
        'primary_color' => trim($_POST['primary_color'] ?? '#2563eb'),
        'show_prices' => isset($_POST['show_prices']) ? 1 : 0,
        'require_registration' => isset($_POST['require_registration']) ? 1 : 0,
        'require_admin_approval' => isset($_POST['require_admin_approval']) ? 1 : 0,
        'terms_html' => trim($_POST['terms_html'] ?? '')
    ];

    if ($portalService->savePortalConfig($instanceId, $configData)) {
        $message = ['type' => 'success', 'text' => 'Portal configuration saved successfully'];
        $config = $portalService->getPortalConfig($instanceId);
    } else {
        $message = ['type' => 'error', 'text' => 'Failed to save configuration'];
    }
}

// Get inquiries
$inquiries = $portalService->getInquiries($instanceId);

// Count by status
$statusCounts = [
    'new' => 0, 'reviewed' => 0, 'quoted' => 0,
    'accepted' => 0, 'rejected' => 0, 'cancelled' => 0
];
foreach ($inquiries as $inquiry) {
    $statusCounts[$inquiry['status']]++;
}

// Load twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__);
$twig = new \Twig\Environment($loader, ['cache' => false]);

$data = [
    'config' => $config,
    'inquiries' => $inquiries,
    'statusCounts' => $statusCounts,
    'message' => $message
];

echo $twig->render('portal_admin.twig', $data);
