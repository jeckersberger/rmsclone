<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT:CLIENT")) finish(false, ["code" => "PERMISSIONS"]);

$toInstanceId = filter_var($_POST['to_instance_id'] ?? 0, FILTER_VALIDATE_INT);
$projectId = filter_var($_POST['project_id'] ?? 0, FILTER_VALIDATE_INT);
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$notes = isset($_POST['notes']) ? htmlspecialchars(strip_tags(trim($_POST['notes'])), ENT_QUOTES, 'UTF-8') : null;

// JSON-Decoding mit Fehlerprüfung
$equipmentRaw = $_POST['equipment'] ?? '[]';
$equipment = json_decode($equipmentRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    finish(false, ["code" => "INVALID", "message" => "Invalid equipment JSON: " . json_last_error_msg()]);
}

// Input-Validierung
if (!$toInstanceId || $toInstanceId <= 0) finish(false, ["code" => "INVALID", "message" => "Valid to_instance_id required"]);
if (!$projectId || $projectId <= 0) finish(false, ["code" => "INVALID", "message" => "Valid project_id required"]);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) finish(false, ["code" => "INVALID", "message" => "start_date must be YYYY-MM-DD"]);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) finish(false, ["code" => "INVALID", "message" => "end_date must be YYYY-MM-DD"]);
if (strtotime($startDate) > strtotime($endDate)) finish(false, ["code" => "INVALID", "message" => "start_date must be before end_date"]);
if (empty($equipment) || !is_array($equipment)) finish(false, ["code" => "INVALID", "message" => "At least one equipment item required"]);

// Equipment-Items validieren
foreach ($equipment as $item) {
    if (!isset($item['assetTypes_id']) || !is_int($item['assetTypes_id']) || $item['assetTypes_id'] <= 0) {
        // Akzeptiere auch String-Integer
        $atId = filter_var($item['assetTypes_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$atId || $atId <= 0) finish(false, ["code" => "INVALID", "message" => "Each equipment item needs a valid assetTypes_id"]);
    }
    if (!isset($item['quantity']) || filter_var($item['quantity'], FILTER_VALIDATE_INT) === false || $item['quantity'] <= 0) {
        finish(false, ["code" => "INVALID", "message" => "Each equipment item needs a positive quantity"]);
    }
}

$svc = new PartnerService($DBLIB);
$requestId = $svc->createRequest(
    $AUTH->data['instance']['instances_id'],
    $toInstanceId,
    $projectId,
    ['equipment' => $equipment, 'notes' => $notes],
    $startDate, $endDate,
    $AUTH->data['users_userid']
);

finish(true, null, ['request_id' => $requestId]);
