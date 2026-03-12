<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$projectId = (int)($_POST['project_id'] ?? 0);
$title = trim($_POST['title'] ?? '');

if ($projectId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new PackingListService($DBLIB);
$listId = $svc->generateFromProject($instanceId, $projectId, $AUTH->data['users_userid'], $title ?: null);

if (!$listId) finish(false, ["code" => "ERROR", "message" => "Keine Assets zugewiesen"]);

$list = $svc->getList($listId, $instanceId);
finish(true, null, ["list" => $list]);
