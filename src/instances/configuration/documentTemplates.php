<?php
require_once __DIR__ . '/../../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "Dokumentvorlagen", "BREADCRUMB" => false];

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

// Load all templates for this instance
$DBLIB->where("instances_id", $AUTH->data['instance']['instances_id']);
$DBLIB->orderBy("type", "ASC");
$DBLIB->orderBy("name", "ASC");
$PAGEDATA['templates'] = $DBLIB->get("document_templates") ?: [];

// Load the built-in default template for reference
$defaultTemplatePath = __DIR__ . '/../../templates/document_de.twig';
$PAGEDATA['defaultTemplate'] = file_exists($defaultTemplatePath) ? file_get_contents($defaultTemplatePath) : '';

echo $TWIG->render('instances/configuration/instances_configuration_documentTemplates.twig', $PAGEDATA);
?>
