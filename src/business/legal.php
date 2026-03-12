<?php
/**
 * Datenschutzerklaerung & Impressum Seite
 *
 * Zeigt die konfigurierten Rechtstexte an (Inline-HTML oder externer Link).
 * Oeffentlich zugaenglich (kein Login erforderlich).
 */
require_once __DIR__ . '/../common/head.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'privacy';

// Instance-Daten laden (oeffentliche Seite - braucht kein Login)
$instanceId = null;
if (isset($_GET['instance'])) {
    $instanceId = (int)$_GET['instance'];
} elseif (isset($AUTH) && isset($AUTH->data['instance']['instances_id'])) {
    $instanceId = $AUTH->data['instance']['instances_id'];
}

$PAGEDATA['legal_page'] = $page;
$PAGEDATA['legal_content'] = '';
$PAGEDATA['legal_title'] = '';

if ($instanceId) {
    $DBLIB->where('instances_id', $instanceId);
    $instance = $DBLIB->getOne('instances', [
        'instances_name', 'instances_privacyPolicyHtml', 'instances_imprintHtml',
        'instances_privacyPolicyUrl', 'instances_imprintUrl',
        'instances_address1', 'instances_address2', 'instances_town', 'instances_postcode',
        'instances_phone', 'instances_email',
        'instances_taxNumber', 'instances_vatId',
        'instances_ceoName', 'instances_registrationNumber', 'instances_courtOfJurisdiction',
    ]);

    if ($page === 'privacy') {
        $PAGEDATA['legal_title'] = 'Datenschutzerklaerung';
        if (!empty($instance['instances_privacyPolicyUrl'])) {
            header('Location: ' . $instance['instances_privacyPolicyUrl']);
            exit;
        }
        $PAGEDATA['legal_content'] = $instance['instances_privacyPolicyHtml'] ?? '';
    } elseif ($page === 'imprint') {
        $PAGEDATA['legal_title'] = 'Impressum';
        if (!empty($instance['instances_imprintUrl'])) {
            header('Location: ' . $instance['instances_imprintUrl']);
            exit;
        }
        $PAGEDATA['legal_content'] = $instance['instances_imprintHtml'] ?? '';

        // Auto-generiertes Impressum falls kein HTML hinterlegt
        if (empty($PAGEDATA['legal_content']) && $instance) {
            $PAGEDATA['legal_content'] = self::generateImprint($instance);
        }
    }
    $PAGEDATA['instance'] = $instance;
}

// Hilfsfunktion fuer Auto-Impressum
function generateAutoImprint($inst) {
    $name = htmlspecialchars($inst['instances_name'] ?? '');
    $addr = htmlspecialchars(($inst['instances_address1'] ?? '') . ($inst['instances_address2'] ? ', ' . $inst['instances_address2'] : ''));
    $city = htmlspecialchars(($inst['instances_postcode'] ?? '') . ' ' . ($inst['instances_town'] ?? ''));
    $phone = htmlspecialchars($inst['instances_phone'] ?? '');
    $email = htmlspecialchars($inst['instances_email'] ?? '');
    $ceo = htmlspecialchars($inst['instances_ceoName'] ?? '');
    $tax = htmlspecialchars($inst['instances_taxNumber'] ?? '');
    $vat = htmlspecialchars($inst['instances_vatId'] ?? '');
    $reg = htmlspecialchars($inst['instances_registrationNumber'] ?? '');
    $court = htmlspecialchars($inst['instances_courtOfJurisdiction'] ?? '');

    $html = "<h4>{$name}</h4>";
    if ($addr) $html .= "<p>{$addr}<br>{$city}</p>";
    if ($ceo) $html .= "<p><strong>Vertreten durch:</strong> {$ceo}</p>";
    if ($phone) $html .= "<p><strong>Telefon:</strong> {$phone}</p>";
    if ($email) $html .= "<p><strong>E-Mail:</strong> {$email}</p>";
    if ($tax) $html .= "<p><strong>Steuernummer:</strong> {$tax}</p>";
    if ($vat) $html .= "<p><strong>USt-IdNr.:</strong> {$vat}</p>";
    if ($reg) $html .= "<p><strong>Handelsregister:</strong> {$reg}</p>";
    if ($court) $html .= "<p><strong>Gerichtsstand:</strong> {$court}</p>";
    return $html;
}

if ($page === 'imprint' && empty($PAGEDATA['legal_content']) && isset($instance) && $instance) {
    $PAGEDATA['legal_content'] = generateAutoImprint($instance);
}

$PAGEDATA['pageConfig'] = ['TITLE' => $PAGEDATA['legal_title'], 'BREADCRUMB' => false];

echo $TWIG->render('business/legal.twig', $PAGEDATA);
