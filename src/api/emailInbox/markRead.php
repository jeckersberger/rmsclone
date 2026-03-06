<?php
/**
 * E-Mail als gelesen/ungelesen markieren
 *
 * POST-Parameter:
 *   id   - emailReceived_id
 *   read - 1 (gelesen) oder 0 (ungelesen)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$emailId = (int)($_POST['id'] ?? 0);
$isRead = (int)($_POST['read'] ?? 1);

if ($emailId <= 0) finish(false, ["code" => "INVALID"]);

$DBLIB->where('emailReceived_id', $emailId);
$DBLIB->where('instances_id', $instanceId);
$email = $DBLIB->getOne('emailReceived', ['emailReceived_id']);

if (!$email) finish(false, ["code" => "NOT_FOUND"]);

$DBLIB->where('emailReceived_id', $emailId);
$updated = $DBLIB->update('emailReceived', ['emailReceived_isRead' => $isRead ? 1 : 0]);

finish((bool)$updated);
