<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Bank-Import', 'BREADCRUMB' => true];

echo $TWIG->render('business/bank-import.twig', $PAGEDATA);
