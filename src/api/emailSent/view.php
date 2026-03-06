<?php
/**
 * Einzelne gesendete E-Mail anzeigen (inkl. HTML-Body)
 *
 * GET-Parameter:
 *   id - emailSent_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_OUTBOX:VIEW") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$emailId = (int)($_GET['id'] ?? 0);

if ($emailId <= 0) finish(false, ["code" => "INVALID"]);

// Benutzer dieser Instanz ermitteln
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('userInstances_deleted', 0);
$instanceUsers = $DBLIB->get('userInstances', null, ['users_userid']);
$userIds = array_column($instanceUsers ?: [], 'users_userid');

if (empty($userIds)) finish(false, ["code" => "NOT_FOUND"]);

$DBLIB->where('emailSent_id', $emailId);
$DBLIB->where('users_userid', $userIds, 'IN');
$email = $DBLIB->getOne('emailSent');

if (!$email) finish(false, ["code" => "NOT_FOUND"]);

finish(true, null, ['email' => $email]);
