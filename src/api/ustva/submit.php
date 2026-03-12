<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$reportId = (int)($_POST['report_id'] ?? 0);
$elsterReference = trim($_POST['elster_reference'] ?? '');

if ($reportId <= 0) finish(false, ["code" => "INVALID", "message" => "Fehlende Berichts-ID"]);
if (empty($elsterReference)) finish(false, ["code" => "INVALID", "message" => "ELSTER-Referenznummer ist erforderlich"]);

// Sicherstellen, dass der Bericht zur aktuellen Instanz gehoert
$svc = new UstvaService($DBLIB);
$DBLIB->where('id', $reportId);
$DBLIB->where('instances_id', $instanceId);
$report = $DBLIB->getOne('ustva_reports');

if (!$report) finish(false, ["code" => "NOT_FOUND", "message" => "Bericht nicht gefunden"]);
if ($report['status'] === 'submitted') finish(false, ["code" => "ALREADY_SUBMITTED", "message" => "Bericht wurde bereits als uebermittelt markiert"]);

$svc->markSubmitted($reportId, $elsterReference);

finish(true, null, ["message" => "Bericht als uebermittelt markiert"]);
