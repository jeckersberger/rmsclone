<?php
require_once __DIR__ . '/../common/headSecure.php';

if (!$AUTH->instancePermissionCheck("EMAIL_OUTBOX:VIEW") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Gesendete E-Mails', 'BREADCRUMB' => true];

echo $TWIG->render('email/sent.twig', $PAGEDATA);
