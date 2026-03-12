<?php
/**
 * Email Templates API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/EmailTemplateService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new EmailTemplateService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'list':
            finish(true, null, ['templates' => $service->list($instanceId)]);
            break;

        case 'save':
            $type = InputValidationService::string($_POST['type'] ?? '', 1, 50);
            $subject = InputValidationService::string($_POST['subject'] ?? '', 1, 200);
            $body = $_POST['body'] ?? '';
            $name = InputValidationService::string($_POST['name'] ?? '', 0, 100);
            $result = $service->save($instanceId, $type, $subject, $body, $name);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'preview':
            $type = InputValidationService::string($_POST['type'] ?? '', 1, 50);
            $variables = json_decode($_POST['variables'] ?? '{}', true) ?: [];
            $rendered = $service->render($instanceId, $type, $variables);
            finish((bool) $rendered, null, $rendered ?: []);
            break;

        case 'variables':
            $type = InputValidationService::string($_POST['type'] ?? '', 1, 50);
            finish(true, null, ['variables' => $service->getAvailableVariables($type)]);
            break;

        case 'delete':
            $id = InputValidationService::positiveInt($_POST['id'] ?? 0);
            finish($service->delete($id, $instanceId));
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Email Templates');
