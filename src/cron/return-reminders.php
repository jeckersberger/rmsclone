<?php
/**
 * Cron-Job: Rueckgabe-Erinnerungen per E-Mail
 *
 * Aufruf: php src/cron/return-reminders.php
 * Empfohlener Crontab-Eintrag: 0 8 * * * php /path/to/src/cron/return-reminders.php >> /var/log/myrms-cron.log 2>&1
 *
 * Sendet E-Mail-Erinnerungen an:
 *   1. Projektleiter: 1 Tag vor Rueckgabeende
 *   2. Projektleiter: Am Tag der Rueckgabe
 *   3. Projektleiter + Kunde: 1 Tag nach Rueckgabeende (ueberfaellig)
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../api/notifications/email/email.php';

echo "[" . date('Y-m-d H:i:s') . "] Return reminders cron started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalSent = 0;
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

foreach ($instances as $inst) {
    $instanceId = $inst['instances_id'];

    // 1. Projects with return date = tomorrow (1-day warning)
    $DBLIB->where('p.instances_id', $instanceId);
    $DBLIB->where('p.projects_deleted', 0);
    $DBLIB->where('p.projects_archived', 0);
    $DBLIB->where("DATE(p.projects_dates_deliver_end) = '{$tomorrow}'");
    $DBLIB->join('users u', 'p.projects_manager=u.users_userid', 'LEFT');
    $DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
    $projects = $DBLIB->get('projects p', null, [
        'p.projects_id', 'p.projects_name', 'p.projects_dates_deliver_end',
        'u.users_email', 'u.users_name1',
        'c.clients_name', 'c.clients_email'
    ]) ?: [];

    foreach ($projects as $proj) {
        if (empty($proj['users_email'])) continue;

        $returnDate = date('d.m.Y H:i', strtotime($proj['projects_dates_deliver_end']));
        $emailHtml = "<p>Erinnerung: Das Projekt <strong>{$proj['projects_name']}</strong>"
            . ($proj['clients_name'] ? " ({$proj['clients_name']})" : '')
            . " hat morgen ({$returnDate}) Rueckgabe-Termin.</p>"
            . "<p>Bitte stellen Sie sicher, dass alle Geraete rechtzeitig zurueckgegeben werden.</p>";

        @sendEmail(
            ['userData' => ['users_email' => $proj['users_email'], 'users_name1' => $proj['users_name1'], 'users_name2' => '']],
            $instanceId,
            "Rueckgabe morgen: {$proj['projects_name']}",
            $emailHtml
        );
        $totalSent++;
        echo "  [>] {$inst['instances_name']}: Vorab-Erinnerung fuer {$proj['projects_name']} -> {$proj['users_email']}\n";
    }

    // 2. Projects with return date = today
    $DBLIB->where('p.instances_id', $instanceId);
    $DBLIB->where('p.projects_deleted', 0);
    $DBLIB->where('p.projects_archived', 0);
    $DBLIB->where("DATE(p.projects_dates_deliver_end) = '{$today}'");
    $DBLIB->join('users u', 'p.projects_manager=u.users_userid', 'LEFT');
    $DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
    $todayProjects = $DBLIB->get('projects p', null, [
        'p.projects_id', 'p.projects_name', 'p.projects_dates_deliver_end',
        'u.users_email', 'u.users_name1',
        'c.clients_name', 'c.clients_email'
    ]) ?: [];

    foreach ($todayProjects as $proj) {
        if (empty($proj['users_email'])) continue;

        $returnDate = date('H:i', strtotime($proj['projects_dates_deliver_end']));
        $emailHtml = "<p><strong>Heute Rueckgabe:</strong> Projekt <strong>{$proj['projects_name']}</strong>"
            . ($proj['clients_name'] ? " ({$proj['clients_name']})" : '')
            . " hat heute um {$returnDate} Uhr Rueckgabe-Termin.</p>";

        @sendEmail(
            ['userData' => ['users_email' => $proj['users_email'], 'users_name1' => $proj['users_name1'], 'users_name2' => '']],
            $instanceId,
            "Rueckgabe HEUTE: {$proj['projects_name']}",
            $emailHtml
        );
        $totalSent++;
        echo "  [!] {$inst['instances_name']}: Heute-Erinnerung fuer {$proj['projects_name']} -> {$proj['users_email']}\n";
    }

    // 3. Projects overdue since yesterday (not archived = still active)
    $DBLIB->where('p.instances_id', $instanceId);
    $DBLIB->where('p.projects_deleted', 0);
    $DBLIB->where('p.projects_archived', 0);
    $DBLIB->where("DATE(p.projects_dates_deliver_end) = '{$yesterday}'");
    $DBLIB->join('users u', 'p.projects_manager=u.users_userid', 'LEFT');
    $DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
    $overdueProjects = $DBLIB->get('projects p', null, [
        'p.projects_id', 'p.projects_name', 'p.projects_dates_deliver_end',
        'u.users_email', 'u.users_name1',
        'c.clients_name', 'c.clients_email'
    ]) ?: [];

    foreach ($overdueProjects as $proj) {
        if (empty($proj['users_email'])) continue;

        $returnDate = date('d.m.Y', strtotime($proj['projects_dates_deliver_end']));
        $emailHtml = "<p><strong style=\"color:red;\">UEBERFAELLIG:</strong> Projekt <strong>{$proj['projects_name']}</strong>"
            . ($proj['clients_name'] ? " ({$proj['clients_name']})" : '')
            . " haette gestern ({$returnDate}) zurueckgegeben werden sollen.</p>"
            . "<p>Bitte ueberpruefen Sie den Status der Rueckgabe und kontaktieren Sie ggf. den Kunden.</p>";

        @sendEmail(
            ['userData' => ['users_email' => $proj['users_email'], 'users_name1' => $proj['users_name1'], 'users_name2' => '']],
            $instanceId,
            "UEBERFAELLIG: Rueckgabe {$proj['projects_name']}",
            $emailHtml
        );
        $totalSent++;
        echo "  [!!] {$inst['instances_name']}: Ueberfaellig-Meldung fuer {$proj['projects_name']} -> {$proj['users_email']}\n";

        // Also notify client if email available
        if (!empty($proj['clients_email'])) {
            $clientEmailHtml = "<p>Sehr geehrte/r {$proj['clients_name']},</p>"
                . "<p>fuer Projekt <strong>{$proj['projects_name']}</strong> war die Rueckgabe fuer den {$returnDate} geplant.</p>"
                . "<p>Bitte setzen Sie sich mit uns in Verbindung, falls die Geraete noch nicht zurueckgegeben wurden.</p>"
                . "<p>Mit freundlichen Gruessen<br>{$inst['instances_name']}</p>";

            @sendEmail(
                ['userData' => ['users_email' => $proj['clients_email'], 'users_name1' => $proj['clients_name'], 'users_name2' => '']],
                $instanceId,
                "Rueckgabe ueberfaellig - {$proj['projects_name']}",
                $clientEmailHtml
            );
            $totalSent++;
        }
    }

    // Log cron run
    if (count($projects) + count($todayProjects) + count($overdueProjects) > 0) {
        $DBLIB->insert('cron_runs', [
            'instances_id' => $instanceId,
            'job_type' => 'return_reminders',
            'status' => 'success',
            'items_processed' => count($projects) + count($todayProjects) + count($overdueProjects),
            'details_json' => json_encode([
                'tomorrow' => count($projects),
                'today' => count($todayProjects),
                'overdue' => count($overdueProjects),
            ]),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Sent {$totalSent} reminder emails.\n";
