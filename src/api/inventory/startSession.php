<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InventoryService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$title = $_POST['title'] ?? null;

$service = new InventoryService($DBLIB);
$sessionId = $service->startSession(
    $AUTH->data['instance']['instances_id'],
    $AUTH->data['users_userid'],
    $title
);

finish($sessionId > 0, null, ['session_id' => $sessionId]);
