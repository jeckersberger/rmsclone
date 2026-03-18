<?php
/**
 * Transport Tours API - GET/POST
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
    $instanceId = $AUTH->data['instance']['instances_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get tours
        $status = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_STRING) : null;
        $dateFrom = isset($_GET['date_from']) ? filter_var($_GET['date_from'], FILTER_SANITIZE_STRING) : null;
        $dateTo = isset($_GET['date_to']) ? filter_var($_GET['date_to'], FILTER_SANITIZE_STRING) : null;

        $tours = $service->getTours($instanceId, $status, $dateFrom, $dateTo);
        finish(true, null, ['tours' => $tours]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$AUTH->instancePermissionCheck("TRANSPORT:EDIT")) {
            finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung zum Bearbeiten"]);
        }

        $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

        switch ($action) {
            case 'create':
                $name = InputValidationService::string($_POST['name'] ?? '', 1, 200);
                $date = InputValidationService::string($_POST['date'] ?? '', 10, 10); // YYYY-MM-DD
                $driverId = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
                $vehicleId = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;
                $status = isset($_POST['status']) ? InputValidationService::enum($_POST['status'], ['planned', 'loading', 'in_transit', 'delivering', 'completed', 'cancelled']) : 'planned';
                $totalDistance = isset($_POST['total_distance_km']) ? floatval($_POST['total_distance_km']) : null;
                $totalCost = isset($_POST['total_cost']) ? floatval($_POST['total_cost']) : null;
                $notes = InputValidationService::string($_POST['notes'] ?? '', 0, 5000);

                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    finish(false, ["code" => "INVALID_DATE", "message" => "Datum ungültig (YYYY-MM-DD)"]);
                }

                $stops = [];
                if (isset($_POST['stops']) && is_array($_POST['stops'])) {
                    foreach ($_POST['stops'] as $idx => $stop) {
                        $stops[] = [
                            'stop_order' => $idx + 1,
                            'type' => InputValidationService::enum($stop['type'] ?? 'delivery', ['pickup', 'delivery', 'return']),
                            'project_id' => isset($stop['project_id']) ? intval($stop['project_id']) : null,
                            'client_id' => isset($stop['client_id']) ? intval($stop['client_id']) : null,
                            'address' => $stop['address'] ?? null,
                            'time_window_start' => $stop['time_window_start'] ?? null,
                            'time_window_end' => $stop['time_window_end'] ?? null,
                        ];
                    }
                }

                $data = [
                    'instances_id' => $instanceId,
                    'name' => $name,
                    'date' => $date,
                    'driver_id' => $driverId,
                    'vehicle_id' => $vehicleId,
                    'status' => $status,
                    'total_distance_km' => $totalDistance,
                    'total_cost' => $totalCost,
                    'notes' => $notes ?: null,
                ];

                $tourId = $service->createTour($data, $stops);
                finish(true, null, ['tour_id' => $tourId, 'id' => $tourId]);
                break;

            default:
                finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
        }
    }
}, 'Transport');
