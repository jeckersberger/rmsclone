<?php
/**
 * Mahnstufen speichern/aktualisieren
 *
 * POST-Parameter:
 *   levels - JSON array of dunning levels [{level, name, days_after_due, fee, interest_rate}]
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? 'save';

if ($action === 'get') {
    $svc = new DunningService($DBLIB);
    $levels = $svc->getDunningLevels($instanceId);
    finish(true, null, ['levels' => $levels]);
}

if ($action === 'save') {
    $levelsJson = $_POST['levels'] ?? '[]';
    $levels = json_decode($levelsJson, true);

    if (!is_array($levels)) {
        finish(false, ["code" => "INVALID_DATA", "message" => "Ungueltige Daten"]);
    }

    // Delete existing levels for this instance
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->delete('dunning_levels');

    // Insert new levels
    foreach ($levels as $lvl) {
        $level = (int)($lvl['level'] ?? 0);
        $name = trim($lvl['name'] ?? '');
        $daysAfterDue = max(0, (int)($lvl['days_after_due'] ?? 0));
        $fee = max(0, round((float)($lvl['fee'] ?? 0), 2));
        $interestRate = max(0, round((float)($lvl['interest_rate'] ?? 0), 2));

        if (empty($name)) continue;

        $DBLIB->insert('dunning_levels', [
            'instances_id'  => $instanceId,
            'level'         => $level,
            'name'          => $name,
            'days_after_due' => $daysAfterDue,
            'fee'           => $fee,
            'interest_rate' => $interestRate,
        ]);
    }

    finish(true, null, ['message' => 'Mahnstufen gespeichert']);
}

if ($action === 'reset_defaults') {
    // Delete existing and insert defaults
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->delete('dunning_levels');

    $defaults = [
        ['level' => 0, 'name' => 'Zahlungserinnerung', 'days_after_due' => 7, 'fee' => 0, 'interest_rate' => 0],
        ['level' => 1, 'name' => '1. Mahnung', 'days_after_due' => 21, 'fee' => 0, 'interest_rate' => 0],
        ['level' => 2, 'name' => '2. Mahnung', 'days_after_due' => 35, 'fee' => 5.00, 'interest_rate' => 0],
        ['level' => 3, 'name' => 'Letzte Mahnung', 'days_after_due' => 49, 'fee' => 10.00, 'interest_rate' => 5],
    ];

    foreach ($defaults as $d) {
        $d['instances_id'] = $instanceId;
        $DBLIB->insert('dunning_levels', $d);
    }

    finish(true, null, ['message' => 'Standardwerte wiederhergestellt']);
}

finish(false, ["code" => "INVALID_ACTION"]);
