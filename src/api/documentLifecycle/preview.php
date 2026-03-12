<?php
/**
 * PDF-Vorschau: Generiert eine HTML-Vorschau eines Dokuments ohne Speicherung.
 *
 * POST-Parameter:
 *   - project_id (int, required)
 *   - doc_type (string, optional, default: 'invoice')
 *   - valid_until (string, optional, for quotes)
 *   - discount_pct (float, optional)
 *   - skonto_enabled (bool, optional)
 *   - skonto_rate (float, optional)
 *   - skonto_days (int, optional)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$projectId = intval($_POST['project_id'] ?? 0);
$docType = $_POST['doc_type'] ?? 'invoice';
if (!$projectId) finish(false, ["message" => "Projekt-ID fehlt"]);

$instanceId = $AUTH->data['instance']['instances_id'];

require_once __DIR__ . '/../../services/DocumentRenderer.php';
require_once __DIR__ . '/../../services/SequenceService.php';
require_once __DIR__ . '/../../services/repos/BusinessRepo.php';
require_once __DIR__ . '/../../services/repos/ProjectRepo.php';
require_once __DIR__ . '/../../services/repos/ClientsRepo.php';

// Build options from POST
$opts = [];
if (isset($_POST['discount_pct'])) $opts['discount_pct'] = (float)$_POST['discount_pct'];
if (isset($_POST['valid_until'])) $opts['valid_until'] = $_POST['valid_until'];
if (!empty($_POST['skonto_enabled'])) {
    $opts['skonto_enabled'] = true;
    if (isset($_POST['skonto_rate'])) $opts['skonto_rate'] = (float)$_POST['skonto_rate'];
    if (isset($_POST['skonto_days'])) $opts['skonto_days'] = (int)$_POST['skonto_days'];
}

// Generate preview without saving
$html = DocumentRenderer::renderPreview($DBLIB, $instanceId, $projectId, $docType, 'default', $opts);

header('Content-Type: text/html; charset=utf-8');
echo $html;
exit;
