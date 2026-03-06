<?php
require_once __DIR__ . '/../common/head.php';

$PAGEDATA['pageTitle'] = "Impressum";
$PAGEDATA['pageDescription'] = "Impressum / Anbieterkennzeichnung";

echo $TWIG->render('legal/impressum.twig', $PAGEDATA);
