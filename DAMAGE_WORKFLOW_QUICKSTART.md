# Schadenmanagement (K3) - Quick Start Guide

## Installation

### 1. Run Database Migration
```bash
cd /path/to/rmsclone
vendor/bin/phinx migrate -e production
```

This creates:
- `damage_workflows` - Main workflow tracking
- `damage_workflow_log` - Audit trail
- `damage_cost_estimates` - Vendor quotes
- `damage_photos` - Photo documentation

### 2. Permissions Setup
Add these permissions to your permission system:
```
DAMAGE:VIEW   - View workflows and reports
DAMAGE:EDIT   - Create/modify workflows, add estimates, upload photos
DAMAGE:CHARGE - Charge customers for repairs
```

### 3. Access Points
- **Dashboard**: `/src/damage/index.php`
- **Workflow Detail**: `/src/damage/index.php?view=detail&damage_report_id=123`

## Quick Workflow

### Scenario: Handle a Damaged Asset

1. **Asset Check-in with Damage**
   - Create damage report via existing system
   - Navigate to `/src/damage/index.php?view=detail&damage_report_id=123`

2. **Initial Assessment**
   - Status: `reported`
   - Upload initial damage photos
   - Add internal notes
   - Assign to technician
   - Transition to `assessed`

3. **Get Repair Quotes**
   - Transition to `quote_requested`
   - API: Add cost estimates via estimate_add.php
   - Vendors submit quotes
   - Transition to `quote_received`
   - Accept best estimate via estimate_accept.php

4. **Repair Execution**
   - Transition to `repair_approved`
   - Transition to `in_repair`
   - Upload during-repair photos
   - Document actual cost
   - Transition to `repaired`

5. **Verification & Closure**
   - Upload after-repair photos
   - Transition to `verified`
   - Charge customer OR submit insurance claim
   - Transition to `charged` or `insurance_claimed`
   - Transition to `closed`

## Common API Calls

### Get All Open Workflows
```bash
curl -X POST http://localhost/api/damage/workflows.php \
  -d "severity=moderate"
```

### Create Workflow
```bash
curl -X POST http://localhost/api/damage/workflow_create.php \
  -d "damage_report_id=42&severity=moderate&estimated_cost=500"
```

### Add Cost Estimate
```bash
curl -X POST http://localhost/api/damage/estimate_add.php \
  -d "workflow_id=1&vendor_name=RepairCo&amount=450&description=Screen%20replacement"
```

### Accept Estimate
```bash
curl -X POST http://localhost/api/damage/estimate_accept.php \
  -d "estimate_id=5"
```

### Transition Status
```bash
curl -X POST http://localhost/api/damage/workflow_transition.php \
  -d "workflow_id=1&new_status=in_repair&notes=Work%20started"
```

### Upload Photo
```bash
curl -X POST http://localhost/api/damage/photo_upload.php \
  -F "damage_report_id=42" \
  -F "photo=@/path/to/photo.jpg" \
  -F "photo_type=initial" \
  -F "description=Damage at screen"
```

### Charge Customer
```bash
curl -X POST http://localhost/api/damage/charge_customer.php \
  -d "workflow_id=1&amount=445&charge_type=customer"
```

### Submit Insurance Claim
```bash
curl -X POST http://localhost/api/damage/insurance_claim.php \
  -d "workflow_id=1&claim_id=INS-2026-12345"
```

## Dashboard Statistics

GET `/api/damage/dashboard.php` returns:
```json
{
  "success": true,
  "data": {
    "stats": {
      "status_counts": {
        "reported": 5,
        "assessed": 3,
        "in_repair": 2,
        "closed": 45
      },
      "severity_counts": {
        "minor": 10,
        "moderate": 15,
        "major": 20,
        "total_loss": 5
      },
      "open_count": 25,
      "total_estimated_cost_this_month": 12500.50,
      "total_actual_cost_this_month": 11800.25,
      "avg_resolution_days": 8
    }
  }
}
```

## Status Flow Diagram

```
REPORTED → ASSESSED ─┬→ QUOTE_REQUESTED → QUOTE_RECEIVED ─┬→ REPAIR_APPROVED
                     │                                     │
                     └─────────────────────────────────────┘
                                                            ↓
                                                        IN_REPAIR
                                                            ↓
                                                        REPAIRED
                                                            ↓
                                                        VERIFIED
                                                         ├→ CHARGED → CLOSED
                                                         ├→ INSURANCE_CLAIMED → CLOSED
                                                         └→ CLOSED

All paths can shortcut to CLOSED at any point for minor/rejected cases
```

## Important Notes

### MeekroDB Rules
- Service uses `getOne()`, `get()`, `insert()`, `update()` methods
- No `groupBy()` - uses raw SQL for aggregations
- No `->count()` - uses `COUNT(*)` with `getValue()`
- All returns from `affectedRows()` check

### Security
- All API endpoints require authentication
- Permission checks on every endpoint
- Instance isolation via `instances_id`
- SQL injection prevented by MeekroDB parameter binding

### File Storage
- Photos stored in `/src/static-assets/damage_photos/YYYY/MM/`
- Directory auto-created on first upload
- Files: `damage_<uuid>_<timestamp>.<ext>`

### Photo Types
- `initial` - Damage documentation at check-in
- `during_repair` - Progress photos
- `after_repair` - Completion documentation

## Troubleshooting

### Workflow won't transition to requested status
- Check `getValidTransitions()` returns the status
- Status transitions are validated - only specific flows allowed
- See status diagram above

### Photos not uploading
- Check `/src/static-assets/` directory is writable
- `damage_photos/` directory will auto-create
- Verify `DAMAGE:EDIT` permission

### Costs not updating
- Workflow must have `estimated_cost` set during creation
- `actual_cost` set via `setActualCost()` method
- Estimates can override estimated_cost when accepted

### Dashboard stats showing wrong counts
- Stats calculated from ALL workflows in instance
- Open workflows = not 'closed' status
- Costs calculated from this month (Y-m-01)

## Integration Points

### With DamageReportService
```php
$reportService = new DamageReportService($db);
$workflowService = new DamageWorkflowService($db);

// Create report
$reportId = $reportService->createReport(
    $instanceId, $assetId, $projectId, $data, $userId
);

// Create workflow for that report
$workflowId = $workflowService->createWorkflow(
    $reportId, 'moderate', 500.00, null, $instanceId
);
```

### With Maintenance System
When repair is in `in_repair` status:
- Can also track via maintenance jobs system
- Cost tracking separate but coordinated
- Status updates flow from workflow to maintenance

## Performance Notes

- Dashboard stats aggregation uses raw SQL (indexed queries)
- Workflow retrieval with related data uses joins
- Photos gallery loads on demand
- Status log queries ordered by date (index on created_at)

## Next Steps

1. Run migration
2. Set up permissions in your system
3. Add menu link to `/src/damage/index.php`
4. Test with sample damage report
5. Configure email notifications (future)
6. Integrate with invoice/billing system (future)
