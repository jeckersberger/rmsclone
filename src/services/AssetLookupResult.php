<?php
declare(strict_types=1);

/**
 * AssetLookupResult - Value object representing AI asset lookup results
 *
 * Immutable data structure returned by SmartAssetLookupService
 * Contains both basic asset info and detailed technical specifications
 * Tracks confidence per field and sources (AI, DB, Web)
 */
class AssetLookupResult
{
    public function __construct(
        public string $name,
        public string $manufacturer,
        public string $model,
        public ?string $category = null,
        public ?string $categorySubId = null,
        public ?float $weightKg = null,
        public ?string $dimensions = null, // "LxWxH" format in mm
        public ?int $powerWatts = null,
        public ?float $newPrice = null,
        public ?float $suggestedRentalPrice = null,
        public ?string $description = null,
        public ?string $imageUrl = null,
        public array $technicalSpecs = [], // key-value pairs: power_source, connectors, etc.
        public array $accessories = [],     // array of accessory names/descriptions
        public array $sources = [],         // [field => ['source' => 'ai|db|web', 'confidence' => 0.95]]
        public float $overallConfidence = 0.0,
    ) {}

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'category' => $this->category,
            'categorySubId' => $this->categorySubId,
            'weightKg' => $this->weightKg,
            'dimensions' => $this->dimensions,
            'powerWatts' => $this->powerWatts,
            'newPrice' => $this->newPrice,
            'suggestedRentalPrice' => $this->suggestedRentalPrice,
            'description' => $this->description,
            'imageUrl' => $this->imageUrl,
            'technicalSpecs' => $this->technicalSpecs,
            'accessories' => $this->accessories,
            'sources' => $this->sources,
            'overallConfidence' => $this->overallConfidence,
        ];
    }

    /**
     * Get confidence for a specific field
     */
    public function getFieldConfidence(string $field): float
    {
        return $this->sources[$field]['confidence'] ?? 0.0;
    }

    /**
     * Get source for a specific field
     */
    public function getFieldSource(string $field): ?string
    {
        return $this->sources[$field]['source'] ?? null;
    }

    /**
     * Create from cached array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            manufacturer: $data['manufacturer'] ?? '',
            model: $data['model'] ?? '',
            category: $data['category'] ?? null,
            categorySubId: $data['categorySubId'] ?? null,
            weightKg: isset($data['weightKg']) ? (float) $data['weightKg'] : null,
            dimensions: $data['dimensions'] ?? null,
            powerWatts: isset($data['powerWatts']) ? (int) $data['powerWatts'] : null,
            newPrice: isset($data['newPrice']) ? (float) $data['newPrice'] : null,
            suggestedRentalPrice: isset($data['suggestedRentalPrice']) ? (float) $data['suggestedRentalPrice'] : null,
            description: $data['description'] ?? null,
            imageUrl: $data['imageUrl'] ?? null,
            technicalSpecs: $data['technicalSpecs'] ?? [],
            accessories: $data['accessories'] ?? [],
            sources: $data['sources'] ?? [],
            overallConfidence: (float) ($data['overallConfidence'] ?? 0.0),
        );
    }
}
