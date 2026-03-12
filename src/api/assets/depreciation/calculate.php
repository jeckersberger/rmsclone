<?php
/**
 * Depreciation Calculator API - AfA-Berechnung
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/DepreciationService.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $action = InputValidationService::string($_POST['action'] ?? 'calculate', 1, 50);

    switch ($action) {
        case 'calculate':
            $cost = InputValidationService::float($_POST['acquisition_cost'] ?? 0);
            $years = InputValidationService::positiveInt($_POST['useful_life_years'] ?? 0);
            $date = InputValidationService::date($_POST['acquisition_date'] ?? '');
            $method = InputValidationService::enum($_POST['method'] ?? 'linear', ['linear', 'degressive']);

            if ($cost <= 0) {
                finish(false, ["code" => "INVALID_COST", "message" => "Anschaffungskosten muessen positiv sein"]);
            }
            if ($years <= 0 || $years > 50) {
                finish(false, ["code" => "INVALID_YEARS", "message" => "Nutzungsdauer muss zwischen 1 und 50 Jahren liegen"]);
            }

            $service = new DepreciationService($DBLIB);
            $result = $service->calculate($cost, $years, $date, $method);
            finish(true, null, $result);
            break;

        case 'useful_life_table':
            $table = DepreciationService::getCommonUsefulLifeYears();
            finish(true, null, ['categories' => $table]);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Depreciation Calculator');
