<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "KI-Nutzung", "BREADCRUMB" => false];

if (!$AUTH->instancePermissionCheck("AI:SETTINGS") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) {
    die($TWIG->render('404.twig', $PAGEDATA));
}

echo $TWIG->render('business/ai_usage.twig', $PAGEDATA);
?>
