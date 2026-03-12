<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InventoryService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$sessionId = intval($_POST['session_id'] ?? 0);
$tagOrBarcode = trim($_POST['tag'] ?? '');
if (!$sessionId || !$tagOrBarcode) finish(false, ["message" => "session_id and tag required"]);

$service = new InventoryService($DBLIB);
$result = $service->scanAsset($sessionId, $tagOrBarcode, $AUTH->data['users_userid'], $_POST['condition'] ?? null, $_POST['location'] ?? null);

finish($result['success'], $result['success'] ? null : ["message" => $result['error']], $result);
