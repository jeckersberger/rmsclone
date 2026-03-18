<?php
declare(strict_types=1);

/**
 * SmartAssetLookupService - AI-powered asset data enrichment
 *
 * Provides intelligent asset lookup combining:
 * - Cache (30-day TTL)
 * - AI providers (vision, web search)
 * - Price suggestions
 * - Category suggestions
 * - User correction feedback loop
 */
class SmartAssetLookupService
{
    private const CACHE_TTL_DAYS = 30;
    private const RENTAL_PRICE_FACTOR = 1.15; // 15% markup as default

    public function __construct(
        private $db,
        private AiRequestHandler $aiHandler,
    ) {}

    /**
     * Main lookup: manufacturer + model → enriched asset data
     *
     * @return AssetLookupResult
     * @throws Exception
     */
    public function lookup(string $manufacturer, string $model, ?string $ean = null, int $instanceId = 1): AssetLookupResult
    {
        // Check cache first (30 day TTL)
        $cached = $this->getCachedLookup($manufacturer, $model, $instanceId);
        if ($cached) {
            return $cached;
        }

        // Fall back to AI web search
        $prompt = $this->buildLookupPrompt($manufacturer, $model);

        try {
            $response = $this->aiHandler->processRequest(
                taskType: 'asset_lookup',
                prompt: $prompt,
                options: ['max_tokens' => 1500],
                instanceId: $instanceId,
            );

            $parsed = $this->parseAiResponse($response->content);

            // Build result with source tracking
            $result = $this->buildResultFromAiResponse($parsed, $manufacturer, $model);

            // Cache the result
            $this->saveLookupCache($result, $manufacturer, $model, $ean, $instanceId);

            return $result;
        } catch (Exception $e) {
            error_log("[SmartAssetLookup] Lookup failed: {$e->getMessage()}");

            // Return minimal result with basic info
            return new AssetLookupResult(
                name: "{$manufacturer} {$model}",
                manufacturer: $manufacturer,
                model: $model,
                overallConfidence: 0.0,
                sources: [
                    'name' => ['source' => 'db', 'confidence' => 0.5],
                ]
            );
        }
    }

    /**
     * Lookup from product image (vision-capable provider)
     *
     * @return AssetLookupResult
     * @throws Exception
     */
    public function lookupFromPhoto(string $imagePath, int $instanceId = 1): AssetLookupResult
    {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: {$imagePath}");
        }

        // Read image and encode as base64
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

        $prompt = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mimeType,
                            'data' => $imageData,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Bitte identifiziere den Hersteller und das Modell dieses Geräts. Antworte in JSON: {"manufacturer": "...", "model": "..."}',
                    ],
                ],
            ],
        ];

        try {
            $response = $this->aiHandler->processRequest(
                taskType: 'asset_lookup_photo',
                prompt: $prompt,
                options: [
                    'max_tokens' => 500,
                    'system' => 'Du bist ein Experte für Veranstaltungstechnik. Identifiziere den Hersteller und das Modell basierend auf Bildern.',
                ],
                instanceId: $instanceId,
            );

            $parsed = json_decode($response->content, true);
            if (!$parsed || empty($parsed['manufacturer']) || empty($parsed['model'])) {
                throw new Exception('Could not extract manufacturer/model from image');
            }

            // Now do standard lookup with extracted info
            return $this->lookup($parsed['manufacturer'], $parsed['model'], null, $instanceId);
        } catch (Exception $e) {
            error_log("[SmartAssetLookupPhoto] Failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Bulk lookup for multiple items (background job)
     *
     * Returns immediately with job ID; results available via polling
     *
     * @param array $items Array of {manufacturer, model, ean?}
     * @return array ['jobId' => 'uuid', 'itemsCount' => int]
     */
    public function bulkLookup(array $items, int $instanceId = 1): array
    {
        $jobId = bin2hex(random_bytes(32));

        // Store job record
        $this->db->insert('smart_lookup_jobs', [
            'id' => $jobId,
            'instances_id' => $instanceId,
            'status' => 'pending',
            'items_count' => count($items),
            'completed_count' => 0,
            'payload' => json_encode(['items' => $items]),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // TODO: Queue for background processing via AiActionQueueService
        // For now, process synchronously
        $this->processBulkLookupJob($jobId, $instanceId);

        return [
            'jobId' => $jobId,
            'itemsCount' => count($items),
        ];
    }

    /**
     * Get bulk lookup job status and results
     */
    public function getBulkLookupStatus(string $jobId, int $instanceId = 1): ?array
    {
        $this->db->where('id', $jobId);
        $this->db->where('instances_id', $instanceId);
        $job = $this->db->getOne('smart_lookup_jobs');

        if (!$job) {
            return null;
        }

        $result = [
            'jobId' => $job['id'],
            'status' => $job['status'],
            'itemsCount' => $job['items_count'],
            'completedCount' => $job['completed_count'],
            'progress' => $job['items_count'] > 0 ? round(($job['completed_count'] / $job['items_count']) * 100) : 0,
        ];

        if ($job['status'] === 'completed' && $job['results']) {
            $result['results'] = json_decode($job['results'], true);
        }

        if ($job['status'] === 'failed') {
            $result['error'] = $job['error_message'];
        }

        return $result;
    }

    /**
     * Suggest rental price based on new price and category
     */
    public function suggestRentalPrice(float $newPrice, string $category, int $instanceId = 1): float
    {
        // Check internal price history for similar category
        $this->db->where('assetCategories_name', $category);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assetCategories_deleted', 0);
        $categoryRecord = $this->db->getOne('assetCategories', null, ['assetCategories_id']);

        if ($categoryRecord) {
            // Average rental price for category
            $this->db->where('assetCategories_id', $categoryRecord['assetCategories_id']);
            $this->db->where('instances_id', $instanceId);
            $avgPrice = $this->db->getOne(
                'assetTypes',
                null,
                ['AVG(assetTypes_rentalDayPrice) as avg_price']
            );

            if ($avgPrice && $avgPrice['avg_price']) {
                return (float) $avgPrice['avg_price'];
            }
        }

        // Default: apply markup factor to new price
        return round($newPrice * self::RENTAL_PRICE_FACTOR, 2);
    }

    /**
     * Suggest category + subcategory from existing system
     *
     * @return array ['category' => 'Lighting', 'categoryId' => int, 'suggestions' => []]
     */
    public function suggestCategory(string $name, string $description, int $instanceId = 1): array
    {
        // Use AI to categorize
        $prompt = "Basierend auf dem Produktnamen \"{$name}\" und der Beschreibung \"{$description}\" " .
                  "welche Veranstaltungstechnik-Kategorie passt am besten? " .
                  "Antworte nur mit dem Kategorienamen (z.B. 'Beleuchtung', 'Audio', 'Video', 'Truss', 'Provisioning', 'Sonstiges').";

        try {
            $response = $this->aiHandler->processRequest(
                taskType: 'asset_categorize',
                prompt: $prompt,
                options: ['max_tokens' => 100],
                instanceId: $instanceId,
            );

            $suggestedCategory = trim($response->content);

            // Find matching category in database
            $this->db->where('instances_id', $instanceId);
            $this->db->where('assetCategories_deleted', 0);
            $this->db->orderBy('assetCategories_name', 'ASC');
            $categories = $this->db->get('assetCategories', null, ['assetCategories_id', 'assetCategories_name']);

            $matched = null;
            foreach ($categories as $cat) {
                if (stripos($cat['assetCategories_name'], $suggestedCategory) !== false ||
                    stripos($suggestedCategory, $cat['assetCategories_name']) !== false) {
                    $matched = $cat;
                    break;
                }
            }

            return [
                'category' => $matched['assetCategories_name'] ?? $suggestedCategory,
                'categoryId' => $matched['assetCategories_id'] ?? null,
                'suggestions' => array_slice($categories, 0, 5),
            ];
        } catch (Exception $e) {
            error_log("[SmartAssetLookupCategory] Failed: {$e->getMessage()}");

            // Return all categories as suggestions
            $this->db->where('instances_id', $instanceId);
            $this->db->where('assetCategories_deleted', 0);
            $this->db->orderBy('assetCategories_name', 'ASC');
            $categories = $this->db->get('assetCategories', 20, ['assetCategories_id', 'assetCategories_name']);

            return [
                'category' => null,
                'categoryId' => null,
                'suggestions' => $categories ?: [],
            ];
        }
    }

    /**
     * Record user correction for learning
     */
    public function recordCorrection(
        int $cacheId,
        string $field,
        string $originalValue,
        string $correctedValue,
        int $userId,
        int $instanceId = 1
    ): void {
        $this->db->insert('asset_lookup_corrections', [
            'instances_id' => $instanceId,
            'cache_id' => $cacheId,
            'field_name' => $field,
            'original_value' => $originalValue,
            'corrected_value' => $correctedValue,
            'corrected_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get cached lookup result
     */
    public function getCachedLookup(string $manufacturer, string $model, int $instanceId = 1): ?AssetLookupResult
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('manufacturer', $manufacturer);
        $this->db->where('model', $model);
        $this->db->where('expires_at', date('Y-m-d'), '>=');
        $cached = $this->db->getOne('asset_lookup_cache', null, ['id', 'data_json', 'sources', 'confidence']);

        if ($cached) {
            $data = json_decode($cached['data_json'], true);
            if ($data) {
                $result = AssetLookupResult::fromArray($data);
                $result->sources = json_decode($cached['sources'] ?? '{}', true);
                $result->overallConfidence = (float) $cached['confidence'];
                return $result;
            }
        }

        return null;
    }

    /**
     * Clear cache entry
     */
    public function clearCache(string $manufacturer, string $model, int $instanceId = 1): bool
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('manufacturer', $manufacturer);
        $this->db->where('model', $model);
        return $this->db->delete('asset_lookup_cache') > 0;
    }

    /**
     * Autocomplete: manufacturer suggestions
     */
    public function getManufacturerSuggestions(string $query, int $instanceId = 1): array
    {
        $term = '%' . $this->db->escape($query) . '%';

        $this->db->where('instances_id', $instanceId);
        $this->db->where('manufacturers_name', $term, 'LIKE');
        $this->db->orderBy('manufacturers_name', 'ASC');
        $results = $this->db->get('manufacturers', 10, ['manufacturers_id', 'manufacturers_name']);

        return array_map(fn($m) => [
            'id' => $m['manufacturers_id'],
            'name' => $m['manufacturers_name'],
        ], $results ?: []);
    }

    /**
     * Autocomplete: model suggestions for manufacturer
     */
    public function getModelSuggestions(string $manufacturer, string $query, int $instanceId = 1): array
    {
        $term = '%' . $this->db->escape($query) . '%';

        $this->db->where('instances_id', $instanceId);
        $this->db->join('manufacturers', 'manufacturers.manufacturers_id=assetTypes.manufacturers_id', 'LEFT');
        $this->db->where('manufacturers.manufacturers_name', $manufacturer);
        $this->db->where('assetTypes.assetTypes_name', $term, 'LIKE');
        $this->db->where('assetTypes.assetTypes_deleted', 0);
        $this->db->orderBy('assetTypes.assetTypes_name', 'ASC');
        $results = $this->db->get('assetTypes', 10, ['assetTypes_id', 'assetTypes_name']);

        return array_map(fn($a) => [
            'id' => $a['assetTypes_id'],
            'name' => $a['assetTypes_name'],
        ], $results ?: []);
    }

    // ── Private Methods ──

    /**
     * Build lookup prompt for AI
     */
    private function buildLookupPrompt(string $manufacturer, string $model): array
    {
        $userMessage = "Recherchiere detaillierte Produktdaten für: {$manufacturer} {$model}\n\n" .
                       "Antworte mit einem vollständigen JSON-Objekt mit diesen Feldern:\n" .
                       "- name (offizieller Produktname)\n" .
                       "- weight_kg (Gewicht in Kilogramm)\n" .
                       "- dimensions_mm (Abmessungen als 'LxBxH', z.B. '1200x300x150')\n" .
                       "- power_watts (Stromverbrauch in Watt)\n" .
                       "- light_source (wenn Beleuchtung: LED, Halogen, etc.)\n" .
                       "- color_temp (bei Beleuchtung: Farbtemperatur in Kelvin, z.B. 5600)\n" .
                       "- beam_angle (bei Scheinwerfern: Strahlwinkel in Grad)\n" .
                       "- protection_class (IP-Schutzart, z.B. IP65)\n" .
                       "- voltage (Betriebsspannung, z.B. '230V')\n" .
                       "- connectors (Anschlüsse, z.B. 'Schuko, DMX5, RJ45')\n" .
                       "- dmx_channels (bei DMX-Geräten: Kanalzahl)\n" .
                       "- new_price_eur (UVP/Marktwert in EUR)\n" .
                       "- description (Kurzbeschreibung auf Deutsch, 2-3 Sätze)\n" .
                       "- category_suggestion (kategorie der Veranstaltungstechnik)\n" .
                       "- accessories (empfohlenes Zubehör als Array)\n" .
                       "- confidence (0.0-1.0: Konfidenz der Daten)\n\n" .
                       "Falls ein Feld nicht verfügbar ist, nutze null.";

        return [
            [
                'role' => 'system',
                'content' => 'Du bist ein Experte für Veranstaltungstechnik und Produktinformationen. ' .
                            'Antworte ausschließlich als valides JSON, ohne zusätzliche Erklärungen oder Markdown.',
            ],
            [
                'role' => 'user',
                'content' => $userMessage,
            ],
        ];
    }

    /**
     * Parse AI response JSON
     */
    private function parseAiResponse(string $response): array
    {
        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Build AssetLookupResult from AI response
     */
    private function buildResultFromAiResponse(array $aiData, string $manufacturer, string $model): AssetLookupResult
    {
        // Build sources tracking
        $sources = [];
        foreach ($aiData as $field => $value) {
            if ($value !== null) {
                $sources[$field] = ['source' => 'ai', 'confidence' => $aiData['confidence'] ?? 0.8];
            }
        }

        return new AssetLookupResult(
            name: $aiData['name'] ?? "{$manufacturer} {$model}",
            manufacturer: $manufacturer,
            model: $model,
            category: $aiData['category_suggestion'] ?? null,
            weightKg: isset($aiData['weight_kg']) ? (float) $aiData['weight_kg'] : null,
            dimensions: $aiData['dimensions_mm'] ?? null,
            powerWatts: isset($aiData['power_watts']) ? (int) $aiData['power_watts'] : null,
            newPrice: isset($aiData['new_price_eur']) ? (float) $aiData['new_price_eur'] : null,
            description: $aiData['description'] ?? null,
            technicalSpecs: [
                'light_source' => $aiData['light_source'] ?? null,
                'color_temp' => $aiData['color_temp'] ?? null,
                'beam_angle' => $aiData['beam_angle'] ?? null,
                'protection_class' => $aiData['protection_class'] ?? null,
                'voltage' => $aiData['voltage'] ?? null,
                'connectors' => $aiData['connectors'] ?? null,
                'dmx_channels' => $aiData['dmx_channels'] ?? null,
            ],
            accessories: $aiData['accessories'] ?? [],
            sources: $sources,
            overallConfidence: (float) ($aiData['confidence'] ?? 0.8),
        );
    }

    /**
     * Save lookup result to cache
     */
    private function saveLookupCache(
        AssetLookupResult $result,
        string $manufacturer,
        string $model,
        ?string $ean,
        int $instanceId
    ): void {
        $this->db->insert('asset_lookup_cache', [
            'instances_id' => $instanceId,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'ean' => $ean,
            'data_json' => json_encode($result->toArray()),
            'sources' => json_encode($result->sources),
            'confidence' => $result->overallConfidence,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d', strtotime('+' . self::CACHE_TTL_DAYS . ' days')),
        ]);
    }

    /**
     * Process bulk lookup job synchronously for now
     * TODO: Move to background queue
     */
    private function processBulkLookupJob(string $jobId, int $instanceId): void
    {
        $this->db->where('id', $jobId);
        $job = $this->db->getOne('smart_lookup_jobs');

        if (!$job) {
            return;
        }

        $payload = json_decode($job['payload'], true);
        $items = $payload['items'] ?? [];
        $results = [];

        $this->db->where('id', $jobId);
        $this->db->update('smart_lookup_jobs', [
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($items as $idx => $item) {
            try {
                $result = $this->lookup(
                    $item['manufacturer'] ?? '',
                    $item['model'] ?? '',
                    $item['ean'] ?? null,
                    $instanceId
                );
                $results[] = $result->toArray();
            } catch (Exception $e) {
                $results[] = ['error' => $e->getMessage()];
            }

            // Update progress
            $this->db->where('id', $jobId);
            $this->db->update('smart_lookup_jobs', [
                'completed_count' => $idx + 1,
            ]);
        }

        // Mark complete
        $this->db->where('id', $jobId);
        $this->db->update('smart_lookup_jobs', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'results' => json_encode($results),
        ]);
    }
}
