<?php
/**
 * TagFormatService — Zentrale Verwaltung der Tag-/Barcode-/QR-Formate
 *
 * Generates and parses RFID EPCs, barcodes and QR codes with embedded company identifier.
 * The company code is 8 hex characters derived from MD5 hash of the instance identity.
 * Format: RMS-{8 hex}-{A|I|E}-{6 digits}  (e.g. RMS-a3f7b2c1-A-000042)
 * QR:     RMS://{8 hex}/{A|I|E}/{6 digits}  (e.g. RMS://a3f7b2c1/A/000042)
 *
 * MD5 is used for uniform distribution, NOT for cryptographic security.
 * 8 hex chars = 4.29 billion combinations. Birthday paradox: ~65,000 instances for 50% collision.
 * Federation handshake validates uniqueness among connected partners.
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
     * Get the 8-char hex company code for this instance.
     * Auto-generates via MD5 if not yet set.
     */
    public function getCompanyCode(): string
    {
        if ($this->companyCode !== null) return $this->companyCode;

        $this->db->where('instances_id', $this->instanceId);
        $inst = $this->db->getOne('instances', ['instances_companyCode', 'instances_name', 'instances_partnerCode']);

        if ($inst && !empty($inst['instances_companyCode'])) {
            $this->companyCode = strtolower($inst['instances_companyCode']);
        } else {
            // Auto-generate from MD5 hash of instance identity
            $this->companyCode = $this->generateCompanyCode($this->instanceId, $inst);
        }

        return $this->companyCode;
    }

    /**
     * Generate an 8-character hex company code using MD5.
     *
     * The code is derived from: MD5("{instanceName}|{partnerCode}|{instanceId}")
     * This ensures deterministic, uniformly distributed codes based on the instance identity.
     * MD5 is NOT used for cryptographic purposes here — only for hash distribution.
     *
     * 8 hex chars = 2^32 = 4.29 billion possibilities.
     * Birthday paradox: 50% collision at ~65,536 instances — more than enough for a niche system.
     * Federation handshake detects collisions among connected partners.
     *
     * @param int $instanceId
     * @param array|null $instData Pre-loaded instance data (optional, avoids extra query)
     * @return string 8-char hex code (lowercase)
     */
    public function generateCompanyCode(int $instanceId, ?array $instData = null): string
    {
        // Load instance data if not provided
        if (!$instData) {
            $this->db->where('instances_id', $instanceId);
            $instData = $this->db->getOne('instances', ['instances_name', 'instances_partnerCode']);
        }

        // Build the identity string for MD5 input
        $identityParts = [
            $instData['instances_name'] ?? 'RMS-Instance',
            $instData['instances_partnerCode'] ?? '',
            $instanceId,
            // Add a timestamp salt for uniqueness if identity is thin
            date('Y-m-d H:i:s'),
        ];
        $identityString = implode('|', $identityParts);

        // MD5 → 32 hex chars → take first 8
        $md5Hash = md5($identityString);
        $code = substr($md5Hash, 0, 8);

        // Collision check: if code already exists, rehash with counter
        $maxAttempts = 100;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            $this->db->where('instances_companyCode', $code);
            $this->db->where('instances_id', $instanceId, '!=');
            $existing = $this->db->getOne('instances', ['instances_id']);

            if (!$existing) break;

            // Rehash with attempt counter as additional salt
            $attempt++;
            $code = substr(md5($identityString . '|' . $attempt), 0, 8);
        }

        // Store in database
        $this->db->where('instances_id', $instanceId);
        $this->db->update('instances', ['instances_companyCode' => $code]);

        $this->companyCode = $code;
        return $code;
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
        $newCode = strtolower(trim($newCode));

        // Validate format: 8 hex characters
        if (!preg_match('/^[a-f0-9]{8}$/', $newCode)) {
            throw new Exception('Company code must be 8 hexadecimal characters (0-9, a-f)');
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
        if ($oldCode) $oldCode = strtolower($oldCode);

        // Update the instance
        $this->db->where('instances_id', $instanceId);
        $updated = $this->db->update('instances', ['instances_companyCode' => $newCode]);

        if ($updated) {
            // Log the change in history table
            $this->db->insert('company_code_history', [
                'instances_id' => $instanceId,
                'old_code' => $oldCode,
                'new_code' => $newCode,
                'changed_at' => date('Y-m-d H:i:s'),
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

        // Current barcode format: RMS-a3f7b2c1-A-000042 (8 hex chars from MD5)
        if (preg_match('/^RMS-([a-f0-9]{8})-([AIE])-(\d{6})$/i', $value, $m)) {
            $code = strtolower($m[1]);
            return [
                'company_code' => $code,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $code === $this->getCompanyCode(),
                'is_old_format'=> false,
                'raw'          => $value,
            ];
        }

        // Legacy 6-char alphanumeric format (backward compat): RMS-ABCDE1-A-000042
        if (preg_match('/^RMS-([A-Z0-9]{6})-([AIE])-(\d{6})$/i', $value, $m)) {
            $legacyCode = strtolower($m[1]);
            $resolvedCode = $this->resolveOldCode($legacyCode);

            return [
                'company_code' => $legacyCode,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $resolvedCode ? ($resolvedCode === $this->getCompanyCode()) : false,
                'is_old_format'=> true,
                'raw'          => $value,
                'resolved_code'=> $resolvedCode,
            ];
        }

        // Legacy 4-char hex format (backward compat): RMS-XXXX-A-000042
        if (preg_match('/^RMS-([a-f0-9]{4})-([AIE])-(\d{6})$/i', $value, $m)) {
            $hexCode = strtolower($m[1]);
            $resolvedCode = $this->resolveOldCode($hexCode);

            return [
                'company_code' => $hexCode,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $resolvedCode ? ($resolvedCode === $this->getCompanyCode()) : false,
                'is_old_format'=> true,
                'raw'          => $value,
                'resolved_code'=> $resolvedCode,
            ];
        }

        // Current QR format: RMS://a3f7b2c1/A/000042 (8 hex chars)
        if (preg_match('#^RMS://([a-f0-9]{8})/([AIE])/(\d{6})$#i', $value, $m)) {
            $code = strtolower($m[1]);
            return [
                'company_code' => $code,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $code === $this->getCompanyCode(),
                'is_old_format'=> false,
                'raw'          => $value,
            ];
        }

        // Legacy QR formats (6-char alphanumeric or 4-char hex)
        if (preg_match('#^RMS://([a-zA-Z0-9]{4,6})/([AIE])/(\d{6})$#i', $value, $m)) {
            $legacyCode = strtolower($m[1]);
            $resolvedCode = $this->resolveOldCode($legacyCode);

            return [
                'company_code' => $legacyCode,
                'entity_type'  => $this->typeLetterToName($m[2]),
                'entity_id'    => (int)$m[3],
                'is_local'     => $resolvedCode ? ($resolvedCode === $this->getCompanyCode()) : false,
                'is_old_format'=> true,
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
        $oldCode = strtolower($oldCode);

        // Look up in company_code_history for the latest (most recent) mapping
        // Check both the exact code and uppercase variant for backward compat
        $this->db->where('(LOWER(old_code) = ?)', [$oldCode]);
        $this->db->orderBy('changed_at', 'DESC');
        $history = $this->db->getOne('company_code_history', ['new_code']);

        return $history ? strtolower($history['new_code']) : null;
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
        $code = strtolower($code);

        // First, try to find by current company code (8 hex chars)
        $this->db->where('instances_companyCode', $code);
        $inst = $this->db->getOne('instances', ['instances_id', 'instances_name', 'instances_companyCode']);

        if ($inst) {
            return $inst;
        }

        // If not found, try to resolve from history (covers old 4-char and 6-char codes)
        $resolvedCode = $this->resolveOldCode($code);
        if ($resolvedCode) {
            $this->db->where('instances_companyCode', $resolvedCode);
            $inst = $this->db->getOne('instances', ['instances_id', 'instances_name', 'instances_companyCode']);
            return $inst ?: null;
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
     * Generate an MD5-based company code from arbitrary input.
     * Useful for generating codes from custom identity strings.
     *
     * @param string $identity Input string to hash
     * @return string 8-char hex code (lowercase)
     */
    public static function md5Code(string $identity): string
    {
        return substr(md5($identity), 0, 8);
    }
}
