<?php
/**
 * TotpService - Zwei-Faktor-Authentifizierung (TOTP / RFC 6238)
 *
 * Implementiert TOTP-Generierung, -Verifizierung, QR-Code-Erstellung
 * und Backup-Code-Verwaltung.
 */
class TotpService
{
    private $db;

    /** Minimale Passwortlaenge */
    const MIN_PASSWORD_LENGTH = 10;

    /** Base32-Alphabet fuer TOTP-Secret */
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generiert ein zufaelliges 16-Zeichen Base32-Secret
     */
    public function generateSecret(): string
    {
        $secret = '';
        $randomBytes = random_bytes(16);
        for ($i = 0; $i < 16; $i++) {
            $secret .= self::BASE32_CHARS[ord($randomBytes[$i]) % 32];
        }
        return $secret;
    }

    /**
     * Generiert die otpauth:// URI fuer Authenticator-Apps
     */
    public function getProvisioningUri(string $secret, string $email, string $issuer = 'AdamRMS'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Generiert QR-Code-URL ueber Google Charts API
     */
    public function generateQrCode(string $uri): string
    {
        return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl='
            . urlencode($uri) . '&choe=UTF-8';
    }

    /**
     * Verifiziert einen TOTP-Code (RFC 6238)
     *
     * Prueft den aktuellen Zeitschritt sowie +-$window Schritte.
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $currentTimeStep = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculated = $this->generateTotp($secret, $currentTimeStep + $i);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * TOTP-Algorithmus nach RFC 6238 (HMAC-SHA1)
     */
    public function generateTotp(string $secret, ?int $timeStep = null): string
    {
        $timeStep = $timeStep ?? (int) floor(time() / 30);
        // 8 Byte Big-Endian Zeitwert
        $time = pack('N*', 0, $timeStep);
        $decodedSecret = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $time, $decodedSecret, true);
        $offset = ord($hash[19]) & 0x0f;
        $code = ((ord($hash[$offset]) & 0x7f) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generiert Einmal-Backup-Codes
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // 8-stellige alphanumerische Codes in Gruppen: XXXX-XXXX
            $part1 = strtoupper(bin2hex(random_bytes(2)));
            $part2 = strtoupper(bin2hex(random_bytes(2)));
            $codes[] = $part1 . '-' . $part2;
        }
        return $codes;
    }

    /**
     * Speichert gehashte Backup-Codes fuer einen Benutzer
     */
    public function storeBackupCodes(int $userId, array $codes): bool
    {
        $hashed = array_map(function ($code) {
            return password_hash(str_replace('-', '', strtoupper($code)), PASSWORD_BCRYPT);
        }, $codes);

        $this->db->where('users_userid', $userId);
        return $this->db->update('users', [
            'users_totpBackupCodes' => json_encode($hashed),
        ]);
    }

    /**
     * Verifiziert und verbraucht einen Backup-Code
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        $code = str_replace('-', '', strtoupper(trim($code)));

        $this->db->where('users_userid', $userId);
        $user = $this->db->getOne('users', ['users_totpBackupCodes']);
        if (!$user || empty($user['users_totpBackupCodes'])) {
            return false;
        }

        $hashedCodes = json_decode($user['users_totpBackupCodes'], true);
        if (!is_array($hashedCodes)) {
            return false;
        }

        foreach ($hashedCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                // Code verbrauchen (entfernen)
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes);
                $this->db->where('users_userid', $userId);
                $this->db->update('users', [
                    'users_totpBackupCodes' => json_encode($hashedCodes),
                ]);
                return true;
            }
        }
        return false;
    }

    /**
     * Aktiviert TOTP fuer einen Benutzer
     */
    public function enableTotp(int $userId, string $secret): bool
    {
        $this->db->where('users_userid', $userId);
        return $this->db->update('users', [
            'users_totpSecret' => $secret,
            'users_totpEnabled' => 1,
        ]);
    }

    /**
     * Deaktiviert TOTP fuer einen Benutzer
     */
    public function disableTotp(int $userId): bool
    {
        $this->db->where('users_userid', $userId);
        return $this->db->update('users', [
            'users_totpSecret' => null,
            'users_totpEnabled' => 0,
            'users_totpBackupCodes' => null,
        ]);
    }

    /**
     * Prueft ob TOTP fuer einen Benutzer aktiviert ist
     */
    public function isTotpEnabled(int $userId): bool
    {
        $this->db->where('users_userid', $userId);
        $user = $this->db->getOne('users', ['users_totpEnabled']);
        return $user && (int)$user['users_totpEnabled'] === 1;
    }

    /**
     * Base32-Dekodierung
     */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $pos = strpos(self::BASE32_CHARS, $input[$i]);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }
        return $output;
    }
}
