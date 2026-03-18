<?php

/**
 * Sustainability Reporting & ESG Module Controller
 *
 * Main entry point for the sustainability module
 * Permissions: SUSTAINABILITY:VIEW, SUSTAINABILITY:CONFIGURE
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Permission check
if (!checkAccess('SUSTAINABILITY', 'VIEW')) {
    die('Access denied');
}

$pageTitle = 'Nachhaltigkeitsreporting & ESG';
$pageDescription = 'CO2-Tracking, Energieverbrauch und Nachhaltigkeitsberichte';

// Render Twig template
echo $twig->render('sustainability/sustainability_index.twig', [
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'CONFIG' => $CONFIG,
    'USERDATA' => $_SESSION
]);
