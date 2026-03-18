<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TagFormatService.php';

// Require admin permission for code management
if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? '';
$tagService = new TagFormatService($DBLIB, $instanceId);

switch ($action) {
    case 'get_code':
        handleGetCode();
        break;

    case 'generate':
        handleGenerate();
        break;

    case 'change':
        handleChange();
        break;

    case 'check_federation':
        handleCheckFederation();
        break;

    case 'get_history':
        handleGetHistory();
        break;

    default:
        finish(false, ["message" => "Invalid action"]);
}

/**
 * Get current company code
 * If none exists, auto-generate one
 */
function handleGetCode() {
    global $DBLIB, $instanceId, $tagService;

    $query = "SELECT instances_companyCode FROM instances WHERE instances_id = ?";
    $result = $DBLIB->query($query, [$instanceId]);

    if (!$result || $result->num_rows === 0) {
        finish(false, ["message" => "Instance not found"]);
    }

    $row = $result->fetch_assoc();
    $code = $row['instances_companyCode'];

    // Auto-generate via MD5 if null
    if (is_null($code)) {
        $code = $tagService->generateCompanyCode($instanceId);
    }

    finish(true, [
        "code" => $code,
        "message" => "Company code retrieved"
    ]);
}

/**
 * Generate a new random company code
 */
function handleGenerate() {
    global $DBLIB, $instanceId, $tagService;

    // Get old code for logging
    $oldQuery = "SELECT instances_companyCode FROM instances WHERE instances_id = ?";
    $oldResult = $DBLIB->query($oldQuery, [$instanceId]);
    $oldRow = $oldResult->fetch_assoc();
    $oldCode = $oldRow['instances_companyCode'];

    // Generate new MD5-based code
    $newCode = $tagService->generateCompanyCode($instanceId);

    // Log the change
    logCodeChange('generate', $oldCode, $newCode, 'MD5-generated new code');

    finish(true, [
        "code" => $newCode,
        "algorithm" => "MD5 (first 8 hex chars)",
        "message" => "Company code generated successfully"
    ]);
}

/**
 * Change to a user-specified code
 */
function handleChange() {
    global $DBLIB, $instanceId;

    $newCode = $_POST['new_code'] ?? '';

    // Validation
    if (empty($newCode)) {
        finish(false, ["message" => "New code is required"]);
    }

    if (!isValidCompanyCode($newCode)) {
        finish(false, ["message" => "Code muss 8 Hex-Zeichen sein (0-9, a-f)"]);
    }

    // Check if code is already taken by another instance
    if (isCodeTaken($newCode, $instanceId)) {
        finish(false, ["message" => "Code is already in use by another instance"]);
    }

    // Get old code for logging
    $oldQuery = "SELECT instances_companyCode FROM instances WHERE instances_id = ?";
    $oldResult = $DBLIB->query($oldQuery, [$instanceId]);
    $oldRow = $oldResult->fetch_assoc();
    $oldCode = $oldRow['instances_companyCode'];

    // Update the code
    $query = "UPDATE instances SET instances_companyCode = ? WHERE instances_id = ?";
    $result = $DBLIB->query($query, [$newCode, $instanceId]);

    if (!$result) {
        finish(false, ["message" => "Failed to change code"]);
    }

    // Log the change
    logCodeChange('change', $oldCode, $newCode, 'User-specified code change');

    finish(true, [
        "code" => $newCode,
        "old_code" => $oldCode,
        "message" => "Company code changed successfully"
    ]);
}

/**
 * Check if current code collides with any federation partner
 */
function handleCheckFederation() {
    global $DBLIB, $instanceId;

    // Get current code
    $codeQuery = "SELECT instances_companyCode FROM instances WHERE instances_id = ?";
    $codeResult = $DBLIB->query($codeQuery, [$instanceId]);

    if (!$codeResult || $codeResult->num_rows === 0) {
        finish(false, ["message" => "Instance not found"]);
    }

    $codeRow = $codeResult->fetch_assoc();
    $currentCode = $codeRow['instances_companyCode'];

    if (is_null($currentCode)) {
        finish(false, ["message" => "No company code set"]);
    }

    // Get partner instances from partner_links (both directions)
    $partnerQuery = "
        SELECT instance_b_id AS partner_id FROM partner_links
        WHERE instance_a_id = ? AND status = 'active' AND deleted = 0
        UNION
        SELECT instance_a_id AS partner_id FROM partner_links
        WHERE instance_b_id = ? AND status = 'active' AND deleted = 0
    ";
    $partnerResult = $DBLIB->query($partnerQuery, [$instanceId, $instanceId]);

    $collisions = [];

    if ($partnerResult && $partnerResult->num_rows > 0) {
        while ($partner = $partnerResult->fetch_assoc()) {
            $partnerId = (int)$partner['partner_id'];

            // Get partner's company code
            $partnerCodeQuery = "SELECT instances_companyCode FROM instances WHERE instances_id = ?";
            $partnerCodeResult = $DBLIB->query($partnerCodeQuery, [$partnerId]);

            if ($partnerCodeResult && $partnerCodeResult->num_rows > 0) {
                $partnerCodeRow = $partnerCodeResult->fetch_assoc();
                $partnerCode = $partnerCodeRow['instances_companyCode'];

                if (!is_null($partnerCode) && $currentCode === $partnerCode) {
                    $collisions[] = [
                        "partner_id" => $partnerId,
                        "partner_code" => $partnerCode
                    ];
                }
            }
        }
    }

    // Also check remote federation servers
    $fedQuery = "
        SELECT partner_servers_id, partner_servers_name, partner_servers_remoteCompanyCode
        FROM partner_servers
        WHERE instances_id = ? AND partner_servers_deleted = 0 AND partner_servers_status = 'active'
          AND partner_servers_remoteCompanyCode IS NOT NULL
    ";
    $fedResult = $DBLIB->query($fedQuery, [$instanceId]);

    if ($fedResult && $fedResult->num_rows > 0) {
        while ($fed = $fedResult->fetch_assoc()) {
            if (strtolower($fed['partner_servers_remoteCompanyCode']) === strtolower($currentCode)) {
                $collisions[] = [
                    "partner_id" => (int)$fed['partner_servers_id'],
                    "partner_name" => $fed['partner_servers_name'],
                    "partner_code" => $fed['partner_servers_remoteCompanyCode'],
                    "source" => "federation"
                ];
            }
        }
    }

    if (!empty($collisions)) {
        finish(true, [
            "has_collision" => true,
            "current_code" => $currentCode,
            "collisions" => $collisions,
            "message" => "Code collision detected with federation partners"
        ]);
    } else {
        finish(true, [
            "has_collision" => false,
            "current_code" => $currentCode,
            "message" => "No code collisions with federation partners"
        ]);
    }
}

/**
 * Get code change history
 */
function handleGetHistory() {
    global $DBLIB, $instanceId;

    $query = "
        SELECT
            id,
            instances_id,
            old_code,
            new_code,
            changed_at
        FROM company_code_history
        WHERE instances_id = ?
        ORDER BY changed_at DESC
        LIMIT 50
    ";

    $result = $DBLIB->query($query, [$instanceId]);

    $history = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }

    finish(true, [
        "history" => $history,
        "count" => count($history),
        "message" => "Code history retrieved"
    ]);
}

/**
 * Validate company code format: 8 hex characters (0-9, a-f)
 * Derived from MD5 hash of instance identity.
 * @param string $code
 * @return bool
 */
function isValidCompanyCode($code) {
    return (bool)preg_match('/^[a-f0-9]{8}$/i', $code);
}

/**
 * Check if a code is already taken by another instance
 * @param string $code
 * @param int $excludeInstanceId - Instance ID to exclude from check (e.g., current instance)
 * @return bool
 */
function isCodeTaken($code, $excludeInstanceId = 0) {
    global $DBLIB;

    $query = "SELECT COUNT(*) as count FROM instances WHERE instances_companyCode = ?";
    $params = [$code];

    // If checking during update, exclude current instance
    if ($excludeInstanceId > 0) {
        $query .= " AND instances_id != ?";
        $params[] = $excludeInstanceId;
    }

    $result = $DBLIB->query($query, $params);

    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

/**
 * Log code change to code_history table
 * @param string $action
 * @param string|null $oldCode
 * @param string $newCode
 * @param string $reason
 */
function logCodeChange($action, $oldCode, $newCode, $reason) {
    global $DBLIB, $instanceId;

    $query = "
        INSERT INTO company_code_history (
            instances_id,
            old_code,
            new_code,
            changed_at
        ) VALUES (?, ?, ?, NOW())
    ";

    $DBLIB->query($query, [
        $instanceId,
        $oldCode,
        $newCode,
    ]);
}
?>
