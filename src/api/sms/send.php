<?php
/**
 * SMS/WhatsApp Notification API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/SmsNotificationService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new SmsNotificationService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

    switch ($action) {
        case 'send_sms':
            $to = InputValidationService::string($_POST['to'] ?? '', 5, 20);
            $message = InputValidationService::string($_POST['message'] ?? '', 1, 500);
            $result = $service->sendSms($to, $message);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'send_whatsapp':
            $to = InputValidationService::string($_POST['to'] ?? '', 5, 20);
            $message = InputValidationService::string($_POST['message'] ?? '', 1, 500);
            $result = $service->sendWhatsApp($to, $message);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'project_reminder':
            $projectId = InputValidationService::positiveInt($_POST['project_id'] ?? 0);
            $channel = InputValidationService::enum($_POST['channel'] ?? 'sms', ['sms', 'whatsapp']);
            $result = $service->sendProjectReminder($projectId, $channel);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'SMS/WhatsApp');
