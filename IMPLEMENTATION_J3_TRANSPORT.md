# Transport & Logistik (J3) Implementation

## Overview
Complete implementation of the Transport & Logistik module for MyRMS, featuring vehicle management, driver administration, tour planning, stop tracking, and cost management.

**Timestamp:** 2026-03-18
**PHP Version:** 8.3
**Database:** MeekroDB + Phinx
**Template Engine:** Twig
**UI Framework:** AdminLTE3

---

## Database Schema

### Migration: `20260318180000_transport_logistics.php`

#### Tables Created:

1. **transport_vehicles**
   - `id` (auto-increment PK)
   - `instances_id` - FK to instances
   - `name` - Vehicle name (100 chars)
   - `type` - ENUM('van', 'truck', 'trailer', 'car')
   - `license_plate` - Vehicle registration plate
   - `max_weight_kg` - Maximum cargo weight capacity
   - `cargo_volume_m3` - Cargo volume in cubic meters
   - `notes` - Free text notes
   - `is_active` - Boolean, default TRUE
   - `created_at` - Timestamp
   - **Indexes:** instances_id, is_active

2. **transport_drivers**
   - `id` (auto-increment PK)
   - `instances_id` - FK to instances
   - `users_userid` - FK to users table
   - `license_types` - Driver license classes (e.g., "B,C,CE")
   - `phone` - Contact phone number
   - `is_available` - Boolean, default TRUE
   - **Indexes:** instances_id, users_userid

3. **transport_tours**
   - `id` (auto-increment PK)
   - `instances_id` - FK to instances
   - `name` - Tour name (200 chars)
   - `date` - Tour date (DATE)
   - `driver_id` - FK to transport_drivers (nullable)
   - `vehicle_id` - FK to transport_vehicles (nullable)
   - `status` - ENUM('planned', 'loading', 'in_transit', 'delivering', 'completed', 'cancelled')
   - `total_distance_km` - Traveled distance (decimal)
   - `total_cost` - Total cost incurred (decimal)
   - `notes` - Free text notes
   - `created_at`, `updated_at` - Timestamps
   - **Indexes:** instances_id, date, status, driver_id, vehicle_id

4. **transport_tour_stops**
   - `id` (auto-increment PK)
   - `tour_id` - FK to transport_tours
   - `stop_order` - Sequence in tour (integer)
   - `type` - ENUM('pickup', 'delivery', 'return')
   - `project_id` - FK to projects (nullable)
   - `client_id` - FK to clients (nullable)
   - `address` - Stop location (text)
   - `time_window_start`, `time_window_end` - TIME for delivery window
   - `arrived_at` - DATETIME when driver arrived
   - `completed_at` - DATETIME when stop completed
   - `confirmed_by_signature` - Boolean, default FALSE
   - `signature_data` - Base64 or JSON of signature
   - `photo_path` - S3 or local path to photo
   - `notes` - Free text notes
   - **Indexes:** tour_id, project_id, client_id

5. **transport_costs**
   - `id` (auto-increment PK)
   - `tour_id` - FK to transport_tours
   - `cost_type` - ENUM('fuel', 'toll', 'parking', 'other')
   - `amount` - Cost amount (decimal)
   - `description` - Free text (255 chars)
   - `receipt_path` - S3 or local path to receipt image
   - `created_at` - Timestamp
   - **Indexes:** tour_id, cost_type

---

## Service Layer

### TransportLogisticsService (`src/services/TransportLogisticsService.php`)
~400 lines | MeekroDB pattern compatible

#### Vehicle Management
- `getVehicles(int $instanceId, ?bool $activeOnly = true): array`
- `getVehicle(int $vehicleId): ?array`
- `createVehicle(array $data): int` - Returns vehicle ID
- `updateVehicle(int $id, array $data): bool`

#### Driver Management
- `getDrivers(int $instanceId): array` - With user name/email joins
- `getDriver(int $driverId): ?array`
- `createDriver(array $data): int` - Returns driver ID
- `updateDriver(int $id, array $data): bool`

#### Tour Management
- `getTours(int $instanceId, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array`
- `getTour(int $id): ?array` - Returns full tour with stops, costs, driver, vehicle
- `createTour(array $data, array $stops = []): int` - Creates tour and initial stops
- `updateTour(int $id, array $data): bool`
- `updateTourStatus(int $id, string $status): bool`

#### Stop Management
- `getTourStops(int $tourId): array`
- `addStop(int $tourId, array $data): int` - Returns stop ID
- `updateStop(int $stopId, array $data): bool`
- `completeStop(int $stopId, ?string $signatureData = null, ?string $photoPath = null): bool`
- `arriveAtStop(int $stopId): bool` - Records arrival timestamp

#### Cost Management
- `addCost(int $tourId, array $data): int` - Returns cost ID
- `getTourCosts(int $tourId): array`
- `deleteCost(int $costId): bool`

#### Analytics & Calculations
- `calculateTourWeight(int $tourId): float` - Sums asset weights for tour
- `checkVehicleCapacity(int $vehicleId, float $requiredWeight, float $requiredVolume): bool`
- `getToursForProject(int $projectId): array`
- `getToursForDate(string $date, int $instanceId): array`
- `getDashboardStats(int $instanceId): array` - Returns:
  - `tours_today` (int)
  - `tours_upcoming` (int, next 7 days)
  - `total_distance_month_km` (float)
  - `total_costs_month` (float)
  - `tours_active` (int, in transit or delivering)

---

## API Endpoints

All endpoints in `/src/api/transport/` - Secured with `apiHeadSecure.php`

### Vehicle Endpoints

#### `vehicles.php` - GET/POST
- **GET:** Fetch all vehicles (with `?all` param for inactive vehicles)
  - Permission: `TRANSPORT:VIEW`
- **POST** `action=create`: Create new vehicle
  - Permission: `TRANSPORT:EDIT`
  - Params: `name`, `type`, `license_plate`, `max_weight_kg`, `cargo_volume_m3`, `notes`, `is_active`
  - Returns: `{ vehicle_id, id }`

#### `vehicle_edit.php` - POST
- Update vehicle by ID
  - Permission: `TRANSPORT:EDIT`
  - Params: `id` (required), plus optional fields
  - Returns: `{ success: bool }`

### Driver Endpoints

#### `drivers.php` - GET/POST
- **GET:** Fetch all drivers with user details
  - Permission: `TRANSPORT:VIEW`
- **POST** `action=create`: Create new driver
  - Permission: `TRANSPORT:EDIT`
  - Params: `users_userid`, `license_types`, `phone`, `is_available`
  - Returns: `{ driver_id, id }`

### Tour Endpoints

#### `tours.php` - GET/POST
- **GET:** Fetch tours with optional filters (`status`, `date_from`, `date_to`)
  - Permission: `TRANSPORT:VIEW`
- **POST** `action=create`: Create tour with stops
  - Permission: `TRANSPORT:EDIT`
  - Params: `name`, `date`, `driver_id`, `vehicle_id`, `status`, `total_distance_km`, `total_cost`, `notes`, `stops[]`
  - Returns: `{ tour_id, id }`

#### `tour_get.php` - GET
- Fetch single tour with all stops and costs
  - Permission: `TRANSPORT:VIEW`
  - Params: `id` (tour ID)
  - Returns: `{ tour: { ...tour, stops[], costs[], driver, vehicle } }`

#### `tour_update.php` - POST
- Update tour or tour status
  - Permission: `TRANSPORT:EDIT`
  - Params: `id`, `status` OR other fields (`name`, `date`, `driver_id`, `vehicle_id`, etc.)
  - Returns: `{ success: bool }`

### Stop Endpoints

#### `stop_add.php` - POST
- Add stop to tour
  - Permission: `TRANSPORT:EDIT`
  - Params: `tour_id`, `type`, `stop_order`, `project_id`, `client_id`, `address`, `time_window_start`, `time_window_end`, `notes`
  - Returns: `{ stop_id, id }`

#### `stop_complete.php` - POST
- Mark stop as arrived or completed
  - Permission: `TRANSPORT:DRIVE`
  - Params: `id` (stop ID), `action` ('arrive' or 'complete'), `signature_data`, `photo_path`
  - Returns: `{ success: bool }`

### Cost Endpoints

#### `costs.php` - GET/POST
- **GET:** Fetch costs for a tour
  - Permission: `TRANSPORT:VIEW`
  - Params: `tour_id` (required)
- **POST** `action=add`: Add cost entry
  - Permission: `TRANSPORT:EDIT`
  - Params: `tour_id`, `cost_type`, `amount`, `description`, `receipt_path`
  - Returns: `{ cost_id, id }`
- **POST** `action=delete`: Delete cost entry
  - Permission: `TRANSPORT:EDIT`
  - Params: `cost_id`
  - Returns: `{ success: bool }`

### Utility Endpoints

#### `capacity_check.php` - GET
- Check if vehicle can handle required load
  - Permission: `TRANSPORT:VIEW`
  - Params: `vehicle_id`, `weight_kg`, `volume_m3`
  - Returns: `{ vehicle, can_fit: bool, warnings: [] }`

#### `dashboard.php` - GET
- Fetch dashboard statistics for instance
  - Permission: `TRANSPORT:VIEW`
  - Returns: `{ stats: { tours_today, tours_upcoming, total_distance_month_km, total_costs_month, tours_active } }`

---

## Frontend

### Template: `src/transport/transport_index.twig`

Features:
- **Dashboard Stats Widget** - Tours today, upcoming, km this month, costs
- **Calendar View Tab** - Visual overview of tours per day
- **Tours List Tab** - Filterable table (by date range, status)
  - Tour detail modal showing stops and costs
  - Status badges (planned, loading, in_transit, delivering, completed, cancelled)
- **Vehicles Tab** - Table of all vehicles with capacity info
  - Create, edit, activate/deactivate
- **Drivers Tab** - Table of all drivers with license classes
  - Create, edit, manage availability

### Controller: `src/transport/index.php`
- Basic controller that loads template
- Permission checks: `TRANSPORT:VIEW`
- Passes framework and auth context to Twig

---

## Permissions

Three permission levels (instance-based):

| Permission | Use Case |
|-----------|----------|
| `TRANSPORT:VIEW` | View tours, vehicles, drivers, dashboard |
| `TRANSPORT:EDIT` | Create/edit tours, vehicles, drivers; add stops; manage costs |
| `TRANSPORT:DRIVE` | Mark stops as arrived/completed; confirm deliveries (mobile app) |

---

## Integration Notes

### MeekroDB Pattern
The service layer follows exact MeekroDB patterns:
- `getOne($table, $where)` for single records (used with `->where()` chaining)
- `insert()`, `update()`, `delete()`, `rawQuery()` for operations
- `affectedRows()` to check success (not relying on return values)
- No `->count()` or `->groupBy()` usage

### Database Compatibility
- Uses Phinx migrations for schema versioning
- Compatible with MySQL 5.7+, 8.0+
- Enum types stored as ENUM(...)
- All timestamps have defaults for automatic management

### Error Handling
All API endpoints use `ErrorHandlerService::wrap()` for:
- Consistent error response format
- Permission checking
- Input validation via `InputValidationService`
- CSRF token validation (POST requests)

---

## Usage Examples

### Create a Tour with Stops (JavaScript/Frontend)
```javascript
fetch('/api/transport/tours.php', {
  method: 'POST',
  body: new FormData({
    'action': 'create',
    'name': 'Tour München - Berlin',
    'date': '2026-03-20',
    'driver_id': 5,
    'vehicle_id': 2,
    'status': 'planned',
    'stops[0][type]': 'pickup',
    'stops[0][address]': 'Warehouse, Munich',
    'stops[0][stop_order]': 1,
    'stops[1][type]': 'delivery',
    'stops[1][address]': 'Client, Berlin',
    'stops[1][stop_order]': 2,
  })
})
.then(r => r.json())
.then(data => console.log(data.tour_id));
```

### Complete a Stop (Mobile Driver App)
```javascript
fetch('/api/transport/stop_complete.php', {
  method: 'POST',
  body: new FormData({
    'id': 42,
    'action': 'complete',
    'signature_data': 'data:image/png;base64,...',
    'photo_path': 's3://bucket/photo-20260318.jpg'
  })
})
```

### Check Vehicle Capacity
```javascript
fetch('/api/transport/capacity_check.php?vehicle_id=2&weight_kg=500&volume_m3=2.5')
  .then(r => r.json())
  .then(data => {
    if (data.can_fit) {
      console.log('Vehicle has capacity!');
    } else {
      console.log('Warnings:', data.warnings);
    }
  });
```

---

## Migration & Deployment

### Running the Migration
```bash
# In project root with Phinx configured
vendor/bin/phinx migrate -e production

# Verify new tables
SHOW TABLES LIKE 'transport_%';
```

### File Checklist
- [x] Database migration: `db/migrations/20260318180000_transport_logistics.php`
- [x] Service: `src/services/TransportLogisticsService.php`
- [x] API endpoints: `src/api/transport/{vehicles.php, vehicle_edit.php, drivers.php, tours.php, tour_get.php, tour_update.php, stop_add.php, stop_complete.php, costs.php, capacity_check.php, dashboard.php}`
- [x] Template: `src/transport/transport_index.twig`
- [x] Controller: `src/transport/index.php`

---

## Future Enhancements

1. **Route Optimization** - Integrate with Google Maps or OpenRoute API for optimal stop ordering
2. **GPS Tracking** - Real-time vehicle location tracking (requires WebSocket or periodic API calls)
3. **Document Storage** - S3 integration for receipts, photos, and signature images
4. **Mobile App** - Dedicated driver app for route navigation, stop completion, and cost entry
5. **Reporting** - Tour reports, cost analysis, driver performance metrics
6. **Notifications** - Real-time alerts for delayed deliveries, capacity issues
7. **Integration** - Sync with accounting system for cost posting; connect to project management

---

## File Locations Summary

```
/sessions/vibrant-kind-edison/rmsclone/
├── db/migrations/
│   └── 20260318180000_transport_logistics.php
├── src/
│   ├── services/
│   │   └── TransportLogisticsService.php
│   ├── api/transport/
│   │   ├── vehicles.php
│   │   ├── vehicle_edit.php
│   │   ├── drivers.php
│   │   ├── tours.php
│   │   ├── tour_get.php
│   │   ├── tour_update.php
│   │   ├── stop_add.php
│   │   ├── stop_complete.php
│   │   ├── costs.php
│   │   ├── capacity_check.php
│   │   └── dashboard.php
│   ├── transport/
│   │   ├── index.php
│   │   └── transport_index.twig
│   └── [other existing services/API structure]
└── [rest of MyRMS structure]
```

---

**Implementation Date:** March 18, 2026
**Status:** ✅ Complete and Ready for Integration Testing
