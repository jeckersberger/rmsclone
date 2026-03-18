<?php
/**
 * Contract Management UI Controller
 * /contracts/
 */

require_once __DIR__ . '/../authenticate.php';
require_once __DIR__ . '/../services/ContractService.php';

// Check permissions
if (!hasPermission('CONTRACTS:VIEW')) {
    http_response_code(403);
    die('Access denied');
}

$service = new ContractService($db);

// Get data for the UI
$contracts = $service->getContracts($CurrentInstance['instances_id']);
$templates = $service->getTemplates($CurrentInstance['instances_id']);
$agbSets = $service->getAgbSets($CurrentInstance['instances_id']);
$placeholders = $service->getAvailablePlaceholders();

// Get clients for dropdown
$db->where('instances_id', $CurrentInstance['instances_id']);
$db->orderBy('clients_name', 'ASC');
$clients = $db->get('clients', null, ['id', 'clients_name']) ?: [];

// Get projects for dropdown
$db->where('instances_id', $CurrentInstance['instances_id']);
$db->orderBy('projects_name', 'ASC');
$projects = $db->get('projects', null, ['id', 'projects_name']) ?: [];

// Render template
echo $twig->render('contracts_index.twig', [
    'contracts' => $contracts,
    'templates' => $templates,
    'agbSets' => $agbSets,
    'placeholders' => $placeholders,
    'clients' => $clients,
    'projects' => $projects,
    'pageTitle' => 'Vertragsmanagement',
]);
