<?php
/**
 * Asset Conflict Detection API
 * Checks if assets or asset types are available for a date range
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/ConflictDetectionService.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

    $service = new ConflictDetectionService($DBLIB);
    $instanceId = $AUTH->data['instance']['instances_id'];

    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $excludeProjectId = intval($_POST['exclude_project_id'] ?? 0) ?: null;

    if (empty($startDate) || empty($endDate)) {
        finish(false, ['message' => 'start_date and end_date required']);
    }

    // Check per asset
    if (isset($_POST['asset_id'])) {
        $assetId = intval($_POST['asset_id']);
        $conflicts = $service->checkAssetConflicts($assetId, $startDate, $endDate, $excludeProjectId);
        finish(true, null, [
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    // Check per asset type
    if (isset($_POST['asset_type_id'])) {
        $assetTypeId = intval($_POST['asset_type_id']);
        $availability = $service->checkAssetTypeAvailability($assetTypeId, $instanceId, $startDate, $endDate, $excludeProjectId);
        finish(true, null, $availability);
    }

    // Bulk check
    if (isset($_POST['asset_ids'])) {
        $assetIds = is_array($_POST['asset_ids']) ? $_POST['asset_ids'] : json_decode($_POST['asset_ids'], true);
        if (!is_array($assetIds)) finish(false, ['message' => 'asset_ids must be an array']);
        $conflicts = $service->checkBulkConflicts($assetIds, $startDate, $endDate, $excludeProjectId);
        finish(true, null, [
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    finish(false, ['message' => 'asset_id, asset_type_id, or asset_ids required']);
}, 'Conflict Check');
