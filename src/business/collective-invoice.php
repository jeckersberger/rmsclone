<?php
require_once __DIR__ . '/../common/head.php';
$GLOBALS['AUTH']->requireLogin();
$GLOBALS['AUTH']->requirePermission('PROJECTS:PROJECT_PAYMENTS:CREATE');

$PAGEDATA['pageTitle'] = 'Sammelrechnung';
$PAGEDATA['pageDescription'] = 'Mehrere Projekte eines Kunden auf einer Rechnung zusammenfassen';
$PAGEDATA['USERDATA'] = $GLOBALS['AUTH']->data;

// Kunden laden die Projekte ohne Rechnung haben
$sql = "SELECT c.clients_id, c.clients_name, c.clients_customerNumber,
               COUNT(DISTINCT p.projects_id) AS project_count
        FROM clients c
        JOIN projects p ON p.clients_id = c.clients_id
            AND p.projects_deleted = 0
            AND p.instances_id IN (" . implode(',', array_map('intval', $GLOBALS['AUTH']->data['instance_ids'])) . ")
        LEFT JOIN document_lifecycle dl ON dl.projects_id = p.projects_id
            AND dl.doc_type = 'invoice'
            AND dl.status != 'cancelled'
        WHERE c.clients_deleted = 0
            AND dl.id IS NULL
        GROUP BY c.clients_id
        HAVING project_count >= 2
        ORDER BY c.clients_name ASC";
$PAGEDATA['clients'] = $DBLIB->rawQuery($sql) ?: [];

echo $TWIG->render('business/collective-invoice.twig', $PAGEDATA);
