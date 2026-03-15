<?php

/**
 * URL-Sicherheitsservice gegen SSRF (Server-Side Request Forgery).
 *
 * Prueft ob eine URL auf private/interne Netzwerke zeigt,
 * bevor ein ausgehender HTTP-Request gesendet wird.
 */
class UrlSecurityService
{
    /**
     * Private und reservierte IP-Bereiche (RFC 1918, RFC 5737, RFC 6598 etc.)
     */
    private static array $privateRanges = [
        '10.0.0.0/8',        // Class A private
        '172.16.0.0/12',     // Class B private
        '192.168.0.0/16',    // Class C private
        '127.0.0.0/8',       // Loopback
        '169.254.0.0/16',    // Link-local
        '0.0.0.0/8',         // "This" network
        '100.64.0.0/10',     // Carrier-grade NAT (RFC 6598)
        '192.0.0.0/24',      // IETF Protocol Assignments
        '192.0.2.0/24',      // TEST-NET-1
        '198.51.100.0/24',   // TEST-NET-2
        '203.0.113.0/24',    // TEST-NET-3
        '224.0.0.0/4',       // Multicast
        '240.0.0.0/4',       // Reserved
        '255.255.255.255/32', // Broadcast
    ];

    /**
     * IPv6 private Bereiche
     */
    private static array $privateRangesV6 = [
        '::1/128',           // Loopback
        'fc00::/7',          // Unique local
        'fe80::/10',         // Link-local
        '::ffff:0:0/96',     // IPv4-mapped (pruefen wir separat)
    ];

    /**
     * Prueft ob eine URL sicher fuer ausgehende Requests ist.
     *
     * @param string $url Die zu pruefende URL
     * @return array ['safe' => bool, 'reason' => string|null]
     */
    public static function validateUrl(string $url): array
    {
        // URL parsen
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return ['safe' => false, 'reason' => 'invalid_url'];
        }

        $host = $parsed['host'];
        $scheme = strtolower($parsed['scheme'] ?? '');

        // Nur HTTP(S) erlauben
        if (!in_array($scheme, ['http', 'https'])) {
            return ['safe' => false, 'reason' => 'invalid_scheme'];
        }

        // DNS-Aufloesung um IP zu erhalten (verhindert DNS-Rebinding mit bekannten Hostnamen)
        $ips = gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            // Versuche direkt als IP zu parsen
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $ips = [$host];
            } else {
                return ['safe' => false, 'reason' => 'dns_resolution_failed'];
            }
        }

        // Jede aufgeloeste IP pruefen
        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return ['safe' => false, 'reason' => 'private_ip_blocked: ' . $ip];
            }
        }

        // Bekannte problematische Hostnamen
        $lowerHost = strtolower($host);
        $blocked = ['localhost', 'metadata.google.internal', '169.254.169.254'];
        foreach ($blocked as $blockedHost) {
            if ($lowerHost === $blockedHost) {
                return ['safe' => false, 'reason' => 'blocked_hostname: ' . $host];
            }
        }

        return ['safe' => true, 'reason' => null];
    }

    /**
     * Prueft ob eine IP-Adresse privat/reserviert ist.
     */
    public static function isPrivateIp(string $ip): bool
    {
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::$privateRanges as $range) {
                if (self::ipInRange($ip, $range)) {
                    return true;
                }
            }
            return false;
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv4-mapped IPv6 (::ffff:x.x.x.x)
            if (substr($ip, 0, 7) === '::ffff:') {
                $ipv4 = substr($ip, 7);
                if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return self::isPrivateIp($ipv4);
                }
            }
            // Einfache Pruefung fuer bekannte private IPv6
            foreach (self::$privateRangesV6 as $range) {
                // Vereinfachte Pruefung: Loopback und Link-Local
                if ($ip === '::1') return true;
                if (stripos($ip, 'fc') === 0 || stripos($ip, 'fd') === 0) return true;
                if (stripos($ip, 'fe80:') === 0) return true;
            }
        }

        return false;
    }

    /**
     * Prueft ob eine IPv4-Adresse in einem CIDR-Bereich liegt.
     */
    private static function ipInRange(string $ip, string $cidr): bool
    {
        [$range, $bits] = explode('/', $cidr);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $mask = -1 << (32 - (int)$bits);
        return ($ipDecimal & $mask) === ($rangeDecimal & $mask);
    }
}
