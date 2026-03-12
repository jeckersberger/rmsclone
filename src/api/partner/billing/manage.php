<?php
/**
 * Partner Billing API - Preisabstimmung, Aufträge, Abrechnung
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/PartnerBillingService.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $billingService = new PartnerBillingService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'sync_prices':
            $partnershipId = InputValidationService::positiveInt($_POST['partnership_id'] ?? 0);
            $result = $billingService->syncPrices($partnershipId, $instanceId);
            finish(true, null, $result);
            break;

        case 'create_order':
            $partnershipId = InputValidationService::positiveInt($_POST['partnership_id'] ?? 0);
            $projectId = InputValidationService::positiveInt($_POST['project_id'] ?? 0);
            $items = json_decode($_POST['items'] ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
                finish(false, ["code" => "INVALID_JSON", "message" => "Ungueltige Artikeldaten"]);
            }
            $result = $billingService->createOrder($partnershipId, $projectId, $instanceId, $items);
            finish(true, null, $result);
            break;

        case 'list_orders':
            $partnershipId = InputValidationService::positiveInt($_POST['partnership_id'] ?? 0);
            $status = InputValidationService::string($_POST['status'] ?? '', 0, 20);
            $result = $billingService->getOrders($partnershipId, $instanceId, $status ?: null);
            finish(true, null, ['orders' => $result]);
            break;

        case 'update_order_status':
            $orderId = InputValidationService::positiveInt($_POST['order_id'] ?? 0);
            $status = InputValidationService::enum($_POST['status'] ?? '', ['confirmed', 'delivered', 'returned', 'invoiced', 'cancelled']);
            $result = $billingService->updateOrderStatus($orderId, $status, $instanceId);
            finish(true, null, $result);
            break;

        case 'calculate_split':
            $orderId = InputValidationService::positiveInt($_POST['order_id'] ?? 0);
            $result = $billingService->calculateRevenueSplit($orderId, $instanceId);
            finish(true, null, $result);
            break;

        case 'availability':
            $partnershipId = InputValidationService::positiveInt($_POST['partnership_id'] ?? 0);
            $startDate = InputValidationService::date($_POST['start_date'] ?? '');
            $endDate = InputValidationService::date($_POST['end_date'] ?? '');
            $result = $billingService->getPartnerAvailability($partnershipId, $startDate, $endDate);
            finish(true, null, $result);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Partner Billing');
