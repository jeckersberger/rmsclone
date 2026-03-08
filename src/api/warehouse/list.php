<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) die("404");

$service = new WarehouseService($DBLIB);
$warehouses = $service->getWarehouses($AUTH->data['instance']['instances_id']);

finish(true, ["warehouses" => $warehouses]);
