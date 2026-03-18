<?php
/**
 * MyRMS Setup Wizard - Entry Point
 * Handles initial system setup without requiring head.php
 * If setup is complete, redirects to login
 */

// Setup marker file location
$setupMarker = '/var/www/html/.setup_complete';

// Check if setup is already complete
if (file_exists($setupMarker)) {
    // Setup already done, redirect to login
    header('Location: /login/');
    exit;
}

// Setup not complete, show the wizard
require_once(__DIR__ . '/../../vendor/autoload.php');

// Initialize Twig without full head.php
$TWIGLOADER = new \Twig\Loader\FilesystemLoader([__DIR__ . '/../']);
$TWIG = new \Twig\Environment($TWIGLOADER, [
    'debug' => (getenv('DEV_MODE') === "true"),
    'auto_reload' => true,
    'charset' => 'utf-8'
]);

// Render the setup wizard
$PAGEDATA = [
    'VERSION' => '1.0.0',
    'ROOTURL' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
];

echo $TWIG->render('setup/setup_wizard.twig', $PAGEDATA);
