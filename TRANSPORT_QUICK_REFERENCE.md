# Transport & Logistik (J3) - Quick Reference Guide

## Quick Start

### Run Migration
```bash
cd /path/to/rmsclone
vendor/bin/phinx migrate -e production
```

### Access the Module
- **URL:** `/src/transport/index.php`
- **Permissions Required:** `TRANSPORT:VIEW`

---

## Key Concepts

### Tour Lifecycle
```
planned → loading → in_transit → delivering → completed
                                  ↓
                              cancelled (any time)
```

### Stop Types
- **pickup** - Load goods at this location
- **delivery** - Unload goods at this location
- **return** - Return empty vehicle or collect returns

### Cost Types
- **fuel** - Fuel expenses
- **toll** - Toll/highway fees
- **parking** - Parking expenses
- **other** - Miscellaneous costs

---

## Most Used Service Methods

### Get Current Tours
```php
$service = new TransportLogisticsService($db);
$tours = $service->getTours($instanceId, $status = 'in_transit', $dateFrom = null, $dateTo = null);
```

### Get Tour with All Details
```php
$tour = $service->getTour($tourId);
// Returns: tour array + stops[], costs[], driver, vehicle
```

### Create Tour with Stops
```php
$data = [
    'instances_id' => 1,
    'name' => 'Tour Berlin-Munich',
    'date' => '2026-03-20',
    'driver_id' => 5,
    'vehicle_id' => 2,
];

$stops = [
    ['type' => 'pickup', 'address' => 'Berlin Warehouse', 'stop_order' => 1],
    ['type' => 'delivery', 'address' => 'Munich Client', 'stop_order' => 2],
];

$tourId = $service->createTour($data, $stops);
```

### Update Tour Status
```php
$service->updateTourStatus($tourId, 'in_transit');
```

### Complete a Delivery Stop
```php
$service->completeStop($stopId, $signatureData = null, $photoPath = null);
// or just mark as arrived:
$service->arriveAtStop($stopId);
```

### Add Cost to Tour
```php
$service->addCost($tourId, [
    'cost_type' => 'fuel',
    'amount' => 45.50,
    'description' => 'Shell Station Munich',
    'receipt_path' => 's3://bucket/receipt.jpg',
]);
```

### Check Vehicle Capacity
```php
$canFit = $service->checkVehicleCapacity($vehicleId, $weightKg = 500, $volumeM3 = 2.5);
if (!$canFit) {
    // Show warning or reject tour
}
```

### Get Dashboard Stats
```php
$stats = $service->getDashboardStats($instanceId);
// Returns:
// {
//   tours_today: 5,
//   tours_upcoming: 12,
//   total_distance_month_km: 2450.5,
//   total_costs_month: 1240.00,
//   tours_active: 3
// }
```

---

## API Endpoint Quick Reference

| Endpoint | Method | Action | Params |
|----------|--------|--------|--------|
| `vehicles.php` | GET | List vehicles | (none) |
| `vehicles.php` | POST | Create vehicle | `name`, `type`, `license_plate`, `max_weight_kg`, `cargo_volume_m3` |
| `vehicle_edit.php` | POST | Update vehicle | `id`, + fields to update |
| `drivers.php` | GET | List drivers | (none) |
| `drivers.php` | POST | Create driver | `users_userid`, `license_types`, `phone` |
| `tours.php` | GET | List tours | `status`, `date_from`, `date_to` |
| `tours.php` | POST | Create tour | `name`, `date`, `driver_id`, `vehicle_id`, `stops[]` |
| `tour_get.php` | GET | Get tour details | `id` |
| `tour_update.php` | POST | Update tour | `id`, fields + OR `status` |
| `stop_add.php` | POST | Add stop | `tour_id`, `type`, `address` |
| `stop_complete.php` | POST | Complete stop | `id`, `action` ('arrive'|'complete'), `signature_data`, `photo_path` |
| `costs.php` | GET | Get costs | `tour_id` |
| `costs.php` | POST | Add cost | `action=add`, `tour_id`, `cost_type`, `amount` |
| `costs.php` | POST | Delete cost | `action=delete`, `cost_id` |
| `capacity_check.php` | GET | Check capacity | `vehicle_id`, `weight_kg`, `volume_m3` |
| `dashboard.php` | GET | Get stats | (none) |

---

## Common Tasks

### Create a New Route/Tour
1. Create tour: `POST /api/transport/tours.php?action=create` with tour data + stops array
2. Assign driver: Include `driver_id` in tour data
3. Assign vehicle: Include `vehicle_id` in tour data
4. Verify capacity: `GET /api/transport/capacity_check.php`

### Track a Delivery
1. Get tour: `GET /api/transport/tour_get.php?id={tourId}`
2. Mark arrived: `POST /api/transport/stop_complete.php?action=arrive&id={stopId}`
3. Complete: `POST /api/transport/stop_complete.php?action=complete&id={stopId}` with signature
4. Update status: `POST /api/transport/tour_update.php?status=completed`

### Record Expenses
1. Get costs: `GET /api/transport/costs.php?tour_id={tourId}`
2. Add cost: `POST /api/transport/costs.php?action=add` with cost data
3. Delete (if wrong): `POST /api/transport/costs.php?action=delete&cost_id={costId}`
4. Auto-calculated: Total cost sums up in next tour load

### Manage Vehicles
1. List: `GET /api/transport/vehicles.php`
2. Create: `POST /api/transport/vehicles.php?action=create`
3. Edit: `POST /api/transport/vehicle_edit.php?id={vehicleId}`

### Manage Drivers
1. List: `GET /api/transport/drivers.php`
2. Create: `POST /api/transport/drivers.php?action=create`
3. Link to user: Supply `users_userid` in create request

---

## Permission Strings

```
TRANSPORT:VIEW    - View tours, vehicles, drivers, stats
TRANSPORT:EDIT    - Create/edit tours, vehicles, drivers; manage costs
TRANSPORT:DRIVE   - Mobile driver: mark stops arrived/completed
```

Add these to your instance permission settings.

---

## Database Queries (Raw SQL)

### Find all pending tours
```sql
SELECT * FROM transport_tours
WHERE instances_id = 1 AND status = 'planned'
ORDER BY date ASC;
```

### Sum fuel costs for a driver (month)
```sql
SELECT COALESCE(SUM(tc.amount), 0) as total_fuel
FROM transport_costs tc
JOIN transport_tours tt ON tc.tour_id = tt.id
JOIN transport_drivers td ON tt.driver_id = td.id
WHERE td.id = 5 AND tc.cost_type = 'fuel' AND tt.date >= '2026-03-01';
```

### Find incomplete deliveries
```sql
SELECT ts.*, tt.date, td.users_userid
FROM transport_tour_stops ts
JOIN transport_tours tt ON ts.tour_id = tt.id
LEFT JOIN transport_drivers td ON tt.driver_id = td.id
WHERE ts.completed_at IS NULL AND tt.status != 'cancelled'
ORDER BY tt.date ASC;
```

### Tours using specific vehicle
```sql
SELECT * FROM transport_tours
WHERE vehicle_id = 3
ORDER BY date DESC LIMIT 20;
```

---

## Debugging Tips

### Check Permissions
All endpoints start with `apiHeadSecure.php` which validates:
- User is authenticated (`$AUTH->login`)
- Has instance permission (e.g., `TRANSPORT:VIEW`)
- CSRF token (POST requests)

If 403 Forbidden: Check permission strings in database.

### Enable Debug Mode
In API endpoint, catch exceptions:
```php
try {
    // your code
} catch (Exception $e) {
    error_log("Transport Error: " . $e->getMessage());
    finish(false, ["error" => $e->getMessage()]);
}
```

### Test Endpoints with cURL
```bash
# Get vehicles
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://yourinstance.rms/api/transport/vehicles.php

# Create vehicle (requires CSRF token from form)
curl -X POST -d "action=create&name=Van1&type=van" \
  https://yourinstance.rms/api/transport/vehicles.php
```

---

## Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| `FORBIDDEN` | Missing permission | Add permission to user role |
| `INVALID_PARAM` | Missing required param | Check required fields in docs |
| `NOT_FOUND` | Tour/vehicle/driver doesn't exist | Verify ID is correct |
| `UPDATE_FAILED` | No fields to update | Include at least one field |
| `INVALID_DATE` | Wrong date format | Use YYYY-MM-DD |
| `INVALID_ACTION` | Unknown action parameter | Check action name spelling |

---

## Future Extensions

### Suggested Features to Add
1. **Route Optimization** - Reorder stops for efficiency
2. **GPS Tracking** - Real-time vehicle location
3. **Document Upload** - S3 integration for photos/receipts
4. **Mobile Driver App** - Companion app for drivers
5. **Analytics Dashboard** - Reports on costs, efficiency, times
6. **Notifications** - Email/SMS for delays, completed tours
7. **Integration** - Auto-post costs to accounting module

---

**Last Updated:** 2026-03-18
**Module Version:** 1.0
**Status:** Production Ready
