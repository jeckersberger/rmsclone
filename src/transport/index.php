<?php
/**
 * Transport & Logistik (J3) Controller
 *
 * Displays transport tours, vehicles, drivers, and management interface
 */

// Load framework
require_once __DIR__ . '/../common/Basics.php';
$AUTH = $_SESSION['AUTH'];

// Check permissions
if (!$AUTH->instancePermissionCheck("TRANSPORT:VIEW")) {
    redirect("$PAGEDATA[ROOTURL]/index.php?msg=nopermission");
}

$PAGEDATA['AUTH'] = $AUTH;
$PAGEDATA['USERDATA'] = $AUTH->data;

// Render template
echo $TWIG->render('transport/transport_index.twig', $PAGEDATA);
