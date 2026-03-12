<?php
/**
 * CSRF-Token-Schutz
 *
 * Generiert und validiert CSRF-Tokens pro Session.
 * Token wird beim Session-Start erzeugt und muss bei
 * allen POST-Requests als Header "X-CSRF-Token" oder
 * POST-Parameter "csrf_token" mitgesendet werden.
 */
class CsrfService
{
    private const TOKEN_KEY = 'csrf_token';

    /**
     * Generiert ein CSRF-Token und speichert es in der Session
     */
    public static function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Gibt das aktuelle Token zurueck (ohne neues zu erzeugen)
     */
    public static function getToken(): string
    {
        return $_SESSION[self::TOKEN_KEY] ?? '';
    }

    /**
     * Validiert das CSRF-Token aus dem Request
     */
    public static function validateRequest(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true; // GET-Requests brauchen kein CSRF-Token
        }

        // OPTIONS preflight requests durchlassen
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return true;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? '';

        if (empty($token) || empty($_SESSION[self::TOKEN_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_KEY], $token);
    }

    /**
     * Erzwingt CSRF-Validierung — bricht bei Fehler ab
     */
    public static function enforce(): void
    {
        if (!self::validateRequest()) {
            http_response_code(403);
            die(json_encode([
                'result' => false,
                'error' => ['code' => 'CSRF', 'message' => 'CSRF token missing or invalid']
            ]));
        }
    }
}
