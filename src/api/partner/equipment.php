<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;
$search = isset($_POST['search']) ? trim($_POST['search']) : null;

// Datumsformat validieren (YYYY-MM-DD)
if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    finish(false, ["code" => "INVALID", "message" => "start_date must be YYYY-MM-DD"]);
}
if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    finish(false, ["code" => "INVALID", "message" => "end_date must be YYYY-MM-DD"]);
}
if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
    finish(false, ["code" => "INVALID", "message" => "start_date must be before end_date"]);
}

// Suchbegriff sanitieren
if ($search) {
    $search = htmlspecialchars(strip_tags($search), ENT_QUOTES, 'UTF-8');
    if (strlen($search) > 100) $search = substr($search, 0, 100);
}

$svc = new PartnerService($DBLIB);
$equipment = $svc->getPartnerEquipment($AUTH->data['instance']['instances_id'], $startDate, $endDate, $search);

finish(true, null, ['equipment' => $equipment]);
