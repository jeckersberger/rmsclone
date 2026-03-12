<?php
/**
 * AiAssetLookupService - KI-basierte Asset-Daten-Suche
 *
 * Sucht online nach Produktdaten wenn ein neues Asset angelegt wird:
 * - Neupreis / Marktwert
 * - Spezifikationen (Gewicht, Abmessungen)
 * - Kategorie-Vorschlag
 *
 * Unterstuetzt Claude API oder OpenAI API als Backend.
 */
class AiAssetLookupService
{
    private $db;
    private $apiKey;
    private $apiProvider;

    public function __construct($db)
    {
        $this->db = $db;
        $this->apiKey = getenv('AI_API_KEY') ?: '';
        $this->apiProvider = getenv('AI_PROVIDER') ?: 'claude'; // 'claude' oder 'openai'
    }

    /**
     * Produktdaten per KI suchen
     */
    public function lookup(string $productName, string $manufacturer = ''): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'KI-API-Key nicht konfiguriert (AI_API_KEY)'];
        }

        // Zuerst Cache pruefen
        $cached = $this->getFromCache($productName, $manufacturer);
        if ($cached) return $cached;

        $prompt = $this->buildPrompt($productName, $manufacturer);

        try {
            $result = $this->apiProvider === 'openai'
                ? $this->callOpenAi($prompt)
                : $this->callClaude($prompt);

            if ($result['success']) {
                $parsed = $this->parseResponse($result['response']);
                $parsed['source'] = 'ai';
                $parsed['suggested'] = true;
                $this->saveToCache($productName, $manufacturer, $parsed);
                return ['success' => true, 'data' => $parsed];
            }

            return $result;
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

    private function buildPrompt(string $name, string $manufacturer): string
    {
        $query = $manufacturer ? "{$manufacturer} {$name}" : $name;
        return "Du bist ein Assistent fuer Veranstaltungstechnik-Vermietung. " .
            "Finde die folgenden Informationen ueber dieses Produkt: \"{$query}\"\n\n" .
            "Antworte NUR im folgenden JSON-Format (keine weiteren Erklaerungen):\n" .
            "{\n" .
            "  \"product_name\": \"Offizieller Produktname\",\n" .
            "  \"manufacturer\": \"Hersteller\",\n" .
            "  \"new_price_eur\": 0.00,\n" .
            "  \"market_value_eur\": 0.00,\n" .
            "  \"weight_kg\": 0.0,\n" .
            "  \"dimensions\": \"LxBxH in cm\",\n" .
            "  \"category\": \"Lichtequipment|Tontechnik|Videotechnik|Buehnenelemente|Traversensysteme|Sonstiges\",\n" .
            "  \"description\": \"Kurzbeschreibung (1-2 Saetze)\",\n" .
            "  \"confidence\": \"high|medium|low\"\n" .
            "}\n\n" .
            "Falls du das Produkt nicht findest, setze confidence auf 'low' und schaetze die Werte.";
    }

    private function callClaude(string $prompt): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "API-Fehler: HTTP $httpCode"];
        }

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '';
        return ['success' => true, 'response' => $text];
    }

    private function callOpenAi(string $prompt): array
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 500,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "API-Fehler: HTTP $httpCode"];
        }

        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'response' => $text];
    }

    private function parseResponse(string $response): array
    {
        // JSON aus der Antwort extrahieren
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if ($data) return $data;
        }

        return [
            'product_name' => '',
            'manufacturer' => '',
            'new_price_eur' => 0,
            'market_value_eur' => 0,
            'weight_kg' => 0,
            'dimensions' => '',
            'category' => 'Sonstiges',
            'description' => '',
            'confidence' => 'low',
        ];
    }

    private function getFromCache(string $name, string $manufacturer): ?array
    {
        $key = md5(strtolower($name . '|' . $manufacturer));
        $this->db->where('cache_key', $key);
        $this->db->where('created_at', date('Y-m-d H:i:s', strtotime('-7 days')), '>=');
        $cached = $this->db->getOne('ai_lookup_cache', ['response_data']);

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
