<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => $GLOBALS['TRANSLATOR']->t('partner_management'), 'BREADCRUMB' => true];
echo $TWIG->render('business/partners.twig', $PAGEDATA);
