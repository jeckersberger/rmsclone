<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$listId = (int)($_POST['list_id'] ?? 0);
if ($listId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new PackingListService($DBLIB);
$list = $svc->getList($listId);
if (!$list) finish(false, ["code" => "NOT_FOUND"]);

finish(true, null, $list);
