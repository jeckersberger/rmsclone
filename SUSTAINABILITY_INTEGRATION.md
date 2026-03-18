# Sustainability Module Integration Guide

## Quick Start Checklist

### 1. Database Migration
```bash
# Run the migration
vendor/bin/phinx migrate

# This creates 4 tables:
# - sustainability_config (configuration per instance)
# - sustainability_transport_log (append-only transport CO2 log)
# - sustainability_energy_log (append-only energy log)
# - sustainability_reports (generated reports)
```

### 2. Service Registration
Add to your bootstrap/dependency injection:
```php
require_once $basePath . '/src/services/SustainabilityService.php';
// For use in API endpoints:
$sustainabilityService = new SustainabilityService($db);
```

### 3. Permissions Setup
Add to `/src/server/permissions.php`:
```php
'SUSTAINABILITY' => [
    'VIEW' => 'Nachhaltigkeitsberichte und -dashboards anzeigen',
    'CONFIGURE' => 'Emissionsfaktoren konfigurieren und Berichte erstellen'
]
```

### 4. Navigation Menu
Add link to main navigation pointing to:
```
/sustainability/index.php
```

## Files Created

### Core Service
- `/src/services/SustainabilityService.php` - Main business logic (~350 lines)

### Database
- `/db/migrations/20260318250000_sustainability_reporting.php` - Schema

### API Endpoints
- `/src/api/sustainability/config.php` - Configuration GET/POST
- `/src/api/sustainability/dashboard.php` - Dashboard stats
- `/src/api/sustainability/emissions.php` - Emissions summary
- `/src/api/sustainability/trend.php` - Monthly trends
- `/src/api/sustainability/by_project.php` - Project breakdown
- `/src/api/sustainability/by_client.php` - Client impact
- `/src/api/sustainability/reports.php` - List & generate
- `/src/api/sustainability/report_get.php` - Report detail
- `/src/api/sustainability/client_report.php` - Client-facing report

### Frontend
- `/src/sustainability/sustainability_index.twig` - Main UI (5 tabs)
- `/src/sustainability/index.php` - Controller

### Documentation
- `/docs/SUSTAINABILITY_MODULE.md` - Full technical documentation

## Integration Points

### Transport Module Hook
**File**: `/src/api/transport/complete.php` or equivalent tour completion endpoint

**Add before response**:
```php
// Log CO2 emissions when tour is marked complete
$sustainabilityService->autoLogFromTour($tourId, $instanceId);
```

**Full example**:
```php
// After tour status updated to 'completed'
if ($tourStatus === 'completed') {
    // ... existing code ...

    // Log sustainability impact
    require_once __DIR__ . '/../../services/SustainabilityService.php';
    $sustainabilityService = new SustainabilityService($db);
    $sustainabilityService->autoLogFromTour($tourId, $_SESSION['instances_id']);
}
```

### Project Module Hook
**File**: `/src/api/projects/complete.php` or equivalent project completion endpoint

**Add before response**:
```php
// Log energy consumption when project is closed
$sustainabilityService->autoLogFromProject($projectId, $instanceId);
```

**Full example**:
```php
// After project status updated to 'closed'
if ($projectStatus === 'closed') {
    // ... existing code ...

    // Log sustainability impact
    require_once __DIR__ . '/../../services/SustainabilityService.php';
    $sustainabilityService = new SustainabilityService($db);
    $sustainabilityService->autoLogFromProject($projectId, $_SESSION['instances_id']);
}
```

## Configuration

### Default Values (in `sustainability_config`)

```json
{
  "emission_factors": {
    "van_per_km": 0.21,
    "truck_per_km": 0.35,
    "car_per_km": 0.15
  },
  "kwh_price_eur": 0.30,
  "co2_per_kwh": 0.42,
  "enable_client_reports": false
}
```

These can be updated via:
- **UI**: Sustainability → Einstellungen (Settings tab)
- **API**: POST `/api/sustainability/config.php`

## API Response Examples

### Dashboard Stats
```json
{
  "success": true,
  "data": {
    "this_month_co2_kg": 2450.75,
    "this_month_transport_kg": 1850.50,
    "this_month_energy_kg": 600.25,
    "this_month_projects": 8,
    "last_year_co2_kg": 2100.00,
    "yoy_growth_percent": 16.7,
    "total_kwh_this_month": 1428.57,
    "transport_split_percent": 75.5,
    "greenest_project": {
      "project_id": 42,
      "project_name": "Summer Festival Setup",
      "total_co2_kg": 125.50
    }
  }
}
```

### Emissions by Project
```json
{
  "success": true,
  "data": [
    {
      "project_id": 10,
      "project_name": "Gala Event 2026",
      "transport_co2_kg": 450.75,
      "energy_co2_kg": 320.25,
      "total_co2_kg": 771.00
    },
    {
      "project_id": 15,
      "project_name": "Exhibition Setup",
      "transport_co2_kg": 250.50,
      "energy_co2_kg": 180.00,
      "total_co2_kg": 430.50
    }
  ]
}
```

### Client Report (for PDF)
```json
{
  "success": true,
  "data": {
    "client_id": 5,
    "client_name": "ACME Events GmbH",
    "period_start": "2026-03-01",
    "period_end": "2026-03-31",
    "total_co2_kg": 1250.75,
    "transport_co2_kg": 950.50,
    "energy_co2_kg": 300.25,
    "project_count": 3,
    "report_text": "Ihr Event verursachte 1250.75 kg CO₂ Emissionen",
    "generated_at": "2026-03-18 15:30:00"
  }
}
```

## Data Logging

### Auto-Logging Prerequisites

**For Transport Logging** (`autoLogFromTour`):
- Tour record must have: `distance_km`, `vehicle_type`, optionally `project_id`

**For Energy Logging** (`autoLogFromProject`):
- Project must have: `start_date`, `end_date`
- Equipment must have: `power_rating` (in kW)
- Equipment must be linked to project via `projects_assets` table

### Manual Logging

If needed, call service methods directly:

```php
$sustainabilityService = new SustainabilityService($db);

// Log transport manually
$logId = $sustainabilityService->logTransportEmission(
    tourId: 42,
    projectId: 10,
    distanceKm: 250.5,
    vehicleType: 'van',
    instanceId: 1
);

// Log energy manually
$logId = $sustainabilityService->logProjectEnergy(
    projectId: 10,
    totalKwh: 500.0,
    instanceId: 1
);
```

## Reporting Workflow

### Generate a Report
```php
$reportId = $sustainabilityService->generateReport(
    reportType: 'monthly',  // 'monthly', 'quarterly', 'annual', 'project', 'client'
    periodStart: '2026-03-01',
    periodEnd: '2026-03-31',
    instanceId: 1,
    userId: 15  // User ID of person generating report
);
```

### Retrieve Report Data
```php
$report = $sustainabilityService->getReport($reportId);
// Contains: name, report_type, period_start, period_end, data_json (parsed), pdf_path, created_by, created_at
```

## Testing Data

To populate test data:
```php
$sustainability = new SustainabilityService($db);

// Create test transport logs
for ($i = 0; $i < 10; $i++) {
    $sustainability->logTransportEmission(
        tourId: 100 + $i,
        projectId: 1,
        distanceKm: 100 + rand(50, 200),
        vehicleType: ['van', 'truck', 'car'][rand(0, 2)],
        instanceId: 1
    );
}

// Create test energy logs
for ($i = 0; $i < 5; $i++) {
    $sustainability->logProjectEnergy(
        projectId: $i + 1,
        totalKwh: 500 + rand(0, 500),
        instanceId: 1
    );
}

// Generate test report
$sustainability->generateReport(
    'monthly',
    date('Y-m-01'),
    date('Y-m-t'),
    1,
    1
);
```

## Performance Considerations

- **Append-only logs**: Transport and energy logs should grow indefinitely
- **Indexes**: Created on `instances_id`, `created_at`, `project_id` for fast queries
- **Aggregation**: Done in-memory in PHP (suitable for typical event management scale)
- **Report storage**: JSON-based, no need to regenerate on each view
- **Chart data**: Cached in frontend localStorage (optional enhancement)

## Security

- All endpoints check `SUSTAINABILITY:VIEW` or `SUSTAINABILITY:CONFIGURE` permissions
- Instance isolation: All queries filtered by `instances_id`
- No direct SQL injection: Uses MeekroDB parameterized queries
- CORS headers: Not set (internal API)

## Compliance & Standards

### Supported Calculation Methods
- **Transport**: Emission factor × distance (WTW - Well-to-Wheel)
- **Energy**: Grid mix CO2 per kWh (Scope 2 - Indirect)
- **Extensible**: JSON data structure allows custom calculations

### Audit Trail
- `created_at`: Timestamp of logging event
- `created_by`: User ID for reports
- `calculation_basis`: Description of calculation method
- Immutable log tables (append-only)

### Future Compliance
- Can be extended for ISO 14064, GRI, CSRD standards
- Calculation basis field supports methodology tracking
- Supports multiple scopes (1, 2, 3)

## Troubleshooting

### No data showing
- Check: Are logs being created? Query `sustainability_transport_log` directly
- Check: Are dates within selected range?
- Check: Is instance ID correct?

### Wrong emissions calculated
- Verify: Emission factors in config (Einstellungen tab)
- Verify: Equipment has `power_rating` set
- Verify: Vehicle type matches config keys (van, truck, car)

### Reports not generating
- Check: `created_by` user ID is valid
- Check: Period dates are in correct format (Y-m-d)
- Check: Instance has at least one log entry

## Migration Rollback

If needed to remove the module:
```bash
# Rollback migration
vendor/bin/phinx rollback -t 20260318250000_sustainability_reporting

# This will drop all 4 sustainability tables
```

---

**Last Updated**: 2026-03-18
**Module Version**: 1.0.0 (L5)
**PHP Version**: 8.3+
**MysqliDb Required**: Yes
