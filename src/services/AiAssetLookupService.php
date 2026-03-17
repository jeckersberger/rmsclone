<?php
/**
 * AiAssetLookupService - KI-basierte Asset-Daten-Suche
 *
 * Sucht online nach Produktdaten wenn ein neues Asset angelegt wird:
 * - Neupreis / Marktwert
 * - Spezifikationen (Gewicht, Abmessungen, Leistung)
 * - Kategorie-Vorschlag
 *
 * Nutzt den zentralen ClaudeService fuer alle KI-Aufrufe (Messages API v1).
 * Feature-Flag: 'asset_lookup'
 * API-Key, Usage-Logging und Feature-Gating laufen ueber ClaudeService.
 */
class AiAssetLookupService
{
    private $db;
    private $claudeService;

    public function __construct($db, ClaudeService $claudeService)
    {
        $this->db = $db;
        $this->claudeService = $claudeService;
    }

    /**
     * Produktdaten per KI suchen
     *
     * @param string $productName Name des Geraets
     * @param string $manufacturer Hersteller (optional)
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'cached' => bool|null]
     */
    public function lookup(string $productName, string $manufacturer = ''): array
    {
        // Pruefen ob Asset-Lookup aktiviert ist
        if (!$this->claudeService->isFeatureEnabled('asset_lookup')) {
            return ['success' => false, 'error' => 'Asset-Lookup-Feature nicht aktiviert'];
        }

        // Zuerst Cache pruefen (7 Tage gueltig)
        $cached = $this->getFromCache($productName, $manufacturer);
        if ($cached) return $cached;

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($productName, $manufacturer);

        try {
            // Nutze ClaudeService statt eigenstaendiger cURL-Aufrufe
            // Usage-Logging und Kostenberechnung laufen automatisch im Service
            $response = $this->claudeService->ask(
                'asset_lookup',
                $systemPrompt,
                $userMessage,
                null,
                1000  // max_tokens fuer JSON-Antwort
            );

            if (!$response) {
                return ['success' => false, 'error' => 'KI-Anfrage fehlgeschlagen'];
            }

            $text = ClaudeService::extractText($response);
            if (!$text) {
                return ['success' => false, 'error' => 'Keine Antwort von Claude erhalten'];
            }

            $parsed = $this->parseResponse($text);
            $parsed['source'] = 'ai';
            $parsed['suggested'] = true;
            $this->saveToCache($productName, $manufacturer, $parsed);
            return ['success' => true, 'data' => $parsed];
        } catch (Exception $e) {
            error_log('[AiAssetLookup] Fehler: ' . $e->getMessage());
            return ['success' => false, 'error' => 'KI-Anfrage fehlgeschlagen'];
        }
    }

    /**
     * Verfuegbare Kategorien fuer Vorschlaege
     */
    public function getCategories(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assetCategories_deleted', 0);
        $this->db->orderBy('assetCategories_name', 'ASC');
        return $this->db->get('assetCategories', null, ['assetCategories_id', 'assetCategories_name']) ?: [];
    }

    // ── Private Methods ──

    private function buildSystemPrompt(): string
    {
        return "Du bist ein Assistent fuer Veranstaltungstechnik-Vermietung und Inventarverwaltung. " .
               "Deine Aufgabe ist es, genaue technische Spezifikationen und Preise fuer Veranstaltungstechnik-Geraete zu recherchieren. " .
               "Antworte AUSSCHLIESSLICH als valides JSON, ohne zusaetzliche Erklaerungen oder Markdown.";
    }

    private function buildUserMessage(string $name, string $manufacturer): string
    {
        $deviceSpec = $manufacturer ? "{$manufacturer} {$name}" : $name;

        return "Recherchiere fuer das Veranstaltungstechnik-Geraeт \"{$deviceSpec}\" die folgenden Daten:\n" .
               "- Gewicht in Kilogramm (weight_kg)\n" .
               "- UVP/Verkaufspreis in EUR (new_price_eur)\n" .
               "- Abmessungen (Laenge x Breite x Hoehe in mm, z.B. '1200x300x150')\n" .
               "- Leistungsaufnahme in Watt (power_consumption_watts)\n" .
               "- Kurzenbeschreibung (2-3 Saetze)\n" .
               "- Konfidenzlevel: 'high' wenn du sichere Daten gefunden hast, 'medium' bei Schaetzungen, 'low' wenn Daten unsicher sind\n\n" .
               "Antworte mit diesem JSON-Struktur:\n" .
               "{\n" .
               "  \"product_name\": \"Offizieller Produktname\",\n" .
               "  \"manufacturer\": \"Hersteller\",\n" .
               "  \"new_price_eur\": 0.00,\n" .
               "  \"weight_kg\": 0.0,\n" .
               "  \"dimensions_mm\": \"LxBxH\",\n" .
               "  \"power_consumption_watts\": 0,\n" .
               "  \"description\": \"Kurzbeschreibung\",\n" .
               "  \"confidence\": \"high|medium|low\"\n" .
               "}\n\n" .
               "Falls keine validen Daten vorhanden sind, nutze 'low' als Konfidenzlevel.";
    }

    private function parseResponse(string $response): array
    {
        // JSON aus der Antwort extrahieren
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if ($data) return $data;
        }

        // Fallback auf Default-Struktur
        return [
            'product_name' => '',
            'manufacturer' => '',
            'new_price_eur' => 0,
            'weight_kg' => 0,
            'dimensions_mm' => '',
            'power_consumption_watts' => 0,
            'description' => '',
            'confidence' => 'low',
        ];
    }

    private function getFromCache(string $name, string $manufacturer): ?array
    {
        $key = md5(strtolower($name . '|' . $manufacturer));
        $this->db->where('cache_key', $key);
        $this->db->where('created_at', date('Y-m-d H:i:s', strtotime('-7 days')), '>=');
        $cached = $this->db->getOne('ai_lookup_cache', null, ['response_data']);

        if (!$cached) return null;

        $data = json_decode($cached['response_data'], true);
        return $data ? ['success' => true, 'data' => $data, 'cached' => true] : null;
    }

    private function saveToCache(string $name, string $manufacturer, array $data): void
    {
        $key = md5(strtolower($name . '|' . $manufacturer));
        $this->db->insert('ai_lookup_cache', [
            'cache_key' => $key,
            'product_name' => $name,
            'manufacturer' => $manufacturer,
            'response_data' => json_encode($data),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
