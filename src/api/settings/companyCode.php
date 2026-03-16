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

    // Auto-generate if null
    if (is_null($code)) {
        $code = generateCompanyCode();
        $updateQuery = "UPDATE instances SET instances_companyCode = ? WHERE instances_id = ?";
        $DBLIB->query($updateQuery, [$code, $instanceId]);
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
    global $DBLIB, $instanceId;

    $newCode = generateCompanyCode();

    $query = "UPDATE instances SET instances_companyCode = ? WHERE instances_id = ?";
    $result = $DBLIB->query($query, [$newCode, $instanceId]);

    if (!$result) {
        finish(false, ["message" => "Failed to generate new code"]);
    }

    // Log the change
    logCodeChange('generate', null, $newCode, 'Auto-generated new code');

    finish(true, [
        "code" => $newCode,
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
        finish(false, ["message" => "Code must be 6 characters, uppercase letters and numbers only"]);
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

    // Get partner instances from partner_links
    $partnerQuery = "
        SELECT pl.partner_links_id, pl.partner_instances_id
        FROM partner_links
        WHERE partner_links_instances_id = ?
    ";
    $partnerResult = $DBLIB->query($partnerQuery, [$instanceId]);

    $collisions = [];

    if ($partnerResult && $partnerResult->num_rows > 0) {
        while ($partner = $partnerResult->fetch_assoc()) {
            $partnerId = (int)$partner['partner_instances_id'];

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
            codeHistory_id,
            codeHistory_instances_id,
            codeHistory_oldCode,
            codeHistory_newCode,
            codeHistory_action,
            codeHistory_reason,
            codeHistory_timestamp
        FROM code_history
        WHERE codeHistory_instances_id = ?
        ORDER BY codeHistory_timestamp DESC
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
 * Generate a random 6-character company code (A-Z, 0-9)
 * @return string
 */
function generateCompanyCode() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';

    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }

    // Ensure code is not already taken
    while (isCodeTaken($code, 0)) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }

    return $code;
}

/**
 * Validate company code format
 * @param string $code
 * @return bool
 */
function isValidCompanyCode($code) {
    // Must be exactly 6 characters
    if (strlen($code) !== 6) {
        return false;
    }

    // Must contain only uppercase letters and numbers
    if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
        return false;
    }

    return true;
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
        INSERT INTO code_history (
            codeHistory_instances_id,
            codeHistory_oldCode,
            codeHistory_newCode,
            codeHistory_action,
            codeHistory_reason,
            codeHistory_timestamp
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ";

    $DBLIB->query($query, [
        $instanceId,
        $oldCode,
        $newCode,
        $action,
        $reason
    ]);
}
?>
