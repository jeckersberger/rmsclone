<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'BWA - Betriebswirtschaftliche Auswertung', 'BREADCRUMB' => true];

echo $TWIG->render('business/bwa.twig', $PAGEDATA);
