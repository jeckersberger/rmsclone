<?php
/**
 * EncryptionService - Verschluesselung sensibler Daten at-rest
 *
 * Verschluesselt sensible Felder (IBAN, Steuernummer, etc.) mit AES-256-GCM.
 * Der Schluessel wird aus der Umgebungsvariable ENCRYPTION_KEY gelesen.
 *
 * Usage:
 *   $enc = new EncryptionService();
 *   $encrypted = $enc->encrypt('DE89370400440532013000');
 *   $decrypted = $enc->decrypt($encrypted);
 */
class EncryptionService
{
    private string $key;
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:';

    public function __construct(?string $key = null)
    {
        $this->key = $key ?? getenv('ENCRYPTION_KEY') ?: '';
        if (empty($this->key)) {
            throw new \RuntimeException('ENCRYPTION_KEY environment variable is not set.');
        }
        // Derive a 32-byte key from the configured key
        $this->key = hash('sha256', $this->key, true);
    }

    /**
     * Encrypt a plaintext value.
     * Returns a prefixed string: "enc:<base64(nonce + ciphertext + tag)>"
     */
    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }
        // Already encrypted?
        if (str_starts_with($plaintext, self::PREFIX)) {
            return $plaintext;
        }

        $nonce = random_bytes(12); // 96-bit nonce for GCM
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return self::PREFIX . base64_encode($nonce . $ciphertext . $tag);
    }

    /**
     * Decrypt an encrypted value.
     * Accepts both prefixed encrypted strings and plain text (for backward compatibility).
     */
    public function decrypt(?string $encrypted): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return $encrypted;
        }
        // Not encrypted — return as-is (backward compatibility)
        if (!str_starts_with($encrypted, self::PREFIX)) {
            return $encrypted;
        }

        $data = base64_decode(substr($encrypted, strlen(self::PREFIX)), true);
        if ($data === false || strlen($data) < 28) { // 12 nonce + min 0 cipher + 16 tag
            throw new \RuntimeException('Invalid encrypted data');
        }

        $nonce = substr($data, 0, 12);
        $tag = substr($data, -16);
        $ciphertext = substr($data, 12, -16);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — key mismatch or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Check if a value is encrypted.
     */
    public function isEncrypted(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }

    /**
     * Encrypt multiple fields in an associative array.
     *
     * @param array $data The data array
     * @param array $fields List of field names to encrypt
     * @return array Modified data with specified fields encrypted
     */
    public function encryptFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->encrypt($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Decrypt multiple fields in an associative array.
     *
     * @param array $data The data array
     * @param array $fields List of field names to decrypt
     * @return array Modified data with specified fields decrypted
     */
    public function decryptFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->decrypt($data[$field]);
            }
        }
        return $data;
    }

    /**
     * List of sensitive fields that should be encrypted at-rest.
     */
    public static function getSensitiveFields(): array
    {
        return [
            'instances_bankIban',
            'instances_bankBic',
            'instances_taxNumber',
            'instances_vatId',
            'clients_vatId',
        ];
    }
}
