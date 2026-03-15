<?php
/**
 * Stock Warnings and Availability API
 *
 * Provides availability checking and warning generation for asset management.
 * Identifies conflicts, low stock, maintenance issues, and overdue returns.
 *
 * GET Parameters:
 *   action - Action to perform (all, counts, availability, conflicts, double_bookings, overdue, low_stock, maintenance)
 *   asset_type_id - For availability check
 *   from - From date (Y-m-d) for availability check
 *   to - To date (Y-m-d) for availability check
 *   quantity - Quantity needed (optional, default 1)
 *   project_id - For project conflict check
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/StockWarningService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    // Check permissions
    if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $instanceId = (int)$AUTH->data['instance']['instances_id'];
    $action = InputValidationService::string($_GET['action'] ?? 'all', 1, 50);

    $service = new StockWarningService($DBLIB);

    switch ($action) {
        case 'all':
            // Generate all warnings
            $warnings = $service->generateWarnings($instanceId);
            finish(true, null, ['warnings' => $warnings]);
            break;

        case 'counts':
            // Get warning counts for dashboard badge
            $counts = $service->getWarningCounts($instanceId);
            finish(true, null, ['counts' => $counts]);
            break;

        case 'availability':
            // Check availability for a specific asset type and date range
            $assetTypeId = InputValidationService::positiveInt($_GET['asset_type_id'] ?? 0);
            $from = InputValidationService::date($_GET['from'] ?? '');
            $to = InputValidationService::date($_GET['to'] ?? '');
            $quantity = InputValidationService::positiveInt($_GET['quantity'] ?? 1);

            if ($assetTypeId <= 0 || empty($from) || empty($to)) {
                finish(false, ['code' => 'INVALID_PARAMS', 'message' => 'asset_type_id, from, and to are required']);
            }

            if (strtotime($from) > strtotime($to)) {
                finish(false, ['code' => 'INVALID_RANGE', 'message' => 'from date must be before to date']);
            }

            $availability = $service->checkAvailability($instanceId, $assetTypeId, $from, $to, $quantity);
            finish(true, null, ['availability' => $availability]);
            break;

        case 'conflicts':
            // Check conflicts for a specific project
            $projectId = InputValidationService::positiveInt($_GET['project_id'] ?? 0);

            if ($projectId <= 0) {
                finish(false, ['code' => 'INVALID_PARAMS', 'message' => 'project_id is required']);
            }

            $conflicts = $service->checkProjectConflicts($instanceId, $projectId);
            finish(true, null, ['conflicts' => $conflicts]);
            break;

        case 'double_bookings':
            // Get all double-booked assets
            $doubleBookings = $service->getDoubleBookings($instanceId);
            finish(true, null, [
                'double_bookings' => $doubleBookings,
                'count' => count($doubleBookings)
            ]);
            break;

        case 'overdue':
            // Get overdue returns
            $overdueReturns = $service->getOverdueReturns($instanceId);
            finish(true, null, [
                'overdue_returns' => $overdueReturns,
                'count' => count($overdueReturns)
            ]);
            break;

        case 'low_stock':
            // Get low stock types
            $threshold = isset($_GET['threshold']) ? (float)$_GET['threshold'] : 0.8;
            $threshold = max(0.0, min(1.0, $threshold)); // Clamp between 0 and 1
            $lowStock = $service->getLowStockTypes($instanceId, $threshold);
            finish(true, null, [
                'low_stock_types' => $lowStock,
                'count' => count($lowStock),
                'threshold' => $threshold * 100
            ]);
            break;

        case 'maintenance':
            // Get maintenance due
            $maintenanceDue = $service->getMaintenanceDue($instanceId);
            finish(true, null, [
                'maintenance_due' => $maintenanceDue,
                'count' => count($maintenanceDue)
            ]);
            break;

        default:
            finish(false, ['code' => 'UNKNOWN_ACTION', 'message' => 'Unknown action: ' . $action]);
    }
}, 'Stock Warnings API');
