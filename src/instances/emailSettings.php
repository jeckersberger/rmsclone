<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "E-Mail Postfach", "BREADCRUMB" => false];

if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:SETTINGS") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) die($TWIG->render('404.twig', $PAGEDATA));

echo $TWIG->render('instances/instances_emailSettings.twig', $PAGEDATA);
