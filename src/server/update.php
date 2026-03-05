<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "System-Update", "BREADCRUMB" => false];

if (!$AUTH->serverPermissionCheck("CONFIG:SET")) die($TWIG->render('404.twig', $PAGEDATA));

echo $TWIG->render('server/update.twig', $PAGEDATA);
