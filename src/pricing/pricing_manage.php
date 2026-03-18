<?php
require_once __DIR__ . '/../common/headSecure.php';
require_once __DIR__ . '/../services/PricingEngineService.php';

if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
    header("Location: /src/index.php");
    exit;
}

$instanceId = $AUTH->data['instance']['instances_id'];

$PAGEDATA['pageConfig'] = [
    "TITLE" => "Flexible Preiskalkulations-Engine",
    "BREADCRUMB" => false
];

// Get all asset types
$DBLIB->where('deleted', 0);
$DBLIB->orderBy('assetTypes_name', 'ASC');
$PAGEDATA['assetTypes'] = $DBLIB->get('assetTypes') ?: [];

// Get all clients
$DBLIB->where('clients_deleted', 0);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->orderBy('clients_name', 'ASC');
$PAGEDATA['clients'] = $DBLIB->get('clients') ?: [];

echo $TWIG->render('pricing/pricing_manage.twig', $PAGEDATA);
