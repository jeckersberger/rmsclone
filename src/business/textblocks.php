<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "Textbausteine", "BREADCRUMB" => false];

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$instanceId = $AUTH->data['instance']['instances_id'];

$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('deleted', 0);
$DBLIB->orderBy('category', 'ASC');
$DBLIB->orderBy('sort_order', 'ASC');
$PAGEDATA['textblocks'] = $DBLIB->get('text_blocks') ?: [];

// Group by category
$PAGEDATA['categories'] = [
    'greeting' => 'Anrede / Einleitung',
    'scope' => 'Leistungsumfang',
    'terms' => 'Bedingungen / AGB',
    'closing' => 'Schlussformel',
    'note' => 'Hinweise',
    'custom' => 'Sonstige',
];

echo $TWIG->render('business/textblocks.twig', $PAGEDATA);
