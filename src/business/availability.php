<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => $GLOBALS['TRANSLATOR']->t('availability_calendar'), 'BREADCRUMB' => true];

echo $TWIG->render('business/availability.twig', $PAGEDATA);
