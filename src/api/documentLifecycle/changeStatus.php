<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:EDIT") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$docId = (int)($_POST['doc_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if ($docId <= 0 || !$newStatus) finish(false, ["code" => "INVALID", "message" => "Missing parameters"]);

$svc = new DocumentLifecycleService($DBLIB);
$result = $svc->changeStatus($docId, $newStatus, $AUTH->data['users_userid'], $comment ?: null, $AUTH->data['instance']['instances_id']);

if (!$result) finish(false, ["code" => "INVALID", "message" => "Status change not allowed"]);

finish(true);
