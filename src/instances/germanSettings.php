<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "Geschäftsdaten & Steuern", "BREADCRUMB" => false];

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

echo $TWIG->render('instances/instances_germanSettings.twig', $PAGEDATA);
