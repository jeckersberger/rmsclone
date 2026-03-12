<?php
/**
 * Schlussrechnung erstellen (verrechnet alle Abschlagsrechnungen).
 *
 * POST:
 *   project_id  - Projekt-ID
 *   payment_term_days - (optional) Zahlungsziel in Tagen (default 14)
 *   skonto_enabled - (optional) Skonto aktivieren
 *   skonto_rate    - (optional) Skonto-Satz in Prozent
 *   skonto_days    - (optional) Skonto-Frist in Tagen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];
$projectId = (int)($_POST['project_id'] ?? 0);

if ($projectId <= 0) finish(false, ["message" => "project_id erforderlich."]);

$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
if (!$DBLIB->getOne('projects')) {
    finish(false, ["message" => "Projekt nicht gefunden."]);
}

require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/DocumentRenderer.php';

$service = new DocumentLifecycleService($DBLIB);

$opts = [];
if (!empty($_POST['payment_term_days'])) $opts['payment_term_days'] = (int)$_POST['payment_term_days'];
if (!empty($_POST['skonto_enabled'])) $opts['skonto_enabled'] = true;
if (!empty($_POST['skonto_rate'])) $opts['skonto_rate'] = (float)$_POST['skonto_rate'];
if (!empty($_POST['skonto_days'])) $opts['skonto_days'] = (int)$_POST['skonto_days'];

try {
    $result = $service->createFinalInvoice($instanceId, $projectId, $userId, $opts);
    finish(true, null, $result);
} catch (\Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
