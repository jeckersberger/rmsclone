<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("DUNNING:VIEW") && !$AUTH->instancePermissionCheck("FINANCE:PAYMENTS_LEDGER:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Mahnwesen', 'BREADCRUMB' => true];

echo $TWIG->render('business/dunning.twig', $PAGEDATA);
