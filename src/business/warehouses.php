<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Lagerverwaltung', 'BREADCRUMB' => true];

echo $TWIG->render('business/warehouses.twig', $PAGEDATA);
