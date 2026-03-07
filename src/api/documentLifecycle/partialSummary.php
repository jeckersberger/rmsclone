<?php
/**
 * Uebersicht der Abschlagsrechnungen fuer ein Projekt.
 *
 * GET/POST:
 *   project_id  - Projekt-ID
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$projectId = (int)($_REQUEST['project_id'] ?? 0);

if ($projectId <= 0) finish(false, ["message" => "project_id erforderlich."]);

require_once __DIR__ . '/../../services/DocumentLifecycleService.php';

$service = new DocumentLifecycleService($DBLIB);
$summary = $service->getPartialInvoiceSummary($instanceId, $projectId);

finish(true, null, $summary);
