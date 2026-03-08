<?php
/**
 * Calendar Feed API - ICS Export + Feed-Token
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/CalendarSyncService.php';

$calService = new CalendarSyncService($DBLIB);

// Token-basierter Zugriff (fuer Kalender-Apps)
if (isset($_GET['token'])) {
    $tokenData = $calService->validateFeedToken($_GET['token']);
    if (!$tokenData) {
        http_response_code(403);
        die('Invalid token');
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="adamrms.ics"');
    echo $calService->generateIcs($tokenData['instances_id']);
    exit;
}

// Authenticated access
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH, $calService) {
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'generate_token':
            $result = $calService->generateFeedToken($AUTH->data['users_userid'], $instanceId);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'export':
            $from = $_POST['from'] ?? null;
            $to = $_POST['to'] ?? null;
            $ics = $calService->generateIcs($instanceId, $from, $to);
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="projekte.ics"');
            echo $ics;
            exit;

        case 'project_ics':
            $projectId = InputValidationService::positiveInt($_POST['project_id'] ?? 0);
            $ics = $calService->generateProjectIcs($projectId);
            if (!$ics) finish(false, ["code" => "NOT_FOUND", "message" => "Projekt nicht gefunden"]);
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="projekt.ics"');
            echo $ics;
            exit;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Calendar');
