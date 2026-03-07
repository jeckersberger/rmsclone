<?php
/**
 * Sammelrechnung erstellen
 * POST: project_ids[] (Array von Projekt-IDs), skonto_enabled, skonto_rate, skonto_days, discount_pct
 */
require_once __DIR__ . '/../apiHeadSecure.php';
$AUTH->requirePermission('PROJECTS:PROJECT_PAYMENTS:CREATE');

$projectIds = $_POST['project_ids'] ?? [];
if (is_string($projectIds)) {
    $projectIds = json_decode($projectIds, true);
}
if (!is_array($projectIds) || count($projectIds) < 2) {
    finish(false, ["message" => "Mindestens 2 Projekt-IDs erforderlich."]);
}

$projectIds = array_map('intval', $projectIds);
$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];

$opts = [];
if (!empty($_POST['skonto_enabled'])) {
    $opts['skonto_enabled'] = true;
    $opts['skonto_rate'] = (float)($_POST['skonto_rate'] ?? 0);
    $opts['skonto_days'] = (int)($_POST['skonto_days'] ?? 0);
}
if (!empty($_POST['discount_pct'])) {
    $opts['discount_pct'] = (float)$_POST['discount_pct'];
}

require_once __DIR__ . '/../../services/CollectiveInvoiceService.php';
require_once __DIR__ . '/../../services/DocumentRenderer.php';
require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/SequenceService.php';
require_once __DIR__ . '/../../services/ProjectRepo.php';
require_once __DIR__ . '/../../services/ClientsRepo.php';
require_once __DIR__ . '/../../services/BusinessRepo.php';
require_once __DIR__ . '/../../services/ZugferdService.php';
require_once __DIR__ . '/../../services/PdfA3Converter.php';

try {
    $service = new CollectiveInvoiceService($DBLIB);
    $result = $service->create($instanceId, $projectIds, $userId, $opts);
    finish(true, false, $result);
} catch (\InvalidArgumentException $e) {
    finish(false, ["message" => $e->getMessage()]);
} catch (\RuntimeException $e) {
    finish(false, ["message" => $e->getMessage()]);
}
