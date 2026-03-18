# Schadenmanagement-Workflow (K3) Implementation Guide

## Overview

The Schadenmanagement-Workflow (K3) is a comprehensive damage management system for MyRMS that handles the complete lifecycle of damage claims from initial report through final settlement or insurance processing.

## Architecture

### Stack
- **PHP 8.3** with MeekroDB (MySQLi wrapper)
- **Phinx** migrations for database versioning
- **Twig** templating engine
- **AdminLTE3** UI framework
- **Bootstrap 4** responsive grid

## Database Schema

### New Tables Created

#### 1. `damage_workflows`
Main workflow tracking table
- `id` (PK) - AUTO_INCREMENT
- `damage_report_id` (FK) - Links to existing damage_reports table
- `instances_id` (FK) - Multi-tenant isolation
- `status` (ENUM) - Current workflow status
- `severity` (ENUM) - Damage severity classification
- `estimated_cost` (DECIMAL) - Initial cost estimate
- `actual_cost` (DECIMAL) - Final repair cost
- `repair_vendor` (VARCHAR) - Repair company name
- `repair_vendor_contact` (VARCHAR) - Repair company contact info
- `insurance_claim_id` (VARCHAR) - Insurance claim reference
- `insurance_claim_status` (ENUM) - Insurance claim status
- `customer_charged` (BOOLEAN) - Whether customer was charged
- `customer_charge_amount` (DECIMAL) - Amount charged to customer
- `customer_charge_invoice_id` (INT FK) - Link to invoice if created
- `deposit_deducted` (BOOLEAN) - Whether cost was deducted from deposit
- `deposit_deduction_amount` (DECIMAL) - Amount deducted from deposit
- `assigned_to` (INT FK) - User responsible for this workflow
- `notes` (TEXT) - Internal notes
- `created_at`, `updated_at` (DATETIME)

#### 2. `damage_workflow_log`
Audit trail for status changes
- `id` (PK) - AUTO_INCREMENT
- `workflow_id` (FK) - References damage_workflows
- `from_status` (VARCHAR) - Previous status
- `to_status` (VARCHAR) - New status
- `changed_by` (INT FK) - User who made change
- `notes` (TEXT) - Change notes
- `created_at` (DATETIME)

#### 3. `damage_cost_estimates`
Cost estimates from repair vendors
- `id` (PK) - AUTO_INCREMENT
- `workflow_id` (FK) - References damage_workflows
- `vendor_name` (VARCHAR) - Vendor company name
- `description` (TEXT) - Work description
- `amount` (DECIMAL) - Quoted amount
- `is_accepted` (BOOLEAN) - Whether estimate was accepted
- `document_path` (VARCHAR) - Optional quote document path
- `created_at` (DATETIME)

#### 4. `damage_photos`
Photo documentation
- `id` (PK) - AUTO_INCREMENT
- `damage_report_id` (FK) - References damage_reports
- `file_path` (VARCHAR) - File location
- `description` (TEXT) - Photo description
- `photo_type` (ENUM) - 'initial', 'during_repair', 'after_repair'
- `uploaded_by` (INT FK) - User who uploaded
- `created_at` (DATETIME)

## Workflow States

### Status Transitions (Allowed Flow)
```
reported
    ↓
assessed
    ├→ quote_requested
    │      ↓
    │   quote_received
    │      ├→ repair_approved → in_repair → repaired → verified
    │      └→ closed
    ├→ repair_approved → in_repair → repaired → verified
    └→ closed (minor damage without repair)

verified
    ├→ charged → closed
    ├→ insurance_claimed → closed
    └→ closed
```

### Status Descriptions

| Status | Description | User Action |
|--------|-------------|-------------|
| `reported` | Initial damage report created | Create report from check-in |
| `assessed` | Damage professionally assessed | Assessment photo + notes |
| `quote_requested` | Repair quotes requested from vendors | Send RFQ to vendors |
| `quote_received` | Quotes received from vendors | Accept/reject estimates |
| `repair_approved` | Repair approved, ready to start | Move to execution |
| `in_repair` | Repair in progress | Track progress |
| `repaired` | Repair completed | Document completion |
| `verified` | Quality verified, ready for billing | Final inspection |
| `charged` | Customer charged for repair | Create invoice/charge deposit |
| `insurance_claimed` | Claim submitted to insurance | Send claim documentation |
| `closed` | Case closed | Archive workflow |

## Service Layer

### DamageWorkflowService Class

Location: `/src/services/DamageWorkflowService.php`

**Core Methods:**

```php
// Initialization
__construct($db) - Create service with DB connection

// Workflow Management
getWorkflow(int $damageReportId): ?array
createWorkflow(int $damageReportId, string $severity, ?float $estimatedCost, ?int $assignedTo, int $instanceId): int
transitionStatus(int $workflowId, string $newStatus, int $userId, ?string $notes = null): bool
getValidTransitions(string $currentStatus): array

// Cost Estimates
addCostEstimate(int $workflowId, array $data): int
acceptEstimate(int $estimateId): bool
setActualCost(int $workflowId, float $actualCost): bool

// Photos
addPhoto(int $damageReportId, string $filePath, string $photoType, ?string $description, int $userId): int

// Customer Charging
chargeCustomer(int $workflowId, float $amount, ?int $invoiceId): bool
deductFromDeposit(int $workflowId, float $amount): bool

// Insurance
submitInsuranceClaim(int $workflowId, string $claimId): bool
updateInsuranceStatus(int $workflowId, string $status): bool

// Workflow Operations
assignTo(int $workflowId, int $userId): bool
setRepairVendor(int $workflowId, string $vendorName, ?string $contact): bool
addNotes(int $workflowId, string $notes): bool

// Reporting
getOpenWorkflows(int $instanceId, ?string $severity = null): array
getDashboardStats(int $instanceId): array
getWorkflowsByAsset(int $assetId): array
getWorkflowsByClient(int $clientId): array
getStatusLog(int $workflowId): array
```

**Constants:**

```php
// Status constants
const STATUS_REPORTED = 'reported';
const STATUS_ASSESSED = 'assessed';
const STATUS_QUOTE_REQUESTED = 'quote_requested';
// ... etc (11 total)

// Severity constants
const SEVERITY_MINOR = 'minor';
const SEVERITY_MODERATE = 'moderate';
const SEVERITY_MAJOR = 'major';
const SEVERITY_TOTAL_LOSS = 'total_loss';
```

## API Endpoints

All endpoints require authentication via `require_once __DIR__ . '/../apiHeadSecure.php'`

### GET Endpoints

#### `/api/damage/workflows.php`
List open workflows
```
POST parameters:
  - severity (optional): Filter by severity
  - assigned_to (optional): Filter by assigned user
Required permission: DAMAGE:VIEW
Returns: { workflows: [...] }
```

#### `/api/damage/workflow_get.php`
Get single workflow with all related data
```
POST parameters:
  - damage_report_id (required): ID of damage report
Required permission: DAMAGE:VIEW
Returns: { workflow: { ..., status_log: [...], cost_estimates: [...], photos: [...] } }
```

#### `/api/damage/dashboard.php`
Get dashboard statistics
```
Required permission: DAMAGE:VIEW
Returns: {
  stats: {
    status_counts: {...},
    severity_counts: {...},
    open_count: int,
    total_estimated_cost_this_month: float,
    total_actual_cost_this_month: float,
    avg_resolution_days: int,
    assigned_counts: {...}
  }
}
```

#### `/api/damage/by_asset.php`
Get damage history for asset
```
POST parameters:
  - asset_id (required): Asset ID
Required permission: DAMAGE:VIEW
Returns: { workflows: [...] }
```

#### `/api/damage/by_client.php`
Get damage history for client/company
```
POST parameters:
  - client_id (required): Company ID
Required permission: DAMAGE:VIEW
Returns: { workflows: [...] }
```

### POST Endpoints

#### `/api/damage/workflow_create.php`
Create new workflow from damage report
```
POST parameters:
  - damage_report_id (required): ID of damage report
  - severity (required): 'minor'|'moderate'|'major'|'total_loss'
  - estimated_cost (optional): Float
  - assigned_to (optional): User ID
Required permission: DAMAGE:EDIT
Returns: { workflow_id: int }
```

#### `/api/damage/workflow_transition.php`
Change workflow status
```
POST parameters:
  - workflow_id (required): Workflow ID
  - new_status (required): Target status (validates against allowed transitions)
  - notes (optional): Reason for transition
Required permission: DAMAGE:EDIT
Returns: { workflow_id: int, new_status: string }
Error: { message, current_status, valid_transitions }
```

#### `/api/damage/estimate_add.php`
Add cost estimate
```
POST parameters:
  - workflow_id (required): Workflow ID
  - vendor_name (required): Vendor company name
  - amount (required): Float > 0
  - description (optional): Work description
  - is_accepted (optional): 0|1
  - document_path (optional): Path to quote PDF
Required permission: DAMAGE:EDIT
Returns: { estimate_id: int }
```

#### `/api/damage/estimate_accept.php`
Accept an estimate and use as workflow estimate
```
POST parameters:
  - estimate_id (required): Estimate ID
Required permission: DAMAGE:EDIT
Returns: { estimate_id: int, accepted: true }
```

#### `/api/damage/photo_upload.php`
Upload damage photo
```
POST parameters (multipart/form-data):
  - damage_report_id (required): Report ID
  - photo (required): File upload
  - photo_type (required): 'initial'|'during_repair'|'after_repair'
  - description (optional): Photo description
Required permission: DAMAGE:EDIT
Storage: /src/static-assets/damage_photos/YYYY/MM/
Returns: { photo_id: int, file_path: string }
```

#### `/api/damage/assign_user.php`
Assign workflow to user
```
POST parameters:
  - workflow_id (required): Workflow ID
  - user_id (required): User ID to assign to
Required permission: DAMAGE:EDIT
Returns: { workflow_id: int, assigned_to: int }
```

#### `/api/damage/charge_customer.php`
Charge damage cost to customer
```
POST parameters:
  - workflow_id (required): Workflow ID
  - amount (required): Float > 0
  - invoice_id (optional): Invoice ID if created
  - charge_type (optional): 'customer'|'deposit' (default: 'customer')
Required permission: DAMAGE:CHARGE
Returns: { workflow_id: int, amount: float, type: string }
```

#### `/api/damage/insurance_claim.php`
Submit or update insurance claim
```
POST parameters (submit new claim):
  - workflow_id (required): Workflow ID
  - claim_id (required): Insurance claim ID/reference

OR (update existing claim):
  - workflow_id (required): Workflow ID
  - claim_status (required): 'claimed'|'approved'|'rejected'|'paid'

Required permission: DAMAGE:EDIT
Returns: { workflow_id: int }
```

## UI Components

### Dashboard (`/src/damage/damage_dashboard.twig`)

**Features:**
- Statistics cards: Open cases, In repair, Costs this month, Avg resolution time
- Severity distribution chart (Minor/Moderate/Major/Total Loss)
- Kanban board with 11 status columns
- Drag-and-drop card organization (future enhancement)
- Severity filter dropdown
- Assigned-to filter dropdown
- Real-time workflow count badges per column

**JavaScript Functions:**
- `loadDashboard()` - Load statistics
- `loadWorkflows()` - Load and filter workflows
- `renderKanban()` - Render kanban board with workflows
- `viewWorkflow()` - Navigate to detail view

### Detail View (`/src/damage/damage_detail.twig`)

**Sections:**
1. **Header** - Workflow ID, Asset, Status badge, Severity badge, Cost
2. **Info Grid** - Created date, Last update, Assigned to, Vendor, Costs, Customer charge
3. **Status Transitions** - Dropdown to valid next status with notes
4. **Status History Timeline** - Audit log with user and timestamp
5. **Cost Estimates** - Table of vendor quotes with accept action
6. **Photos** - Gallery of damage/repair photos with lightbox viewer
7. **Quick Actions Panel**
   - Assign to user
   - Set repair vendor
   - Charge customer (if permission DAMAGE:CHARGE)
   - Submit insurance claim
   - Update insurance status
8. **Notes Panel** - Internal notes with timestamp

**Features:**
- Collapsible forms for inline actions
- Modal for full-size photo viewing
- Responsive grid layout
- Color-coded status badges
- Permission-based action visibility

## Controller

### Main Controller (`/src/damage/index.php`)

**Routes:**

1. Dashboard (default)
```
GET /src/damage/index.php
Renders: damage_dashboard.twig
```

2. Workflow Detail
```
GET /src/damage/index.php?view=detail&damage_report_id=123
Renders: damage_detail.twig
Loads: Workflow with related data, asset info, project info, status log
```

**Features:**
- Auto-create workflow if missing
- Load asset and project context
- Check permissions for edit/charge actions
- Populate user dropdown for assignment

## Permissions

The system uses three core permissions:

| Permission | Description |
|-----------|-------------|
| `DAMAGE:VIEW` | View workflows, reports, statistics |
| `DAMAGE:EDIT` | Create/transition workflows, add estimates, upload photos |
| `DAMAGE:CHARGE` | Charge customers, create invoices |

## Integration with Existing Systems

### DamageReportService
The new workflow system **extends** (not replaces) the existing `DamageReportService`:
- Existing `damage_reports` table is used
- New `damage_workflows` table links to existing reports
- When a report is created, workflows can be created separately
- Auto-creation from check-in still works via DamageReportService

### Maintenance Integration
- Workflows can reference maintenance jobs
- Repair status updates flow through maintenance system
- Cost tracking integrates with maintenance cost reporting

## Database Migration

### Running the Migration

```bash
cd /path/to/rmsclone
vendor/bin/phinx migrate -e production
```

Migration file: `/db/migrations/20260318160000_damage_workflow.php`

Creates 4 tables:
- `damage_workflows` (11 columns, 6 indexes)
- `damage_workflow_log` (6 columns, 2 indexes)
- `damage_cost_estimates` (8 columns, 2 indexes)
- `damage_photos` (8 columns, 2 indexes)

## Usage Example

### Creating a Workflow

```php
$service = new DamageWorkflowService($db);

// Create workflow from damage report
$workflowId = $service->createWorkflow(
    damageReportId: 42,
    severity: 'moderate',
    estimatedCost: 500.00,
    assignedTo: 15,
    instanceId: 1
);

// Add cost estimate
$estimateId = $service->addCostEstimate($workflowId, [
    'vendor_name' => 'Repair Co',
    'amount' => 450.00,
    'description' => 'Screen replacement and calibration',
    'is_accepted' => false
]);

// Add photos
$service->addPhoto($workflowId, 'damage_photos/2026/03/photo.jpg', 'initial', 'Initial damage', 5);

// Transition status
$service->transitionStatus($workflowId, 'assessed', $userId, 'Professionally assessed');
$service->transitionStatus($workflowId, 'quote_requested', $userId, 'RFQ sent');

// Accept estimate
$service->acceptEstimate($estimateId);

// Transition to repair
$service->transitionStatus($workflowId, 'repair_approved', $userId);
$service->transitionStatus($workflowId, 'in_repair', $userId, 'Work started');

// Complete repair
$service->setActualCost($workflowId, 445.00);
$service->transitionStatus($workflowId, 'repaired', $userId);
$service->addPhoto($workflowId, 'damage_photos/2026/03/after.jpg', 'after_repair', 'Repair complete', 5);
$service->transitionStatus($workflowId, 'verified', $userId, 'Quality verified');

// Charge customer
$service->chargeCustomer($workflowId, 445.00, null); // null = no invoice
$service->transitionStatus($workflowId, 'charged', $userId);

// Close
$service->transitionStatus($workflowId, 'closed', $userId, 'Case closed');
```

## MeekroDB Usage Notes

The implementation follows MeekroDB rules:
- Uses `$db->getOne($table, $where=null, $columns=null)` for single records
- Uses `$db->get($table, $limit=null, $columns=null)` for multiple records
- Uses `$db->insert($table, $data)` returning affectedRows or last insert ID
- Uses `$db->update($table, $data)` returning affectedRows
- No `groupBy()` - instead uses raw SQL with `$db->rawQuery()` for aggregations
- No `->count()` - instead uses `COUNT(*)` in SELECT with `getValue()`

## Future Enhancements

1. **Drag-and-drop Kanban** - Implement card dragging for status transitions
2. **Bulk Operations** - Select multiple workflows for batch actions
3. **Email Notifications** - Auto-notify assigned users of status changes
4. **Insurance Integration** - Direct API connection to insurance providers
5. **Document Generation** - Auto-generate claim PDFs
6. **Cost Tracking** - Detailed cost breakdown and tracking
7. **Approval Workflows** - Multi-level approval for charges
8. **Mobile Support** - Responsive photo capture for field work
9. **Reporting** - Advanced analytics and cost trends
10. **Audit Export** - Export full audit trail for compliance

## File Structure

```
/db/migrations/
  20260318160000_damage_workflow.php

/src/services/
  DamageWorkflowService.php (new)
  DamageReportService.php (existing, extended)

/src/api/damage/
  workflows.php (new)
  workflow_get.php (new)
  workflow_create.php (new)
  workflow_transition.php (new)
  estimate_add.php (new)
  estimate_accept.php (new)
  photo_upload.php (new)
  assign_user.php (new)
  charge_customer.php (new)
  insurance_claim.php (new)
  dashboard.php (new)
  by_asset.php (new)
  by_client.php (new)
  [existing files: create.php, list.php, updateStatus.php]

/src/damage/
  index.php (new)
  damage_dashboard.twig (new)
  damage_detail.twig (new)
  [existing: business folder with damage-reports.php/twig]

/src/static-assets/
  damage_photos/ (new directory, auto-created)
```

## Testing Checklist

- [ ] Migration runs without errors
- [ ] Dashboard loads statistics correctly
- [ ] Workflow creation from damage report works
- [ ] Status transitions validate correctly
- [ ] Cost estimates can be added and accepted
- [ ] Photos upload and display
- [ ] User assignment works
- [ ] Customer charges deduct correctly
- [ ] Insurance claim submission works
- [ ] Status log records all transitions
- [ ] Dashboard filters work
- [ ] Kanban board displays all statuses
- [ ] Permission checks work correctly
- [ ] Integration with existing DamageReportService works

## Support

For questions about this implementation, refer to:
- MaintenanceService.php - Similar service pattern
- `/src/common/headSecure.php` - Auth/permission patterns
- Twig template examples in maintenance/
- MeekroDB documentation in vendor/
