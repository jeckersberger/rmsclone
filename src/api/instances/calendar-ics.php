<?php
/**
 * iCloud/Google Calendar kompatibles ICS-Feed
 *
 * Erreichbar via GET (fuer Kalender-Abonnements):
 * /api/instances/calendar-ics.php?id=INSTANCE_ID&key=CALENDAR_HASH
 *
 * Fuer iCloud: Einstellungen → Kalender → Accounte → Kalenderabo
 * URL eingeben: https://dein-server.de/api/instances/calendar-ics.php?id=X&key=HASH
 */
require_once __DIR__ . '/../apiHead.php';

// ICS Header
header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-WR-CALNAME: Projekte');

$instanceId = $_GET['id'] ?? $_POST['id'] ?? null;
$key = $_GET['key'] ?? $_POST['key'] ?? null;

if (!$instanceId || !$key) {
    die("VCALENDAR ERROR: Missing id or key");
}

// Authentifizierung via Calendar-Hash
$DBLIB->where("instances.instances_deleted", 0);
$DBLIB->where("instances.instances_id", (int)$instanceId);
$DBLIB->where("instances.instances_calendarHash", $key);
$DBLIB->where("instances.instances_calendarHash", NULL, 'IS NOT');
$instance = $DBLIB->getone("instances", ["instances.instances_id", "instances.instances_name"]);
if (!$instance) die("VCALENDAR ERROR: Invalid credentials");

$calName = $instance['instances_name'] ?? 'Projekte';

// Alle aktiven Projekte laden
$DBLIB->where("projects.instances_id", $instance['instances_id']);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
$DBLIB->join("locations", "projects.locations_id=locations.locations_id", "LEFT");
$DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
$DBLIB->join("users AS pm", "projects.projects_manager=pm.users_userid", "LEFT");
$DBLIB->where("projectsStatuses.projectsStatuses_assetsReleased", 0);
$DBLIB->orderBy("projects.projects_dates_use_start", "ASC");
$projects = $DBLIB->get("projects", null, [
    "pm.users_name1 AS pm_name1", "pm.users_name2 AS pm_name2",
    "projects.projects_id", "projects.projects_description",
    "projects.projects_dates_use_start", "projects.projects_dates_use_end",
    "projects.projects_dates_deliver_start", "projects.projects_dates_deliver_end",
    "projects.projects_name",
    "clients.clients_name",
    "projectsStatuses.projectsStatuses_name",
    "locations.locations_name", "locations.locations_address"
]) ?: [];

// ICS generieren (manuell, ohne Library fuer maximale Kompatibilitaet)
$dtz = $CONFIG['TIMEZONE'] ?? 'Europe/Berlin';
$now = gmdate('Ymd\THis\Z');

$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//MyRMS//Kalender//DE\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";
$ics .= "X-WR-CALNAME:" . icsEscape($calName) . "\r\n";
$ics .= "X-WR-TIMEZONE:{$dtz}\r\n";

// Timezone definition
$ics .= "BEGIN:VTIMEZONE\r\n";
$ics .= "TZID:{$dtz}\r\n";
$ics .= "BEGIN:DAYLIGHT\r\n";
$ics .= "DTSTART:19700329T020000\r\n";
$ics .= "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\n";
$ics .= "TZOFFSETFROM:+0100\r\n";
$ics .= "TZOFFSETTO:+0200\r\n";
$ics .= "TZNAME:CEST\r\n";
$ics .= "END:DAYLIGHT\r\n";
$ics .= "BEGIN:STANDARD\r\n";
$ics .= "DTSTART:19701025T030000\r\n";
$ics .= "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\n";
$ics .= "TZOFFSETFROM:+0200\r\n";
$ics .= "TZOFFSETTO:+0100\r\n";
$ics .= "TZNAME:CET\r\n";
$ics .= "END:STANDARD\r\n";
$ics .= "END:VTIMEZONE\r\n";

foreach ($projects as $p) {
    if (!$p['projects_dates_use_start'] || !$p['projects_dates_use_end']) continue;

    $uid = "project-{$p['projects_id']}@{$_SERVER['HTTP_HOST']}";
    $dtStart = icsDate($p['projects_dates_use_start'], $dtz);
    $dtEnd = icsDate($p['projects_dates_use_end'], $dtz);
    $summary = $p['projects_name'];
    if ($p['clients_name']) $summary .= ' (' . $p['clients_name'] . ')';

    $description = '';
    if ($p['projectsStatuses_name']) $description .= "Status: {$p['projectsStatuses_name']}\\n";
    if ($p['projects_description']) $description .= str_replace(["\r\n", "\n"], "\\n", $p['projects_description']) . "\\n";
    if ($p['pm_name1']) $description .= "Projektleiter: {$p['pm_name1']} {$p['pm_name2']}\\n";

    // Lieferzeitraum als separate Events
    $hasDelivery = ($p['projects_dates_deliver_start'] && $p['projects_dates_deliver_end']
        && $p['projects_dates_deliver_start'] !== $p['projects_dates_use_start']);

    $location = '';
    if ($p['locations_name']) $location = $p['locations_name'];
    if ($p['locations_address']) $location .= ($location ? ', ' : '') . str_replace(["\r\n", "\n"], ", ", $p['locations_address']);

    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:{$uid}\r\n";
    $ics .= "DTSTAMP:{$now}\r\n";
    $ics .= "DTSTART;TZID={$dtz}:{$dtStart}\r\n";
    $ics .= "DTEND;TZID={$dtz}:{$dtEnd}\r\n";
    $ics .= "SUMMARY:" . icsEscape($summary) . "\r\n";
    if ($description) $ics .= "DESCRIPTION:" . icsEscape($description) . "\r\n";
    if ($location) $ics .= "LOCATION:" . icsEscape($location) . "\r\n";
    $ics .= "STATUS:CONFIRMED\r\n";
    $ics .= "END:VEVENT\r\n";

    // Lieferung als separates Event
    if ($hasDelivery) {
        $uid2 = "delivery-{$p['projects_id']}@{$_SERVER['HTTP_HOST']}";
        $dtDelStart = icsDate($p['projects_dates_deliver_start'], $dtz);
        $dtDelEnd = icsDate($p['projects_dates_deliver_end'], $dtz);
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid2}\r\n";
        $ics .= "DTSTAMP:{$now}\r\n";
        $ics .= "DTSTART;TZID={$dtz}:{$dtDelStart}\r\n";
        $ics .= "DTEND;TZID={$dtz}:{$dtDelEnd}\r\n";
        $ics .= "SUMMARY:" . icsEscape("Lieferung: {$p['projects_name']}") . "\r\n";
        if ($location) $ics .= "LOCATION:" . icsEscape($location) . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
    }
}

$ics .= "END:VCALENDAR\r\n";

echo $ics;
exit;

function icsEscape(string $str): string {
    return str_replace([',', ';', '\\'], ['\\,', '\\;', '\\\\'], $str);
}

function icsDate(string $datetime, string $tz): string {
    $dt = new DateTime($datetime, new DateTimeZone($tz));
    return $dt->format('Ymd\THis');
}
