<?php
/**
 * Rollenvorlagen auflisten
 *
 * GET: Alle verfuegbaren Rollenvorlagen mit Beschreibungen anzeigen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RoleTemplateService.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:ROLES_AND_PERMISSIONS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$templates = RoleTemplateService::getTemplates();

$result = [];
foreach ($templates as $key => $template) {
    $result[] = [
        'key' => $key,
        'name' => $template['name'],
        'description' => $template['description'],
        'rank' => $template['rank'],
        'permissionCount' => count($template['permissions']),
        'permissions' => $template['permissions'],
    ];
}

finish(true, null, ['templates' => $result]);
