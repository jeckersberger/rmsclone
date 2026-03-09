<?php
// CORS for embed: allow configured origins only
$allowedOrigin = rtrim(getenv('CORS_ALLOWED_ORIGIN') ?: getenv('ROOT_URL') ?: '', '/');
if ($allowedOrigin && isset($_SERVER['HTTP_ORIGIN'])) {
    $allowedOrigins = array_map('trim', explode(',', $allowedOrigin));
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    }
}
require_once __DIR__ . '/../../common/head.php';
if ($_GET['i'] == null) exit;
$DBLIB->where("instances_deleted", 0);
$DBLIB->where("instances_publicConfig", NULL, "IS NOT");
$DBLIB->where("instances_id", $_GET['i']);
$PAGEDATA['INSTANCE'] = $DBLIB->getOne("instances");
$PAGEDATA['INSTANCE']['publicData'] = json_decode($PAGEDATA['INSTANCE']['instances_publicConfig'], true);

if (!$PAGEDATA['INSTANCE'] or !isset($PAGEDATA['INSTANCE']['publicData']['enabled']) or !$PAGEDATA['INSTANCE']['publicData']['enabled']) die("Disabled by MyRMS administrator");
