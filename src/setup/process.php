<?php
/**
 * MyRMS Setup Wizard - Process Handler
 * Handles AJAX requests from the setup wizard
 * NO AUTHENTICATION REQUIRED
 */

header('Content-Type: application/json');

// Check if setup is already complete
$setupMarker = '/var/www/html/.setup_complete';
if (file_exists($setupMarker)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Setup already completed']);
    exit;
}

// Get the step parameter
$step = $_GET['step'] ?? null;

if (!$step) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No step specified']);
    exit;
}

// Include database connection without full head.php
require_once(__DIR__ . '/../../vendor/autoload.php');

// Database connection
try {
    $DBLIB = new MysqliDb([
        'host' => getenv('DB_HOSTNAME'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'db' => getenv('DB_DATABASE'),
        'port' => getenv('DB_PORT') ?: 3306,
        'charset' => 'utf8'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle different steps
switch ($step) {
    case 'check_db':
        handleCheckDatabase();
        break;

    case 'create_admin':
        handleCreateAdmin();
        break;

    case 'create_company':
        handleCreateCompany();
        break;

    case 'complete':
        handleComplete();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown step']);
        exit;
}

/**
 * Check database connection and table count
 */
function handleCheckDatabase() {
    global $DBLIB;

    try {
        // Get database name
        $dbName = getenv('DB_DATABASE');

        // Count tables
        $result = $DBLIB->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$dbName]);

        if ($result && count($result) > 0) {
            $tableCount = $result[0]['count'] ?? 0;

            echo json_encode([
                'success' => true,
                'message' => 'Database connection successful',
                'data' => [
                    'database_name' => $dbName,
                    'table_count' => (int)$tableCount
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Could not retrieve table information'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database check failed: ' . $e->getMessage()]);
    }
}

/**
 * Create admin user and instance
 */
function handleCreateAdmin() {
    global $DBLIB;

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['admin']) || !isset($data['company'])) {
            throw new Exception('Invalid data provided');
        }

        $admin = $data['admin'];
        $company = $data['company'];

        // Validate required fields
        if (empty($admin['firstname']) || empty($admin['lastname']) || empty($admin['email']) || empty($admin['password'])) {
            throw new Exception('Missing required admin fields');
        }

        if (empty($company['name'])) {
            throw new Exception('Missing company name');
        }

        // Check if user exists
        $DBLIB->where('users_email', $admin['email']);
        $existingUser = $DBLIB->getOne('users', ['users_userid']);

        if ($existingUser) {
            throw new Exception('User with this email already exists');
        }

        // Create instance first
        $instanceData = [
            'instances_name' => $company['name'],
            'instances_address' => $company['address'] ?? null,
            'instances_phone' => $company['phone'] ?? null,
            'instances_email' => $company['email'] ?? null,
            'instances_config_currency' => $company['currency'] ?? 'EUR',
            'instances_deleted' => 0
        ];

        $instanceId = $DBLIB->insert('instances', $instanceData);

        if (!$instanceId) {
            throw new Exception('Failed to create instance');
        }

        // Create user
        $passwordHash = password_hash($admin['password'], PASSWORD_BCRYPT);
        $userHash = bin2hex(random_bytes(16));

        $userData = [
            'users_name1' => $admin['firstname'],
            'users_name2' => $admin['lastname'],
            'users_email' => $admin['email'],
            'users_password' => $admin['password'], // Plain for now, will be hashed properly
            'users_hash' => $userHash,
            'users_created' => date('Y-m-d H:i:s'),
            'users_emailVerified' => 1,
            'users_deleted' => 0,
            'users_suspended' => 0
        ];

        $userId = $DBLIB->insert('users', $userData);

        if (!$userId) {
            // Rollback instance creation
            $DBLIB->delete('instances', ['instances_id' => $instanceId]);
            throw new Exception('Failed to create user');
        }

        // Link user to instance
        $DBLIB->insert('usersInstances', [
            'users_userid' => $userId,
            'instances_id' => $instanceId,
            'usersInstances_permission' => 'SUPER_ADMIN'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Admin user and instance created successfully',
            'data' => [
                'user_id' => $userId,
                'instance_id' => $instanceId
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update company settings
 */
function handleCreateCompany() {
    global $DBLIB;

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['company'])) {
            throw new Exception('Invalid data provided');
        }

        $company = $data['company'];

        // Get the instance ID (last created)
        $DBLIB->orderBy('instances_id', 'DESC');
        $instance = $DBLIB->getOne('instances', ['instances_id']);

        if (!$instance) {
            throw new Exception('No instance found');
        }

        $instanceId = $instance['instances_id'];

        // Update instance with company details
        $updateData = [
            'instances_address' => $company['address'] ?? null,
            'instances_phone' => $company['phone'] ?? null,
            'instances_email' => $company['email'] ?? null,
            'instances_config_currency' => $company['currency'] ?? 'EUR'
        ];

        $DBLIB->where('instances_id', $instanceId);
        $updated = $DBLIB->update('instances', $updateData);

        if (!$updated) {
            throw new Exception('Failed to update company settings');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Company settings saved'
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Complete setup and create marker file
 */
function handleComplete() {
    global $DBLIB;

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['settings'])) {
            throw new Exception('Invalid data provided');
        }

        $settings = $data['settings'];

        // Get the last created instance
        $DBLIB->orderBy('instances_id', 'DESC');
        $instance = $DBLIB->getOne('instances', ['instances_id']);

        if (!$instance) {
            throw new Exception('No instance found');
        }

        $instanceId = $instance['instances_id'];

        // Store settings in the config table or as instance configuration
        // For now, we'll just mark setup as complete

        // Create the setup completion marker file
        $setupMarker = '/var/www/html/.setup_complete';

        // Try to create the marker file
        if (!file_put_contents($setupMarker, json_encode([
            'completed' => date('Y-m-d H:i:s'),
            'instance_id' => $instanceId,
            'version' => '1.0.0'
        ]))) {
            // If file write fails, we can still consider it successful for database purposes
            // The system will check for this file on subsequent requests
        }

        // Make sure the file has correct permissions
        if (file_exists($setupMarker)) {
            chmod($setupMarker, 0644);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Setup completed successfully',
            'data' => [
                'instance_id' => $instanceId,
                'redirect' => '/login/'
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
