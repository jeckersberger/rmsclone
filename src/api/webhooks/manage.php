<?php
/**
 * Webhook Management API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WebhookService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new WebhookService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'list':
            finish(true, null, ['webhooks' => $service->list($instanceId)]);
            break;

        case 'register':
            $url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
            $events = json_decode($_POST['events'] ?? '[]', true) ?: [];
            $name = InputValidationService::string($_POST['name'] ?? '', 0, 100);
            if (!$url) finish(false, ["code" => "INVALID_URL", "message" => "Ungueltige URL"]);
            $result = $service->register($instanceId, $url, $events, $name);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'delete':
            $id = InputValidationService::positiveInt($_POST['id'] ?? 0);
            finish($service->delete($id, $instanceId));
            break;

        case 'log':
            $id = InputValidationService::positiveInt($_POST['webhook_id'] ?? 0);
            finish(true, null, ['deliveries' => $service->getDeliveryLog($id)]);
            break;

        case 'events':
            finish(true, null, ['events' => WebhookService::VALID_EVENTS]);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Webhooks');
