<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Cloud-Buchhaltung Export', 'BREADCRUMB' => true];

echo $TWIG->render('business/cloud-accounting.twig', $PAGEDATA);
