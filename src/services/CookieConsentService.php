<?php
/**
 * Cookie-Consent-Service (DSGVO Art. 6 Abs. 1 lit. a)
 *
 * Verwaltet Cookie-Einwilligungen der Benutzer.
 * Kategorien:
 *   - necessary: Immer aktiv (Session, CSRF-Token)
 *   - analytics: Nutzungsstatistiken
 *   - marketing: Werbe-Cookies (aktuell nicht verwendet)
 */
class CookieConsentService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Consent speichern
     */
    public function saveConsent(int $instanceId, string $sessionId, array $categories, ?int $userId = null): int
    {
        // Bestehenden Consent fuer diese Session loeschen
        $this->db->where('instances_id', $instanceId);
        $this->db->where('session_id', $sessionId);
        $this->db->delete('cookie_consents');

        return $this->db->insert('cookie_consents', [
            'instances_id' => $instanceId,
            'session_id' => $sessionId,
            'users_userid' => $userId,
            'consent_given' => true,
            'consent_categories' => implode(',', $categories),
            'ip_address' => self::getClientIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'consented_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+12 months')),
        ]);
    }

    /**
     * Consent pruefen
     */
    public function hasConsent(int $instanceId, string $sessionId, string $category = 'necessary'): bool
    {
        if ($category === 'necessary') return true;

        $this->db->where('instances_id', $instanceId);
        $this->db->where('session_id', $sessionId);
        $this->db->where('consent_given', true);
        $this->db->where('expires_at', date('Y-m-d H:i:s'), '>');
        $consent = $this->db->getOne('cookie_consents');

        if (!$consent) return false;

        $cats = explode(',', $consent['consent_categories']);
        return in_array($category, $cats);
    }

    /**
     * Consent widerrufen
     */
    public function revokeConsent(int $instanceId, string $sessionId): bool
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('session_id', $sessionId);
        return $this->db->update('cookie_consents', [
            'consent_given' => false,
        ]);
    }

    /**
     * Consent-Banner HTML-Snippet generieren (fuer Template-Integration)
     */
    public static function getBannerHtml(): string
    {
        return <<<'HTML'
<div id="cookieConsentBanner" style="display:none;position:fixed;bottom:0;left:0;right:0;background:#2c3e50;color:#fff;padding:15px 20px;z-index:99999;box-shadow:0 -2px 10px rgba(0,0,0,0.3);">
    <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <div style="flex:1;min-width:300px;">
            <strong>Cookie-Einstellungen</strong>
            <p style="margin:5px 0 0;font-size:13px;opacity:0.9;">
                Wir verwenden Cookies, um die Funktionalitaet der Anwendung sicherzustellen.
                Technisch notwendige Cookies sind immer aktiv.
                <a href="#" id="cookieConsentDetails" style="color:#3498db;">Mehr erfahren</a>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button onclick="cookieConsentAccept('necessary')" class="btn btn-outline-light btn-sm">Nur notwendige</button>
            <button onclick="cookieConsentAccept('all')" class="btn btn-success btn-sm">Alle akzeptieren</button>
        </div>
    </div>
</div>
<script>
(function() {
    var consent = localStorage.getItem('cookie_consent');
    if (!consent) {
        document.getElementById('cookieConsentBanner').style.display = 'block';
    }
})();
function cookieConsentAccept(level) {
    var categories = ['necessary'];
    if (level === 'all') categories.push('analytics', 'marketing');
    localStorage.setItem('cookie_consent', JSON.stringify({categories: categories, date: new Date().toISOString()}));
    document.getElementById('cookieConsentBanner').style.display = 'none';
    // Server-seitig speichern
    if (typeof ajaxcall === 'function') {
        ajaxcall('cookieConsent/save.php', {categories: categories.join(',')}, function(){}, false);
    }
}
</script>
HTML;
    }

    private static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
