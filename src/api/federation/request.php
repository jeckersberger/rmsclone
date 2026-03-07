<?php
/**
 * Federation Request - Equipment-Anfrage von Partner
 *
 * POST (JSON):
 *   start_date  - Datum ab (Y-m-d)
 *   end_date    - Datum bis (Y-m-d)
 *   equipment   - Array von { assetTypes_id, quantity }
 *   notes       - optional, Bemerkung
 *   requesting_server_name - Name der anfragenden Firma
 */
require_once __DIR__ . '/federationHead.php';

$server = federationAuth();
$instanceId = $server['instances_id'];

$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$equipment = $_POST['equipment'] ?? [];
$notes = strip_tags(trim($_POST['notes'] ?? ''));
$requestingName = strip_tags(trim($_POST['requesting_server_name'] ?? $server['partner_servers_name']));

if (empty($startDate) || empty($endDate) || empty($equipment)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Missing required fields']);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Invalid date format']);
}

if (is_string($equipment)) {
    $equipment = json_decode($equipment, true);
}
if (!is_array($equipment) || empty($equipment)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Equipment must be a non-empty array']);
}
if (count($equipment) > 100) {
    finish(false, ['code' => 'INVALID', 'message' => 'Too many equipment items (max 100)']);
}

// Partner-Request in unserer DB speichern
$DBLIB->insert('partner_requests', [
    'from_instance_id' => null, // Remote - keine lokale Instance-ID
    'to_instance_id' => $instanceId,
    'projects_id' => null,
    'status' => 'pending',
    'date_start' => $startDate,
    'date_end' => $endDate,
    'notes' => (!empty($notes) ? $notes : null),
    'response_notes' => null,
    'created_by' => null,
    'partner_servers_id' => $server['partner_servers_id'] ?? null,
]);
$requestId = $DBLIB->getInsertId();

foreach ($equipment as $item) {
    if (!isset($item['assetTypes_id']) || !isset($item['quantity'])) continue;
    $DBLIB->insert('partner_request_items', [
        'partner_requests_id' => $requestId,
        'assetTypes_id' => (int)$item['assetTypes_id'],
        'quantity' => max(1, (int)$item['quantity']),
        'day_rate' => $item['day_rate'] ?? null,
    ]);
}

finish(true, null, ['request_id' => $requestId]);
