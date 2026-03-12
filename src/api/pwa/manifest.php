<?php
/**
 * PWA Manifest API - Web App Manifest + Service Worker
 */
require_once __DIR__ . '/../../services/PwaService.php';

$action = $_GET['action'] ?? 'manifest';

switch ($action) {
    case 'manifest':
        header('Content-Type: application/manifest+json');
        $baseUrl = getenv('ROOT_URL') ?: '/';
        $appName = getenv('CONFIG_PROJECT_NAME') ?: 'MyRMS';
        echo json_encode(PwaService::getManifest($appName, $baseUrl), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;

    case 'sw':
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        echo PwaService::getServiceWorker();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
}
