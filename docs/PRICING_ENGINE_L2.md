# Flexible Preiskalkulations-Engine (L2) - Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Database Schema](#database-schema)
4. [Service Layer](#service-layer)
5. [API Endpoints](#api-endpoints)
6. [Frontend UI](#frontend-ui)
7. [Usage Examples](#usage-examples)
8. [Integration Guide](#integration-guide)

---

## Overview

The Flexible Preiskalkulations-Engine (L2) is a comprehensive pricing calculation system that supports:

- **Staffelpreise (Tier Pricing)** - Duration-based price adjustments
- **Mengenrabatte (Volume Discounts)** - Quantity-based percentage discounts
- **Saisonzuschläge (Seasonal Surcharges)** - Date range-based price increases
- **Paketpreise (Bundle Pricing)** - Pre-configured asset bundles with fixed daily rates
- **Kundenspezifische Preislisten (Customer Pricing)** - Per-customer and per-asset customization

### Key Features

- Real-time price calculation with detailed breakdown
- Multi-tenant support (instances_id)
- Permission-based access control
- CSRF protection on all mutations
- RESTful API design
- Responsive admin interface with inline editing
- Cascade delete support
- Proper date/time handling for seasonal pricing

---

## Architecture

### Component Stack

```
Frontend UI (Twig + JavaScript)
    ↓
API Endpoints (REST)
    ↓
Service Layer (PricingEngineService)
    ↓
Database Layer (MysqliDb + Phinx)
```

### Request Flow

1. **Calculator/Admin Interface** - User selects pricing parameters
2. **JavaScript Layer** - Validates input, formats data, handles UI state
3. **API Endpoint** - Receives request, validates permissions
4. **Service Layer** - Executes business logic, performs calculations
5. **Database** - CRUD operations on pricing tables
6. **Response** - JSON with calculated values and breakdown

---

## Database Schema

### pricing_tiers
Duration-based pricing levels.

```sql
CREATE TABLE pricing_tiers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_type_id INT UNSIGNED NOT NULL,
  min_days INT UNSIGNED NOT NULL,
  max_days INT UNSIGNED NULL,          -- NULL = unbounded
  price_per_day DECIMAL(10,2) NOT NULL,
  instances_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tier_range (asset_type_id, min_days, max_days),
  KEY idx_tiers_instance (instances_id)
);
```

**Example:**
- 1-6 days: 100 EUR/day
- 7-30 days: 80 EUR/day
- 31+ days: 60 EUR/day

### pricing_volume_discounts
Quantity-based percentage discounts.

```sql
CREATE TABLE pricing_volume_discounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  min_quantity INT UNSIGNED NOT NULL,
  max_quantity INT UNSIGNED NULL,
  discount_percent DECIMAL(5,2) NOT NULL,
  instances_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_volume_range (min_quantity, max_quantity),
  KEY idx_volume_instance (instances_id)
);
```

**Example:**
- 1-4 units: 0% discount
- 5-9 units: 5% discount
- 10+ units: 10% discount

### pricing_seasonal_surcharges
Date range-based price increases.

```sql
CREATE TABLE pricing_seasonal_surcharges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  start_month INT UNSIGNED NOT NULL,
  start_day INT UNSIGNED NOT NULL,
  end_month INT UNSIGNED NOT NULL,
  end_day INT UNSIGNED NOT NULL,
  surcharge_percent DECIMAL(5,2) NOT NULL,
  instances_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_surcharge_instance (instances_id)
);
```

**Example:**
- Summer Season (06-01 to 08-31): +20% surcharge
- Trade Fair (10-15 to 10-20): +15% surcharge

### pricing_bundles
Pre-configured asset packages with fixed pricing.

```sql
CREATE TABLE pricing_bundles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  bundle_price_per_day DECIMAL(10,2) NOT NULL,
  instances_id INT UNSIGNED NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bundle_active (instances_id, is_active)
);
```

### pricing_bundle_items
Items contained in a bundle.

```sql
CREATE TABLE pricing_bundle_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bundle_id INT UNSIGNED NOT NULL,
  asset_type_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bundle_id) REFERENCES pricing_bundles(id) ON DELETE CASCADE,
  KEY idx_bundle_items_bundle (bundle_id)
);
```

### pricing_customer_lists
Per-customer pricing configurations.

```sql
CREATE TABLE pricing_customer_lists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  discount_percent DECIMAL(5,2) NULL,  -- Global discount %
  name VARCHAR(100) NULL,
  instances_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_customer_list_lookup (client_id, instances_id)
);
```

### pricing_customer_list_items
Per-customer, per-asset custom pricing.

```sql
CREATE TABLE pricing_customer_list_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  list_id INT UNSIGNED NOT NULL,
  asset_type_id INT UNSIGNED NOT NULL,
  custom_price_per_day DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (list_id) REFERENCES pricing_customer_lists(id) ON DELETE CASCADE,
  KEY idx_customer_item_lookup (list_id, asset_type_id)
);
```

---

## Service Layer

### PricingEngineService

Located: `/src/services/PricingEngineService.php`

#### Main Methods

##### calculatePrice()
```php
public function calculatePrice(
    int $assetTypeId,
    int $days,
    int $quantity = 1,
    ?int $clientId = null,
    ?string $startDate = null,
    int $instanceId = 0
): PriceResult
```

Calculates total price applying all discount/surcharge rules in order:
1. Base price from asset type
2. Customer-specific pricing (overrides base)
3. Tier pricing (if no customer price)
4. Volume discounts (percentage reduction)
5. Seasonal surcharges (percentage increase)
6. Customer global discount

**Returns:** `PriceResult` object with breakdown

**Example:**
```php
$service = new PricingEngineService($db);
$result = $service->calculatePrice(
    assetTypeId: 5,
    days: 14,
    quantity: 3,
    clientId: 12,
    startDate: '2026-06-15',
    instanceId: 1
);

echo $result->final_price;  // Total cost
echo json_encode($result->breakdown);  // Detailed breakdown
```

#### Tier Pricing Methods

```php
// Get applicable tier for duration
public function getApplicableTier(int $assetTypeId, int $days, int $instanceId): ?array

// List all tiers for asset type
public function getTiersForAssetType(int $assetTypeId, int $instanceId): array

// Create or update tier
public function saveTier(array $data): int
// $data: ['asset_type_id', 'min_days', 'max_days', 'price_per_day', 'instances_id', 'id']

// Delete tier
public function deleteTier(int $id): bool
```

#### Volume Discount Methods

```php
// Get discount percent for quantity
public function getVolumeDiscountPercent(int $quantity, int $instanceId): float

// List all volume discounts
public function getVolumeDiscounts(int $instanceId): array

// Create or update volume discount
public function saveVolumeDiscount(array $data): int
// $data: ['min_quantity', 'max_quantity', 'discount_percent', 'instances_id', 'id']

// Delete volume discount
public function deleteVolumeDiscount(int $id): bool
```

#### Seasonal Surcharge Methods

```php
// List all seasonal surcharges
public function getSeasonalSurcharges(int $instanceId): array

// Get surcharge percent for date range
public function getSeasonalSurchargePercent(?string $startDate, int $days, int $instanceId): float

// Create or update seasonal surcharge
public function saveSurcharge(array $data): int
// $data: ['name', 'start_month', 'start_day', 'end_month', 'end_day', 'surcharge_percent', 'instances_id', 'id']

// Delete seasonal surcharge
public function deleteSurcharge(int $id): bool
```

#### Bundle Methods

```php
// List all active bundles
public function getBundles(int $instanceId): array

// Get single bundle with items
public function getBundle(int $bundleId): ?array

// Create or update bundle with items
public function saveBundle(array $bundleData, array $items): int
// $bundleData: ['name', 'description', 'bundle_price_per_day', 'is_active', 'instances_id', 'id']
// $items: [['asset_type_id' => 1, 'quantity' => 2], ...]

// Calculate bundle price
public function calculateBundlePrice(int $bundleId, int $days, ?int $clientId, ?string $startDate, int $instanceId): PriceResult

// Delete bundle
public function deleteBundle(int $id): bool
```

#### Customer Pricing Methods

```php
// Get customer price list with items
public function getCustomerPriceList(int $clientId, int $instanceId): ?array

// Get specific asset price for customer
public function getCustomerAssetPrice(int $clientId, int $assetTypeId, int $instanceId): ?float

// Get global discount for customer
public function getCustomerGlobalDiscount(int $clientId, int $instanceId): float

// Save customer price list
public function saveCustomerPriceList(int $clientId, array $items, ?float $globalDiscount, int $instanceId): int
// $items: [['asset_type_id' => 1, 'custom_price_per_day' => 75.00], ...]

// Delete customer price list
public function deleteCustomerPriceList(int $listId): bool
```

#### PriceResult Class

```php
class PriceResult {
    public float $base_price = 0;
    public float $tier_discount = 0;
    public float $volume_discount = 0;
    public float $seasonal_surcharge = 0;
    public float $customer_discount = 0;
    public float $final_price = 0;
    public array $breakdown = [];  // Human-readable description of each adjustment
}
```

---

## API Endpoints

All endpoints use:
- **Base URL:** `/src/api/pricing/`
- **Security:** CSRF token on POST, permission checks
- **Auth:** Requires authenticated user
- **Response:** JSON via `finish()` helper

### 1. Price Calculator

**POST** `/calculate.php`

Calculate price for given parameters.

**Request:**
```json
{
  "asset_type_id": 5,
  "days": 7,
  "quantity": 2,
  "client_id": 12,
  "start_date": "2026-06-15"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "base_price": 1400.00,
    "tier_discount": 0.00,
    "volume_discount": 70.00,
    "seasonal_surcharge": 280.00,
    "customer_discount": 0.00,
    "final_price": 1610.00,
    "breakdown": [
      "Volume discount (2 units): 5%",
      "Seasonal surcharge: 20%"
    ]
  }
}
```

### 2. Tier Pricing

**GET** `/tiers.php?asset_type_id=5`

List tiers for asset type.

**Response:**
```json
{
  "success": true,
  "data": {
    "tiers": [
      {
        "id": 1,
        "asset_type_id": 5,
        "min_days": 1,
        "max_days": 6,
        "price_per_day": "100.00",
        "instances_id": 1
      }
    ]
  }
}
```

**POST** `/tiers.php`

Create or update tier.

**Request:**
```json
{
  "asset_type_id": 5,
  "min_days": 7,
  "max_days": 30,
  "price_per_day": 80.00
}
```

**POST** `/tiers_delete.php`

Delete tier.

**Request:**
```json
{
  "id": 1
}
```

### 3. Volume Discounts

**GET** `/volume_discounts.php`

List all volume discounts.

**POST** `/volume_discounts.php`

Create or update volume discount.

**POST** `/volume_discounts_delete.php`

Delete volume discount.

### 4. Seasonal Surcharges

**GET** `/surcharges.php`

List all surcharges.

**POST** `/surcharges.php`

Create or update surcharge.

**Request:**
```json
{
  "name": "Summer High Season",
  "start_month": 6,
  "start_day": 1,
  "end_month": 8,
  "end_day": 31,
  "surcharge_percent": 20.00
}
```

**POST** `/surcharges_delete.php`

Delete surcharge.

### 5. Bundles

**GET** `/bundles.php`

List all active bundles.

**GET** `/bundles.php?id=1`

Get single bundle with items.

**POST** `/bundles.php`

Create or update bundle.

**Request:**
```json
{
  "name": "Studio Package",
  "description": "Complete studio setup",
  "bundle_price_per_day": 500.00,
  "is_active": true,
  "items": [
    {"asset_type_id": 1, "quantity": 2},
    {"asset_type_id": 3, "quantity": 1}
  ]
}
```

**POST** `/bundles_delete.php`

Delete bundle.

### 6. Customer Lists

**GET** `/customer_list.php?client_id=12`

Get customer pricing list.

**POST** `/customer_list.php`

Create or update customer pricing.

**Request:**
```json
{
  "client_id": 12,
  "global_discount": 10.00,
  "items": [
    {"asset_type_id": 1, "custom_price_per_day": 75.00},
    {"asset_type_id": 2, "custom_price_per_day": 150.00}
  ]
}
```

---

## Frontend UI

### Location
Navigate to: `/src/pricing/pricing_manage.php`

### Components

#### 1. Price Calculator Widget
- Select asset type, days, quantity
- Optional: customer and start date
- Real-time calculation display
- Detailed breakdown of all adjustments

#### 2. Staffelpreise (Tier Pricing)
- Filter by asset type
- Sortable table with edit/delete buttons
- Modal dialog for adding/editing
- Min/max days with optional unbounded ranges

#### 3. Mengenrabatte (Volume Discounts)
- Global rules (not per-asset)
- Table with quantity ranges and discount percentages
- Quick edit functionality

#### 4. Saisonzuschläge (Seasonal Surcharges)
- Month/day selection for date ranges
- Support for season spanning year boundary
- Named surcharges with percentage values
- Automatic overlap detection in calculator

#### 5. Pakete (Bundles)
- Name, description, daily price
- Add/remove items with quantities
- Active/inactive toggle
- Calculate bundle price with modifiers

#### 6. Kundenpreise (Customer Pricing)
- Select customer from dropdown
- Global discount percentage
- Per-asset custom pricing
- Bulk add/remove items

### JavaScript API (pricing_engine.js)

```javascript
// Initialize
const pricingEngine = new PricingEngine();

// Calculate price
pricingEngine.calculatePrice();  // Uses form values

// Tier operations
pricingEngine.loadTiers();
pricingEngine.saveTier();
pricingEngine.deleteTier(tierId);

// Volume discount operations
pricingEngine.loadVolumeDiscounts();
pricingEngine.saveVolumeDiscount();
pricingEngine.deleteVolumeDiscount(discountId);

// Surcharge operations
pricingEngine.loadSurcharges();
pricingEngine.saveSurcharge();
pricingEngine.deleteSurcharge(surchargeId);

// Bundle operations
pricingEngine.loadBundles();
pricingEngine.saveBundle();
pricingEngine.deleteBundle(bundleId);

// Customer pricing operations
pricingEngine.loadCustomerPricing(clientId);
pricingEngine.saveCustomerPricing();
```

---

## Usage Examples

### Example 1: Basic Price Calculation

```php
require_once 'src/services/PricingEngineService.php';

$db = /* database connection */;
$service = new PricingEngineService($db);

// Calculate price for 1 laptop stand for 5 days
$result = $service->calculatePrice(
    assetTypeId: 10,
    days: 5,
    instanceId: 1
);

echo "Total: " . number_format($result->final_price, 2) . " EUR";
```

### Example 2: Add Tier Pricing

```php
// Tier: 7-30 days at 80 EUR/day for asset type 10
$tierId = $service->saveTier([
    'asset_type_id' => 10,
    'min_days' => 7,
    'max_days' => 30,
    'price_per_day' => 80.00,
    'instances_id' => 1,
]);
```

### Example 3: Set Up Seasonal Surcharge

```php
// Summer season surcharge: June 1 - August 31, +20%
$surchargeId = $service->saveSurcharge([
    'name' => 'Summer High Season',
    'start_month' => 6,
    'start_day' => 1,
    'end_month' => 8,
    'end_day' => 31,
    'surcharge_percent' => 20.00,
    'instances_id' => 1,
]);
```

### Example 4: Create Bundle

```php
$bundleId = $service->saveBundle(
    bundleData: [
        'name' => 'Studio Complete',
        'description' => 'Full studio equipment package',
        'bundle_price_per_day' => 500.00,
        'is_active' => true,
        'instances_id' => 1,
    ],
    items: [
        ['asset_type_id' => 1, 'quantity' => 2],  // 2 cameras
        ['asset_type_id' => 5, 'quantity' => 1],  // 1 lighting kit
        ['asset_type_id' => 3, 'quantity' => 4],  // 4 tripods
    ]
);
```

### Example 5: Customer-Specific Pricing

```php
// Customer 12: 10% global discount + custom prices for specific assets
$listId = $service->saveCustomerPriceList(
    clientId: 12,
    items: [
        ['asset_type_id' => 1, 'custom_price_per_day' => 75.00],
        ['asset_type_id' => 5, 'custom_price_per_day' => 120.00],
    ],
    globalDiscount: 10.00,
    instanceId: 1
);

// Now customer 12 gets 75 EUR/day for asset 1 (not standard price)
$price = $service->calculatePrice(1, 5, 1, clientId: 12, instanceId: 1);
```

### Example 6: Volume Discount

```php
// 10+ units = 15% discount
$discountId = $service->saveVolumeDiscount([
    'min_quantity' => 10,
    'max_quantity' => null,
    'discount_percent' => 15.00,
    'instances_id' => 1,
]);

// Calculate with 12 units (applies 15% discount)
$price = $service->calculatePrice(5, 7, quantity: 12, instanceId: 1);
```

---

## Integration Guide

### Step 1: Run Migration

```bash
vendor/bin/phinx migrate
```

Creates all 7 pricing tables.

### Step 2: Add Permissions

Add to your permission system:
- `PRICING:VIEW` - View pricing rules and calculator
- `PRICING:EDIT` - Create/update/delete pricing rules

Example:
```php
// In permissions table
INSERT INTO permissions (permission_code, permission_name) VALUES
('PRICING:VIEW', 'View Pricing Rules'),
('PRICING:EDIT', 'Edit Pricing Rules');
```

### Step 3: Add Navigation

Add menu item to admin navigation linking to `/src/pricing/pricing_manage.php`:

```twig
<li class="nav-item">
    <a class="nav-link" href="/src/pricing/pricing_manage.php">
        <i class="fas fa-calculator"></i>
        <span>Pricing</span>
    </a>
</li>
```

### Step 4: Use in Quotes/Orders

When creating quotes or orders, use the API:

```javascript
// Calculate price before showing to customer
fetch('/src/api/pricing/calculate.php', {
    method: 'POST',
    body: new FormData({
        'asset_type_id': assetId,
        'days': rentalDays,
        'quantity': quantity,
        'client_id': customerId,
        'start_date': startDate
    })
})
.then(r => r.json())
.then(data => {
    console.log('Final price: ' + data.data.final_price);
});
```

### Step 5: Display in Templates

```twig
{# Use pricing engine to show calculated price #}
<div class="price-breakdown">
    <p>Base Price: {{ base_price|currency }}</p>
    {% if tier_discount > 0 %}
        <p class="discount">- Tier Discount: {{ tier_discount|currency }}</p>
    {% endif %}
    <strong>Total: {{ final_price|currency }}</strong>
</div>
```

### Step 6: Optional - Caching

For performance, cache pricing rules:

```php
// In service
private $cachedTiers = [];

public function getTiersForAssetType(int $assetTypeId, int $instanceId): array {
    $cacheKey = "tiers_{$instanceId}_{$assetTypeId}";
    if (isset($this->cachedTiers[$cacheKey])) {
        return $this->cachedTiers[$cacheKey];
    }

    // Load from DB...
    return $this->cachedTiers[$cacheKey] = $tiers;
}
```

---

## Notes

- All prices are in EUR with 2 decimal places
- Date handling uses PHP's strtotime() for maximum flexibility
- Seasonal pricing applies highest surcharge when overlapping multiple rules
- Bundle pricing applies modifiers (volume, seasonal, customer discounts)
- Customer-specific pricing completely overrides tier pricing
- Multi-tenant isolation via instances_id on all tables
- Cascade delete ensures referential integrity

---

## Support

For issues or enhancements:
1. Check this documentation
2. Review API endpoint responses
3. Check browser console for JavaScript errors
4. Verify permissions are set correctly
5. Ensure migration was run successfully
