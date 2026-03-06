<?php
require_once __DIR__ . '/../common/head.php';

$PAGEDATA['pageTitle'] = "Datenschutzerklaerung";
$PAGEDATA['pageDescription'] = "Datenschutzerklaerung gemaess DSGVO";

echo $TWIG->render('legal/datenschutz.twig', $PAGEDATA);
