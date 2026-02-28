<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$itemId = (int)($_POST['item_id'] ?? 0);
if ($itemId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new PackingListService($DBLIB);
$result = $svc->togglePacked($itemId, $AUTH->data['users_userid']);

finish($result);
