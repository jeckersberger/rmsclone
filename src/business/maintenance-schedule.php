<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("MAINTENANCE_JOBS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => $GLOBALS['TRANSLATOR']->t('maintenance_schedule'), 'BREADCRUMB' => true];

echo $TWIG->render('business/maintenance-schedule.twig', $PAGEDATA);
