<?php
/**
 * GET /api/damage/by_asset.php
 * Get damage history for specific asset
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$assetId = intval($_POST['asset_id'] ?? 0);
if (!$assetId) {
    finish(false, ["message" => "asset_id required"]);
}

// Verify asset belongs to instance
$DBLIB->where('assets_id', $assetId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('assets', ['assets_id'])) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);
$workflows = $service->getWorkflowsByAsset($assetId);

finish(true, null, ['workflows' => $workflows]);
