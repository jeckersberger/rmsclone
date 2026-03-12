<?php
/**
 * Standard-Rollenvorlagen fuer die aktuelle Instance anlegen
 *
 * POST-Parameter:
 *   template - (optional) Einzelne Vorlage anlegen (z.B. "admin", "buchhalter")
 *              Wenn nicht angegeben, werden alle Vorlagen angelegt
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RoleTemplateService.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:ROLES_AND_PERMISSIONS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$svc = new RoleTemplateService($DBLIB);

$templateKey = isset($_POST['template']) ? trim($_POST['template']) : null;

if ($templateKey) {
    // Einzelne Vorlage anlegen
    $templates = RoleTemplateService::getTemplates();
    if (!isset($templates[$templateKey])) {
        finish(false, ["message" => "Unbekannte Vorlage: {$templateKey}"]);
    }

    $positionId = $svc->createRole($instanceId, $templateKey);
    if ($positionId) {
        $bCMS->auditLog("CREATE", "instancePositions", "Role template '{$templateKey}' applied (ID: {$positionId})", $AUTH->data['users_userid']);
        finish(true, null, [
            'role' => [
                'id' => $positionId,
                'name' => $templates[$templateKey]['name'],
                'key' => $templateKey,
            ]
        ]);
    } else {
        finish(false, ["message" => "Rolle konnte nicht angelegt werden."]);
    }
} else {
    // Alle Vorlagen anlegen
    $result = $svc->createDefaultRoles($instanceId);
    $bCMS->auditLog("CREATE", "instancePositions", "All role templates applied: {$result['created']} created, {$result['skipped']} skipped", $AUTH->data['users_userid']);
    finish(true, null, $result);
}
