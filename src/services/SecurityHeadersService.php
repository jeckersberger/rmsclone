<?php
/**
 * Security Headers Service
 *
 * Setzt sicherheitsrelevante HTTP-Header fuer alle Responses:
 * - Content-Security-Policy (CSP)
 * - Strict-Transport-Security (HSTS)
 * - X-Frame-Options
 * - X-Content-Type-Options
 * - Referrer-Policy
 * - Permissions-Policy
 *
 * Aufruf: SecurityHeadersService::apply() in headSecure.php oder apiHeadSecure.php
 */
class SecurityHeadersService
{
    /**
     * Alle Security-Header setzen.
     *
     * @param array $options  Optionale Konfiguration
     *   - 'csp_nonce'     => string  Nonce fuer inline scripts (wird pro Request generiert)
     *   - 'frame_ancestors'=> string  Erlaubte Frame-Quellen (default: 'self')
     *   - 'report_uri'    => string  CSP-Report-URI (optional)
     */
    public static function apply(array $options = []): void
    {
        if (headers_sent()) return;

        $nonce = $options['csp_nonce'] ?? '';
        $frameAncestors = $options['frame_ancestors'] ?? "'self'";
        $reportUri = $options['report_uri'] ?? '';

        // Content-Security-Policy
        $cspParts = [
            "default-src 'self'",
            "script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net" . ($nonce ? " 'nonce-{$nonce}'" : " 'unsafe-inline'"),
            "style-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors {$frameAncestors}",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];
        if ($reportUri) {
            $cspParts[] = "report-uri {$reportUri}";
        }
        header('Content-Security-Policy: ' . implode('; ', $cspParts));

        // Strict-Transport-Security (1 Jahr, inkl. Subdomains)
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

        // X-Frame-Options (Fallback fuer aeltere Browser)
        header('X-Frame-Options: SAMEORIGIN');

        // MIME-Sniffing verhindern
        header('X-Content-Type-Options: nosniff');

        // XSS-Filter (Legacy-Browser)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions-Policy (kein Zugriff auf Kamera, Mikro, Geolocation etc.)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        // Cross-Origin-Opener-Policy
        header('Cross-Origin-Opener-Policy: same-origin');
    }

    /**
     * CSP-Nonce generieren fuer inline Scripts.
     */
    public static function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * API-spezifische Header setzen (kein CSP noetig, aber CORS).
     */
    public static function applyApi(array $options = []): void
    {
        if (headers_sent()) return;

        $allowedOrigin = $options['allowed_origin'] ?? '';

        // CORS-Header
        if ($allowedOrigin) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
        header('Access-Control-Max-Age: 86400');

        // Sicherheitsheader auch fuer API
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // Cache-Control fuer API-Responses
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
}
