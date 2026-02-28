<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$listId = (int)($_POST['list_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($listId <= 0 || !in_array($status, ['draft', 'ready', 'packed', 'loaded', 'returned'])) {
    finish(false, ["code" => "INVALID"]);
}

$svc = new PackingListService($DBLIB);
$svc->updateStatus($listId, $status);

finish(true);
