# Nachhaltigkeitsreporting & ESG Module (L5)

## Overview

The Sustainability Reporting & ESG module provides comprehensive CO2 emission tracking and reporting for MyRMS. It tracks transport emissions, energy consumption, and generates detailed sustainability reports for compliance and environmental impact analysis.

## Architecture

### Database Schema

**`sustainability_config`** (1 row per instance)
- Stores emission factors for different vehicle types
- Energy pricing and CO2 calculations
- Client report enablement flag

**`sustainability_transport_log`** (Append-only)
- Logs tour-based CO2 emissions
- Tracks distance, vehicle type, calculated CO2
- Links to projects and tours

**`sustainability_energy_log`** (Append-only)
- Logs project energy consumption
- Calculates CO2 from electricity consumption
- Stores calculation basis (equipment + hours)

**`sustainability_reports`** (Report artifacts)
- Generated reports (monthly, quarterly, annual, project, client)
- Stores report data as JSON
- Optional PDF path

### Service Layer

**`SustainabilityService`** (~350 lines)

Core methods:

```php
// Configuration
getConfig(instanceId): array
saveConfig(instanceId, data): bool

// Transport Tracking
calculateTransportCo2(distanceKm, vehicleType, instanceId): float
logTransportEmission(tourId, projectId, distanceKm, vehicleType, instanceId): int
autoLogFromTour(tourId, instanceId): void  // Hook

// Energy Tracking
calculateProjectEnergy(projectId, instanceId): array  // From equipment power × hours
logProjectEnergy(projectId, totalKwh, instanceId): int
autoLogFromProject(projectId, instanceId): void  // Hook

// Reporting
getEmissionsSummary(instanceId, period, dateFrom, dateTo): array
getEmissionsByProject(instanceId, dateFrom, dateTo): array
getEmissionsByClient(clientId, instanceId): array
getEmissionsTrend(instanceId, months): array
generateReport(reportType, periodStart, periodEnd, instanceId, userId): int
generateClientReport(clientId, periodStart, periodEnd, instanceId): array

// Dashboard
getDashboardStats(instanceId): array
```

### API Endpoints

All endpoints located in `/src/api/sustainability/`:

#### `config.php`
- **GET**: Retrieve current configuration
- **POST**: Update emission factors, energy pricing

#### `dashboard.php`
- **GET**: Dashboard KPIs
  - This month's CO2
  - YoY comparison
  - Transport vs. energy split
  - Greenest project

#### `emissions.php`
- **GET**: Summary with period/date filters
  - Supports: day, week, month, quarter, year
  - Custom date ranges via `from` / `to` params

#### `trend.php`
- **GET**: 12-month CO2 trend for charting
- Query: `?months=12` (1-60 supported)

#### `by_project.php`
- **GET**: Emissions per project
- Breakdown: transport vs. energy
- Optional date filtering

#### `by_client.php`
- **GET**: Client emissions summary
- Required: `client_id` parameter
- Returns: Total CO2, transport/energy split, project count

#### `reports.php`
- **GET**: List all reports for instance
- **POST**: Generate new report
  - Types: monthly, quarterly, annual, project, client
  - Required: `report_type`, `period_start`, `period_end`

#### `report_get.php`
- **GET**: Single report with full data
- Required: `id` parameter

#### `client_report.php`
- **GET**: Client-facing sustainability report
- Required: `client_id`
- Optional: `from`, `to` dates
- Returns: "Ihr Event verursachte X kg CO₂" formatted data

## Frontend

**`/src/sustainability/sustainability_index.twig`**

AdminLTE3 responsive interface with 5 tabs:

### Dashboard Tab
- Large CO2 metric (this month)
- YoY comparison badge
- Transport vs. Energy split (donut chart)
- 12-month trend line chart
- Greenest project badge
- Energy consumption (kWh)

### By Project Tab
- Date range picker
- Table with:
  - Project name
  - Transport CO2
  - Energy CO2
  - Total CO2
  - Sustainability badge (green/yellow/red)

### By Client Tab
- Client selector dropdown
- 3 info boxes (total, transport, energy)
- Client impact message

### Reports Tab
- Generate new report modal
- Report type selector
- Date range picker
- Reports table with:
  - Report name & type
  - Period
  - Creation date
  - View & download buttons

### Settings Tab (CONFIGURE permission required)
- Emission factors per vehicle type
- kWh pricing
- CO2 per kWh conversion
- Client report enablement toggle

## Integration Hooks

### Transport Module
Call from `/src/api/transport/complete.php` or similar:
```php
$sustainabilityService->autoLogFromTour($tourId, $instanceId);
```

### Project Module
Call from project completion endpoint:
```php
$sustainabilityService->autoLogFromProject($projectId, $instanceId);
```

## Emission Calculation

### Transport
```
CO2 (kg) = Distance (km) × Emission Factor (kg/km)
Factors: van=0.21, truck=0.35, car=0.15 (configurable)
```

### Energy
```
CO2 (kg) = Energy (kWh) × CO2 per kWh (default 0.42)
Energy (kWh) = Equipment Power (kW) × Operating Hours × Quantity
```

## Data Flow Examples

### Example 1: Log Transport Emission
```php
// After tour completion
$sustainabilityService->logTransportEmission(
    tourId: 42,
    projectId: 10,
    distanceKm: 250.5,
    vehicleType: 'van',
    instanceId: 1
);
// Calculates: 250.5 × 0.21 = 52.605 kg CO2
// Records: sustainability_transport_log entry
```

### Example 2: Project Energy Calculation
```php
// Project from 2026-03-01 to 2026-03-15 (14 days = 336 hours)
// Equipment:
//   - 2× Projectors @ 0.5 kW each
//   - 4× LED Lights @ 0.2 kW each
//
// Energy = (2×0.5 + 4×0.2) × 336 = (1.0 + 0.8) × 336 = 604.8 kWh
// CO2 = 604.8 × 0.42 = 254.016 kg

$energyCalc = $sustainabilityService->calculateProjectEnergy(10, 1);
// Returns: ['total_kwh' => 604.8, 'calculation_basis' => '...']
```

### Example 3: Monthly Report
```php
$reportId = $sustainabilityService->generateReport(
    reportType: 'monthly',
    periodStart: '2026-03-01',
    periodEnd: '2026-03-31',
    instanceId: 1,
    userId: 15
);
// Generates report with:
// - Summary (total CO2, splits, project count)
// - By-project breakdown
// - 12-month trend
// - Stored as JSON in sustainability_reports
```

## Permissions

Two permission levels:

- **SUSTAINABILITY:VIEW** - View dashboard, reports, emissions data
- **SUSTAINABILITY:CONFIGURE** - Modify configuration, generate reports

Add to `permissions.php`:
```php
'SUSTAINABILITY' => [
    'VIEW' => 'View sustainability reports and dashboards',
    'CONFIGURE' => 'Configure emission factors and generate reports'
]
```

## Typical Workflows

### 1. Monthly Report Generation (Admin)
1. Go to Sustainability → Reports tab
2. Click "Neuer Bericht"
3. Select "monthly", set dates to 1st–last of month
4. Click "Erstellen"
5. Report appears in list with "Ansicht" button
6. Download PDF when available

### 2. Check Client Impact
1. Go to Sustainability → Nach Kunde
2. Select client from dropdown
3. View total CO2, breakdown by transport/energy
4. Generate client report for sharing

### 3. Adjust Emission Factors (Compliance)
1. Go to Sustainability → Einstellungen
2. Update factors based on new vehicle fleet specs
3. Click "Speichern"
4. New logging will use updated factors

## Migration & Setup

1. **Run migration**:
   ```bash
   vendor/bin/phinx migrate
   ```

2. **Service registration** (in bootstrap):
   ```php
   // Add to dependency injection or auto-load
   require_once $basePath . '/src/services/SustainabilityService.php';
   $sustainabilityService = new SustainabilityService($db);
   ```

3. **Add permissions** to your permissions system

4. **Add navigation item** to main menu pointing to `/sustainability/index.php`

5. **Set up hooks** in transport and project modules:
   ```php
   // After tour completion
   $sustainabilityService->autoLogFromTour($tourId, $instanceId);

   // After project completion
   $sustainabilityService->autoLogFromProject($projectId, $instanceId);
   ```

## Features

### Configurable Defaults
- Emission factors per vehicle type
- Energy cost per kWh
- CO2 emissions per kWh (grid mix)
- Client report toggle

### Automatic Logging
- Tours → Transport emissions
- Projects → Energy consumption (from equipment list)
- No manual data entry required

### Flexible Reporting
- Monthly, quarterly, annual, project, client reports
- Custom date ranges
- JSON-based data structure (extensible)
- PDF generation ready

### Analytics
- 12-month trend visualization
- Project-level CO2 breakdown
- Client-level aggregation
- YoY comparisons
- Transport vs. energy split analysis

### Compliance Ready
- Audit trail (created_at, created_by)
- JSON data for extensibility
- Configuration versioning
- Calculation basis documentation

## Technical Notes

- Uses **MeekroDB** (no groupBy/count, uses affectedRows)
- **PHP 8.3** strict types
- **Phinx** migrations with auto-timestamp
- **Twig** templates with AdminLTE3 components
- **Chart.js** for frontend visualizations
- **REST API** endpoints with JSON responses
- Permission-based access control

## Future Enhancements

- PDF report generation (with wkhtmltopdf or similar)
- Scope 3 emissions (supplier data, materials)
- Carbon offset calculator
- ESG risk rating system
- Integration with carbon accounting standards (ISO 14064)
- Supply chain emissions tracking
- Staff commute tracking
- Real-time dashboard widgets
- Export to CSV/Excel with formatting
