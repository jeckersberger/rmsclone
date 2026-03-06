<?php
require_once __DIR__ . '/../common/headSecure.php';

if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:VIEW") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Posteingang', 'BREADCRUMB' => true];

echo $TWIG->render('email/inbox.twig', $PAGEDATA);
