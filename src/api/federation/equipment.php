<?php
/**
 * Federation Equipment - Verfuegbares Equipment auflisten
 *
 * Wird von einem Partner-Server aufgerufen um unser Equipment zu sehen.
 *
 * POST (JSON):
 *   start_date - optional, Verfuegbarkeit ab (Y-m-d)
 *   end_date   - optional, Verfuegbarkeit bis (Y-m-d)
 *   search     - optional, Suchbegriff
 */
require_once __DIR__ . '/federationHead.php';

$server = federationAuth();
$instanceId = $server['instances_id'];

$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;
$search = $_POST['search'] ?? null;

// Datums-Validierung
if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = null;
if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = null;
if ($search) $search = substr(trim($search), 0, 100);

$equipment = $FEDERATION->getLocalEquipment($instanceId, $startDate, $endDate, $search);

finish(true, null, ['equipment' => $equipment]);
