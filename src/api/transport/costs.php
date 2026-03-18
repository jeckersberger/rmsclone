<?php
/**
 * Transport Costs API - GET/POST
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportLogisticsService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("TRANSPORT:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new TransportLogisticsService($DBLIB);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get costs for a tour
        $tourId = InputValidationService::positiveInt($_GET['tour_id'] ?? 0);
        if (!$tourId) {
            finish(false, ["code" => "INVALID_PARAM", "message" => "Tour ID erforderlich"]);
        }

        $costs = $service->getTourCosts($tourId);
        finish(true, null, ['costs' => $costs]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$AUTH->instancePermissionCheck("TRANSPORT:EDIT")) {
            finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung zum Bearbeiten"]);
        }

        $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

        switch ($action) {
            case 'add':
                $tourId = InputValidationService::positiveInt($_POST['tour_id'] ?? 0);
                $costType = InputValidationService::enum($_POST['cost_type'] ?? '', ['fuel', 'toll', 'parking', 'other']);
                $amount = floatval($_POST['amount'] ?? 0);
                $description = InputValidationService::string($_POST['description'] ?? '', 0, 255);
                $receiptPath = isset($_POST['receipt_path']) ? filter_var($_POST['receipt_path'], FILTER_SANITIZE_STRING) : null;

                if (!$tourId || $amount <= 0) {
                    finish(false, ["code" => "INVALID_DATA", "message" => "Tour ID und Betrag erforderlich"]);
                }

                $data = [
                    'cost_type' => $costType,
                    'amount' => $amount,
                    'description' => $description ?: null,
                    'receipt_path' => $receiptPath,
                ];

                $costId = $service->addCost($tourId, $data);
                finish(true, null, ['cost_id' => $costId, 'id' => $costId]);
                break;

            case 'delete':
                $costId = InputValidationService::positiveInt($_POST['cost_id'] ?? 0);
                if (!$costId) {
                    finish(false, ["code" => "INVALID_PARAM", "message" => "Cost ID erforderlich"]);
                }

                $success = $service->deleteCost($costId);
                finish($success, $success ? null : ["code" => "DELETE_FAILED", "message" => "Kosten konnte nicht gelöscht werden"]);
                break;

            default:
                finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
        }
    }
}, 'Transport');
