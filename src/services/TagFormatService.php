<?php
/**
 * TagFormatService — Zentrale Verwaltung der Tag-/Barcode-/QR-Formate
 *
 * Generates and parses RFID EPCs, barcodes and QR codes with embedded company identifier.
 * The company code comes from instances_companyCode (6 alphanumeric characters).
 */
class TagFormatService
{
    private $db;
    private int $instanceId;
    private ?string $companyCode = null;

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
    }

    /**
     * Get the 6-char alphanumeric company code for this instance
     */
    public function getCompanyCode(): string
    {
        if ($this->companyCode !== null) return $this->companyCode;

        $this->db->where('instances_id', $this->instanceId);
        $inst = $this->db->getOne('instances', ['instances_companyCode']);

        if ($inst && !empty($inst['instances_companyCode'])) {
            $this->companyCode = strtoupper($inst['instances_companyCode']);
        } else {
            // Fallback: generate from instance ID (should not happen in normal operation)
            $this->companyCode = $this->generateCompanyCode($this->instanceId);
        }

        return $this->companyCode;
    }

    /**
     * Generate a new 6-character alphanumeric company code (A-Z, 0-9)
     * and store it in the instances table.
     *
     * @param int $instanceId
     * @return string The generated code
     * @throws Exception if unable to generate a unique code after max attempts
     */
    public function generateCompanyCode(int $instanceId): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $code = $this->randomAlphanumeric(6);

            // Check for uniqueness in the database
            $this->db->where('instances_companyCode', $code);
            $existing = $this->db->getOne('instances', ['instances_id']);

            if (!$existing) {
                // Code is unique, store it
                $this->db->where('instances_id', $instanceId);
                $this->db->update('instances', ['instances_companyCode' => $code]);

                $this->companyCode = $code;
                return $code;
            }

            $attempt++;
        }

        throw new Exception("Unable to generate a unique company code after {$maxAttempts} attempts");
    }

    /**
     * Change the company code for an instance.
     * Validates the new code, checks for collisions, logs the change, and updates the database.
     *
     * @param int $instanceId
     * @param string $newCode Must be 6 alphanumeric characters
     * @return bool true on success
     * @throws Exception if code is invalid or already in use
     */
    public function changeCompanyCode(int $instanceId, string $newCode): bool
    {
        $newCode = strtoupper(trim($newCode));

        // Validate format: 6 alphanumeric characters
        if (!preg_match('/^[A-Z0-9]{6}$/', $newCode)) {
            throw new Exception('Company code must be 6 alphanumeric characters (A-Z, 0-9)');
        }

        // Check if code is already taken
        $this->db->where('instances_companyCode', $newCode);
        $this->db->where('instances_id', $instanceId, '!=');
        $existing = $this->db->getOne('instances', ['instances_id']);

        if ($existing) {
            throw new Exception("Company code '{$newCode}' is already in use");
        }

        // Get the old code for logging
        $this->db->where('instances_id', $instanceId);
        $inst = $this->db->getOne('instances', ['instances_companyCode']);
        $oldCode = $inst['instances_companyCode'] ?? null;

        // Update the instance
        $this->db->where('instances_id', $instanceId);
        $updated = $this->db->update('instances', ['instances_companyCode' => $newCode]);

        if ($updated) {
            // Log the change in history table
            $this->db->insert('company_code_history', [
                'cch_instanceId' => $instanceId,
                'cch_oldCode' => $oldCode,
                'cch_newCode' => $newCode,
                'cch_changedAt' => date('Y-m-d H:i:s'),
            ]);

            // Update cached value
            if ($this->instanceId === $instanceId) {
                $this->companyCode = $newCode;
            }

            return true;
        }

        return false;
    }

    /**
     * Check if a company code collision exists with any partner instance.
     * Returns true if a collision is found.
     *
     * @param string $code The company code to check
     * @param array $partnerInstanceIds Array of partner instance IDs
     * @return bool true if collision found
     */
    public function checkFederationCollision(string $code, array $partnerInstanceIds): bool
    {
        if (empty($partnerInstanceIds)) {
            return false;
        }

        $code = strtoupper($code);
        $this->db->where('instances_companyCode', $code);
        $this->db->where('instances_id', $partnerInstanceIds, 'IN');
        $result = $this->db->getOne('instances', ['instances_id']);

        return (bool)$result;
    }

    // ══════════════════════════════════════
    // EPC / Barcode Generation
    // ══════════════════════════════════════

    /**
     * Generate Asset EPC: RMS-ABCDE1-A-000042
     */
    public function generateAssetEpc(int $assetId): string
    {
        return 'RMS-' . $this->getCompanyCode() . '-A-' . str_pad($assetId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Stock Instance EPC: RMS-ABCDE1-I-000023
     */
    public function generateStockInstanceEpc(int $instanceNumber): string
    {
        return 'RMS-' . $this->getCompanyCode() . '-I-' . str_pad($instanceNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate External Item barcode: RMS-ABCDE1-E-000001
     */
    public function generateExternalBarcode(int $itemId): string
    {
        return 'RMS-' . $this->getCompanyCode() . '-E-' . str_pad($itemId, 6, '0', STR_PAD_LEFT);
    }

    // ══════════════════════════════════════
    // QR Code Generation
    // ══════════════════════════════════════

    /**
     * Generate QR code content: RMS://ABCDE1/A/000042
     */
    public function generateAssetQr(int $assetId): string
    {
        return 'RMS://' . $this->getCompanyCode() . '/A/' . str_pad($assetId, 6, '0', STR_PAD_LEFT);
    }

    public function generateStockInstanceQr(int $instanceNumber): string
    {
        return 'RMS://' . $this->getCompanyCode() . '/I/' . str_pad($instanceNumber, 6, '0', STR_PAD_LEFT);
    }

    public function generateExternalQr(int $itemId): string
    {
        return 'RMS://' . $this->getCompanyCode() . '/E/' . str_pad($itemId, 6, '0', STR_PAD_LEFT);
    }

    // ══════════════════════════════════════
    // Parsing (decode any scanned value)
    // ══════════════════════════════════════

    /**
     * Parse any scanned tag value and extract its components.
     * Supports new format (RMS-ABCDE1-A-000042), old format (RMS-A-000042),
     * 4-char hex codes for backward compat (RMS-XXXX-A-000042),
     * and QR formats (RMS://ABCDE1/A/000042 or RMS://XXXX/A/000042).
     *
     * @return array|null {
     *   'company_code' => string|null (null for old format),
     *   'entity_type'  => 'asset'|'stock_instance'|'external',
     *   'entity_id'    => int,
     *   'is_local'     => bool (true if company_code matches our instance),
     *   'is_old_format'=> bool,
     *   'raw'          => string (original scan value)
     * }
     */
    public function parse(string $value): ?array
    {
        $value = trim($value);

        // New barcode format: RMS-ABCDE1-A-000042 (6 alphanumeric)
        if (preg_match('/^RMS-([A-Z0-9]{6})-([AIE])-(\d{6})$/i', $value, $m)) {
            return [
                'company_code' => strtoupper($m[1]),
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => strtoupper($m[1]) === $this->getCompanyCode(),
                'is_old_format'=> false,
                'raw'          => $value,
            ];
        }

        // Old 4-char hex format (backward compat): RMS-XXXX-A-000042
        if (preg_match('/^RMS-([A-F0-9]{4})-([AIE])-(\d{6})$/i', $value, $m)) {
            $hexCode = strtoupper($m[1]);
            // Resolve from history if needed
            $resolvedCode = $this->resolveOldCode($hexCode);

            return [
                'company_code' => $hexCode,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $resolvedCode ? ($resolvedCode === $this->getCompanyCode()) : false,
                'is_old_format'=> false,
                'raw'          => $value,
                'resolved_code'=> $resolvedCode,
            ];
        }

        // New QR format: RMS://ABCDE1/A/000042 (6 alphanumeric)
        if (preg_match('#^RMS://([A-Z0-9]{6})/([AIE])/(\d{6})$#i', $value, $m)) {
            return [
                'company_code' => strtoupper($m[1]),
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => strtoupper($m[1]) === $this->getCompanyCode(),
                'is_old_format'=> false,
                'raw'          => $value,
            ];
        }

        // Old QR format: RMS://XXXX/A/000042 (4-char hex, backward compat)
        if (preg_match('#^RMS://([A-F0-9]{4})/([AIE])/(\d{6})$#i', $value, $m)) {
            $hexCode = strtoupper($m[1]);
            $resolvedCode = $this->resolveOldCode($hexCode);

            return [
                'company_code' => $hexCode,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $resolvedCode ? ($resolvedCode === $this->getCompanyCode()) : false,
                'is_old_format'=> false,
                'raw'          => $value,
                'resolved_code'=> $resolvedCode,
            ];
        }

        // Old barcode format: RMS-A-000042 (no company code)
        if (preg_match('/^RMS-([AIE])-(\d{6})$/i', $value, $m)) {
            return [
                'company_code' => null,
                'entity_type'  => $this->typeLetterToName($m[1]),
                'entity_id'    => (int)$m[2],
                'is_local'     => true, // assume local for old format
                'is_old_format'=> true,
                'raw'          => $value,
            ];
        }

        // Old external format: EXT-000001
        if (preg_match('/^EXT-(\d{6})$/i', $value, $m)) {
            return [
                'company_code' => null,
                'entity_type'  => 'external',
                'entity_id'    => (int)$m[1],
                'is_local'     => true,
                'is_old_format'=> true,
                'raw'          => $value,
            ];
        }

        // Not an RMS format — could be a raw RFID EPC or unknown barcode
        return null;
    }

    /**
     * Resolve an old 4-char hex code to the current 6-char alphanumeric code
     * by checking the company_code_history table.
     *
     * @param string $oldCode The old 4-char hex code
     * @return string|null The current 6-char code if found in history, null otherwise
     */
    public function resolveOldCode(string $oldCode): ?string
    {
        $oldCode = strtoupper($oldCode);

        // Look up in company_code_history for the latest (most recent) mapping
        $this->db->where('cch_oldCode', $oldCode);
        $this->db->orderBy('cch_changedAt', 'DESC');
        $this->db->limit(1);
        $history = $this->db->getOne('company_code_history', ['cch_newCode']);

        return $history ? $history['cch_newCode'] : null;
    }

    /**
     * Convert type letter to entity type name
     */
    private function typeLetterToName(string $letter): string
    {
        return match (strtoupper($letter)) {
            'A' => 'asset',
            'I' => 'stock_instance',
            'E' => 'external',
            default => 'unknown',
        };
    }

    /**
     * Check if a scanned value belongs to our instance
     */
    public function isLocal(string $value): bool
    {
        $parsed = $this->parse($value);
        return $parsed ? $parsed['is_local'] : true; // if unparseable, assume local
    }

    /**
     * Get company code from a scanned value (returns null if not parseable)
     */
    public function extractCompanyCode(string $value): ?string
    {
        $parsed = $this->parse($value);
        return $parsed ? $parsed['company_code'] : null;
    }

    /**
     * Find which instance a company code belongs to.
     * Checks both active instances_companyCode and company_code_history for resolved codes.
     *
     * @param string $code The company code (6-char alphanumeric or 4-char hex)
     * @return array|null Instance data if found
     */
    public function findInstanceByCompanyCode(string $code): ?array
    {
        $code = strtoupper($code);

        // First, try to find by current company code
        $this->db->where('instances_companyCode', $code);
        $inst = $this->db->getOne('instances', ['instances_id', 'instances_name', 'instances_companyCode']);

        if ($inst) {
            return $inst;
        }

        // If not found and it looks like a 4-char hex code, try to resolve from history
        if (preg_match('/^[A-F0-9]{4}$/', $code)) {
            $resolvedCode = $this->resolveOldCode($code);
            if ($resolvedCode) {
                $this->db->where('instances_companyCode', $resolvedCode);
                $inst = $this->db->getOne('instances', ['instances_id', 'instances_name', 'instances_companyCode']);
                return $inst ?: null;
            }
        }

        return null;
    }

    /**
     * Migrate an old-format tag to the new format
     */
    public function migrateToNewFormat(string $oldTag): ?string
    {
        $parsed = $this->parse($oldTag);
        if (!$parsed || !$parsed['is_old_format']) return null;

        return match ($parsed['entity_type']) {
            'asset' => $this->generateAssetEpc($parsed['entity_id']),
            'stock_instance' => $this->generateStockInstanceEpc($parsed['entity_id']),
            'external' => $this->generateExternalBarcode($parsed['entity_id']),
            default => null,
        };
    }

    // ══════════════════════════════════════
    // Utilities
    // ══════════════════════════════════════

    /**
     * Generate next internal serial number for a given asset type
     * Format: {TypePrefix}-{Sequential} e.g., MH-001, LED-002
     *
     * @param int $assetTypeId The asset type ID
     * @return string The generated internal serial number
     */
    public function generateInternalSerial(int $assetTypeId): string
    {
        // Get asset type short name or abbreviation
        $this->db->where('assetTypes_id', $assetTypeId);
        $assetType = $this->db->getOne('assetTypes', ['assetTypes_id', 'assetTypes_name']);

        if (!$assetType) {
            throw new Exception("Asset type {$assetTypeId} not found");
        }

        // Generate a short prefix from the asset type name (first 3 chars uppercase)
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $assetType['assetTypes_name']), 0, 3));
        if (empty($prefix)) {
            $prefix = 'AST'; // Fallback to AST if name is empty
        }

        // Count existing assets of this type to get next sequential number
        $this->db->where('assetTypes_id', $assetTypeId);
        $count = $this->db->getValue('assets', 'COUNT(assets_id)');
        $nextNumber = ($count + 1);

        // Format as TypePrefix-SequentialNumber (e.g., MH-001)
        return $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random alphanumeric string of specified length.
     * Uses uppercase letters A-Z and digits 0-9.
     *
     * @param int $length
     * @return string
     */
    private function randomAlphanumeric(int $length): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $result;
    }
}
