<?php
/**
 * DSGVO Art. 20 - Recht auf Datenportabilität
 * Exportiert alle persönlichen Daten des eingeloggten Benutzers als JSON.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

$userId = $AUTH->data['users_userid'];

// Benutzerdaten (ohne Passwort-Hash und Sicherheitsfelder)
$DBLIB->where("users_userid", $userId);
$user = $DBLIB->getOne("users", [
    "users_userid", "users_username", "users_name1", "users_name2",
    "users_email", "users_emailVerified", "users_created",
    "users_social_facebook", "users_social_instagram", "users_social_linkedin",
    "users_social_snapchat", "users_social_twitter", "users_notes"
]);

if (!$user) finish(false, ["code" => "NOT-FOUND", "message" => "User not found"]);

// Instanz-Mitgliedschaften
$DBLIB->where("users_userid", $userId);
$DBLIB->join("instances", "userInstances.instances_id=instances.instances_id", "LEFT");
$instanceMemberships = $DBLIB->get("userInstances", null, [
    "instances.instances_name", "userInstances.userInstances_label",
    "userInstances.userInstances_started"
]);

// Crew-Zuweisungen
$DBLIB->where("users_userid", $userId);
$DBLIB->join("projects", "crewAssignments.projects_id=projects.projects_id", "LEFT");
$crewAssignments = $DBLIB->get("crewAssignments", null, [
    "crewAssignments.crewAssignments_role", "crewAssignments.crewAssignments_comment",
    "projects.projects_name", "crewAssignments.crewAssignments_timestamp"
]);

// Audit-Log (eigene Aktionen)
$DBLIB->where("users_userid", $userId);
$DBLIB->orderBy("auditLog_timestamp", "DESC");
$auditLog = $DBLIB->get("auditLog", 500, [
    "auditLog_actionType", "auditLog_actionTable", "auditLog_timestamp"
]);

// Login-Versuche
$DBLIB->where("loginAttempts_userId", $userId);
$DBLIB->orderBy("loginAttempts_timestamp", "DESC");
$loginAttempts = $DBLIB->get("loginAttempts", 100, [
    "loginAttempts_timestamp", "loginAttempts_ip", "loginAttempts_result"
]);

$export = [
    'export_date'     => date('Y-m-d H:i:s'),
    'export_type'     => 'DSGVO Art. 20 - Datenportabilität',
    'personal_data'   => $user,
    'memberships'     => $instanceMemberships,
    'crew_assignments' => $crewAssignments,
    'audit_log'       => $auditLog,
    'login_attempts'  => $loginAttempts,
];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="daten_export_' . $userId . '_' . date('Y-m-d') . '.json"');
echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
