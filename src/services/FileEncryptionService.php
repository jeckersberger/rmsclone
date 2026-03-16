<?php
/**
 * FileEncryptionService — Encrypts files at rest for GoBD/accounting compliance
 *
 * Uses AES-256-GCM for authenticated encryption with:
 * - 256-bit key (32 bytes)
 * - 128-bit IV/nonce (16 bytes)
 * - 128-bit authentication tag (16 bytes)
 *
 * Each file is encrypted independently with a unique IV.
 * Storage format: [16 bytes IV][16 bytes auth tag][encrypted data]
 *
 * The encryption key should be derived from a master key using HKDF or stored securely.
 * Recommended: Generate with: openssl rand -hex 32
 */
class FileEncryptionService
{
    private string $masterKey;
    private string $cipher = 'aes-256-gcm';

    /**
     * Initialize with the master encryption key
     *
     * @param string $masterKey 32-byte hex-encoded key (or raw binary key)
     * @throws InvalidArgumentException if key is invalid
     */
    public function __construct(string $masterKey)
    {
        // Support both hex-encoded and raw binary keys
        if (strlen($masterKey) === 64 && ctype_xdigit($masterKey)) {
            $this->masterKey = hex2bin($masterKey);
        } elseif (strlen($masterKey) === 32) {
            $this->masterKey = $masterKey;
        } else {
            throw new InvalidArgumentException('Master key must be 32 bytes (raw) or 64 hex characters');
        }

        if (strlen($this->masterKey) !== 32) {
            throw new InvalidArgumentException('Master key must be exactly 32 bytes');
        }
    }

    /**
     * Encrypt a file and save encrypted content to output path
     *
     * @param string $inputPath Path to source file
     * @param string $outputPath Path where encrypted file will be saved
     * @return bool True on success, false on failure
     */
    public function encryptFile(string $inputPath, string $outputPath): bool
    {
        if (!file_exists($inputPath) || !is_readable($inputPath)) {
            error_log("FileEncryptionService: Cannot read input file: $inputPath");
            return false;
        }

        $plaintext = file_get_contents($inputPath);
        if ($plaintext === false) {
            error_log("FileEncryptionService: Failed to read file: $inputPath");
            return false;
        }

        // Generate random IV (16 bytes)
        $iv = openssl_random_pseudo_bytes(16, $strong);
        if (!$strong) {
            error_log("FileEncryptionService: Insufficient entropy for IV generation");
            return false;
        }

        // Encrypt with authentication tag
        $tag = '';
        $encrypted = @openssl_encrypt($plaintext, $this->cipher, $this->masterKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($encrypted === false) {
            error_log("FileEncryptionService: Encryption failed: " . openssl_error_string());
            return false;
        }

        // Verify tag was generated
        if (strlen($tag) !== 16) {
            error_log("FileEncryptionService: Invalid authentication tag length");
            return false;
        }

        // Write: IV + tag + encrypted data
        $result = file_put_contents($outputPath, $iv . $tag . $encrypted, LOCK_EX);
        if ($result === false) {
            error_log("FileEncryptionService: Failed to write encrypted file: $outputPath");
            return false;
        }

        return true;
    }

    /**
     * Decrypt a file and save decrypted content to output path
     *
     * @param string $inputPath Path to encrypted file
     * @param string $outputPath Path where decrypted file will be saved
     * @return bool True on success, false on failure
     */
    public function decryptFile(string $inputPath, string $outputPath): bool
    {
        if (!file_exists($inputPath) || !is_readable($inputPath)) {
            error_log("FileEncryptionService: Cannot read encrypted file: $inputPath");
            return false;
        }

        $data = file_get_contents($inputPath);
        if ($data === false) {
            error_log("FileEncryptionService: Failed to read encrypted file: $inputPath");
            return false;
        }

        // Must have at least IV (16) + tag (16) = 32 bytes
        if (strlen($data) < 32) {
            error_log("FileEncryptionService: Encrypted file too short (minimum 32 bytes needed)");
            return false;
        }

        // Extract IV, tag, and encrypted content
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);

        // Decrypt with authentication verification
        $decrypted = @openssl_decrypt($encrypted, $this->cipher, $this->masterKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            error_log("FileEncryptionService: Decryption failed (authentication failure or corruption): " . openssl_error_string());
            return false;
        }

        // Write decrypted content
        $result = file_put_contents($outputPath, $decrypted, LOCK_EX);
        if ($result === false) {
            error_log("FileEncryptionService: Failed to write decrypted file: $outputPath");
            return false;
        }

        return true;
    }

    /**
     * Encrypt data in memory (for small text/metadata)
     * Returns base64-encoded encrypted data suitable for database storage
     *
     * @param string $plaintext Data to encrypt
     * @return string Base64-encoded encrypted data (IV + tag + encrypted content)
     */
    public function encrypt(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(16, $strong);
        if (!$strong) {
            throw new RuntimeException("Insufficient entropy for encryption");
        }

        $tag = '';
        $encrypted = openssl_encrypt($plaintext, $this->cipher, $this->masterKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($encrypted === false) {
            throw new RuntimeException("In-memory encryption failed: " . openssl_error_string());
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt data from base64-encoded format
     *
     * @param string $encryptedBase64 Base64-encoded encrypted data (IV + tag + encrypted content)
     * @return string|false Decrypted plaintext, or false on failure
     */
    public function decrypt(string $encryptedBase64): string|false
    {
        $data = base64_decode($encryptedBase64, true);
        if ($data === false) {
            error_log("FileEncryptionService: Invalid base64 in decrypt()");
            return false;
        }

        if (strlen($data) < 32) {
            error_log("FileEncryptionService: Decoded data too short for decryption");
            return false;
        }

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);

        $decrypted = @openssl_decrypt($encrypted, $this->cipher, $this->masterKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            error_log("FileEncryptionService: In-memory decryption failed: " . openssl_error_string());
            return false;
        }

        return $decrypted;
    }

    /**
     * Calculate SHA-256 integrity hash of a file
     * Used for GoBD compliance to verify file integrity
     *
     * @param string $filePath Path to file
     * @return string SHA-256 hash in hex format
     */
    public function calculateHash(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * Verify file integrity against stored hash
     *
     * @param string $filePath Path to file
     * @param string $expectedHash SHA-256 hash to compare against
     * @return bool True if hashes match, false otherwise
     */
    public function verifyIntegrity(string $filePath, string $expectedHash): bool
    {
        if (!file_exists($filePath)) {
            error_log("FileEncryptionService: File not found for integrity check: $filePath");
            return false;
        }

        $actualHash = $this->calculateHash($filePath);
        return hash_equals($expectedHash, $actualHash);
    }

    /**
     * Generate a random 256-bit master key for new installations
     * Run this once during setup and store the result in FILE_ENCRYPTION_KEY
     *
     * Example usage:
     *   $key = FileEncryptionService::generateMasterKey();
     *   // Store $key in .env: FILE_ENCRYPTION_KEY=$key
     *
     * @return string 64-character hex-encoded 256-bit key
     */
    public static function generateMasterKey(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(32, $strong));
    }
}
