<?php
/**
 * Public Portal Entry Point
 * No authentication required
 * Routes to catalog, item details, login/register, booking cart
 */

require_once __DIR__ . '/../../common/head.php';

// Get instance ID from request (from subdomain or query param)
$instanceId = isset($_POST['instances_id']) ? (int) $_POST['instances_id'] : 0;
$instanceId = $instanceId ?: (isset($_GET['instances_id']) ? (int) $_GET['instances_id'] : 0);

// Try to get from subdomain or path
if (!$instanceId) {
    // Default to first instance for development
    $DBLIB->limit(1);
    $instance = $DBLIB->getOne('instances', null, ['instances_id']);
    $instanceId = $instance['instances_id'] ?? 1;
}

// Load portal service
require_once __DIR__ . '/../../services/BookingPortalService.php';
$portalService = new BookingPortalService($DBLIB);

// Get portal config
$config = $portalService->getPortalConfig($instanceId);

if (!$config || !$config['is_active']) {
    die('Portal is not available');
}

// Twig setup
$loader = new \Twig\Loader\FilesystemLoader(__DIR__);
$twig = new \Twig\Environment($loader, [
    'cache' => false,
    'debug' => getenv('DEV_MODE') == 'true'
]);

// Get route action
$action = isset($_GET['action']) ? trim($_GET['action']) : 'catalog';

// Check for portal session
$session = null;
$portalToken = $_COOKIE['portal_token'] ?? $_SESSION['portal_token'] ?? null;

if ($portalToken) {
    $session = $portalService->validatePortalSession($portalToken);
    if ($session && isset($session['client_id'])) {
        $DBLIB->where('clients_id', $session['client_id']);
        $client = $DBLIB->getOne('clients', null, ['clients_id', 'clients_name', 'clients_email']);
        if ($client) {
            $session = array_merge($session, $client);
            $session['token'] = $portalToken;
        }
    }
}

// Global template variables
$globals = [
    'config' => $config,
    'instance_id' => $instanceId,
    'session' => $session,
    'action' => $action
];

// Route handler
try {
    switch ($action) {
        case 'logout':
            setcookie('portal_token', '', time() - 3600, '/');
            unset($_SESSION['portal_token']);
            header('Location: /portal/');
            exit;

        case 'item':
            $itemId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $DBLIB->where('assetTypes_id', $itemId);
            $DBLIB->where('instances_id', $instanceId);
            $item = $DBLIB->getOne('assetTypes');

            if (!$item) {
                header('HTTP/1.0 404 Not Found');
                echo $twig->render('404.twig', $globals);
                exit;
            }

            $globals['item'] = $item;
            echo $twig->render('item.twig', $globals);
            break;

        case 'cart':
            echo $twig->render('cart.twig', $globals);
            break;

        case 'login':
        case 'register':
            $globals['action'] = $action;
            echo $twig->render('login.twig', $globals);
            break;

        case 'my-inquiries':
            if (!$session || !isset($session['client_id'])) {
                header('Location: /portal/?action=login');
                exit;
            }

            $inquiries = $portalService->getClientInquiries($session['client_id']);
            $globals['inquiries'] = $inquiries;

            // Simple template for my inquiries
            echo $twig->render('my_inquiries.twig', $globals);
            break;

        case 'thank-you':
            $inquiryId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $globals['inquiry_id'] = $inquiryId;
            echo $twig->render('thank_you.twig', $globals);
            break;

        case 'catalog':
        default:
            $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
            $search = isset($_GET['search']) ? trim($_GET['search']) : null;

            $catalog = $portalService->getPublicCatalog($instanceId, $categoryId, $search);
            $categories = $portalService->getCategories($instanceId);

            // Filter prices if not visible
            if (!$config['show_prices']) {
                foreach ($catalog as &$item) {
                    unset($item['assetTypes_dayRate']);
                    unset($item['assetTypes_weekRate']);
                }
            }

            $globals['items'] = $catalog;
            $globals['categories'] = $categories;

            echo $twig->render('catalog.twig', $globals);
            break;
    }
} catch (Exception $e) {
    if (getenv('DEV_MODE') == 'true') {
        die('Error: ' . $e->getMessage());
    }
    header('HTTP/1.0 500 Internal Server Error');
    echo '<h1>500 - Internal Server Error</h1>';
}
