<?php
/**
 * Dashboard Widget Config API
 *
 * POST actions:
 *   get   – return current widget configuration for the authenticated user
 *   save  – persist widget order / visibility / size
 *   reset – delete saved config and return defaults
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DashboardWidgetService.php';

$userId     = $AUTH->data['users_userid'];
$instanceId = (int) $AUTH->data['instance']['instances_id'];
$action     = $_POST['action'] ?? '';

$service = new DashboardWidgetService($DBLIB);

switch ($action) {
    case 'get':
        $config = $service->getWidgetConfig($userId, $instanceId);
        finish(true, null, ['widgets' => $config]);
        break;

    case 'save':
        $widgetsRaw = $_POST['widgets'] ?? '[]';
        $widgets = is_array($widgetsRaw) ? $widgetsRaw : json_decode($widgetsRaw, true);

        if (!is_array($widgets)) {
            finish(false, 'Invalid widgets data');
        }

        $result = $service->saveWidgetConfig($userId, $instanceId, $widgets);
        $config = $service->getWidgetConfig($userId, $instanceId);
        finish($result, $result ? null : 'Failed to save widget config', ['widgets' => $config]);
        break;

    case 'reset':
        $service->resetWidgetConfig($userId, $instanceId);
        $config = $service->getDefaultWidgets();
        finish(true, null, ['widgets' => $config]);
        break;

    default:
        finish(false, 'Invalid action. Use: get, save, reset');
}
