<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Transportplanung', 'BREADCRUMB' => true];

echo $TWIG->render('business/transport.twig', $PAGEDATA);
