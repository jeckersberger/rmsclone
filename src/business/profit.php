<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => $GLOBALS['TRANSLATOR']->t('profit_dashboard'), 'BREADCRUMB' => true];

echo $TWIG->render('business/profit.twig', $PAGEDATA);
