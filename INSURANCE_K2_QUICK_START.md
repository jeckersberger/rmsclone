# Insurance Management (K2) - Quick Start Guide

## Overview
Complete insurance management module for MyRMS with policy tracking, asset coverage, client certificates, and claims management.

## Installation

### 1. Run Database Migration
```bash
phinx migrate -e production
```
Creates 4 tables: `insurance_policies`, `insurance_asset_coverage`, `insurance_client_certificates`, `insurance_claims`

### 2. Access the Module
Navigate to: `/insurance/`

Requires permissions:
- `INSURANCE:VIEW` - View policies, claims, certificates
- `INSURANCE:EDIT` - Create, update, delete policies and claims

## Core Features

### Policy Management
- **Create policies** with 5 coverage types: all_risk, transport, liability, equipment, event
- **Track coverage amounts**, premiums, validity dates, deductibles
- **Store policy documents** and notes
- **Monitor expiring policies** (alerts within 30 days)

### Asset Coverage
- Assign policies to individual assets or entire asset types
- Track coverage status: covered, uncovered, expiring
- Identify coverage gaps automatically
- Support for standard assets and stock items

### Client Certificates
- Upload and verify client insurance documents
- Types: liability, property, event
- Expiry tracking and verification workflow
- Audit trail with verified_by and verified_at

### Claims Management
- Create claims linked to damage workflows (K3 integration)
- Track claim status: draft → submitted → under_review → approved/rejected → paid/closed
- Record claimed vs. approved amounts
- Automatic payout date tracking

### Dashboard & Analytics
- Total coverage amount across all policies
- Monthly insurance premium total
- Open claims count and status breakdown
- Year-to-date claimed and approved amounts
- Policy count by coverage type
- Uncovered assets count
- Expiring policies alert

## API Endpoints

### Policies
```
GET  /api/insurance/policies.php                 - List all policies
POST /api/insurance/policies.php                 - Create new policy
POST /api/insurance/policy_edit.php              - Update policy
POST /api/insurance/policy_delete.php            - Delete policy
```

### Asset Coverage
```
POST /api/insurance/coverage.php                 - Add/remove coverage
GET  /api/insurance/check_coverage.php           - Check asset coverage
GET  /api/insurance/uncovered.php                - List uncovered assets
GET  /api/insurance/expiring.php                 - List expiring policies
```

### Client Certificates
```
GET  /api/insurance/certificates.php             - Get client certificates
POST /api/insurance/certificates.php             - Upload certificate
POST /api/insurance/certificate_verify.php       - Verify certificate
```

### Claims
```
GET  /api/insurance/claims.php                   - List claims
POST /api/insurance/claims.php                   - Create claim
POST /api/insurance/claim_update.php             - Update claim status
```

### Dashboard
```
GET  /api/insurance/dashboard.php                - Get dashboard statistics
```

## Service Usage (PHP)

```php
require_once 'src/services/InsuranceService.php';

$svc = new InsuranceService($DBLIB);
$instanceId = 1; // Current instance

// Get all active policies
$policies = $svc->getPolicies($instanceId, true);

// Check if asset is covered
$status = $svc->getCoverageStatus($assetId); // 'covered', 'uncovered', 'expiring'

// Get dashboard stats
$stats = $svc->getDashboardStats($instanceId);

// Create claim
$claimId = $svc->createClaim([
    'instances_id' => $instanceId,
    'policy_id' => 5,
    'claim_number' => 'CLM-2026-001',
    'claimed_amount' => 5000.00,
]);

// Update claim status
$svc->updateClaimStatus($claimId, 'approved', 4500.00);
```

## Database Schema

### insurance_policies
```
id, instances_id, name, provider, policy_number, coverage_type,
coverage_amount, deductible, premium_monthly, valid_from, valid_until,
document_path, notes, is_active, created_at, updated_at
```

### insurance_asset_coverage
```
id, policy_id, asset_id (nullable), asset_type_id (nullable), 
stock_item_id (nullable), created_at
```

### insurance_client_certificates
```
id, instances_id, client_id, certificate_type, file_path, valid_until,
verified, verified_by, verified_at, created_at
```

### insurance_claims
```
id, instances_id, policy_id, damage_workflow_id (nullable),
claim_number, claim_date, description, claimed_amount, approved_amount,
status, payout_date, created_at, updated_at
```

## Dashboard Tabs

1. **POLICEN** - Card view of all active policies with expiry status
2. **DECKUNGSLÜCKEN** - Table of uncovered assets with assignment option
3. **KUNDENNACHWEISE** - Client certificate management and verification
4. **SCHADENSMELDUNGEN** - Claims tracking with status and amount details

## Integration with K3 (Damage Workflow)

Insurance claims can be automatically linked to damage workflows:
```php
$claimId = $svc->createClaim([
    'instances_id' => $instanceId,
    'policy_id' => 5,
    'damage_workflow_id' => $workflowId,  // Link to K3
    'claimed_amount' => 5000.00,
]);
```

## Permission System

Two permissions control access:

- **INSURANCE:VIEW** - Read-only access to all insurance data
- **INSURANCE:EDIT** - Create, update, delete policies and claims

Both must be granted at the instance level.

## Key Methods

### Policy Management
- `getPolicies($instanceId, $activeOnly)` → array
- `getPolicy($id)` → array with covered assets
- `createPolicy($data)` → int (policy ID)
- `updatePolicy($id, $data)` → bool
- `deletePolicy($id)` → bool

### Coverage & Status
- `addAssetCoverage($policyId, $entityType, $entityId)` → bool
- `removeAssetCoverage($policyId, $entityType, $entityId)` → bool
- `checkCoverage($assetId, $instanceId)` → array
- `getCoverageStatus($assetId)` → string ('covered'|'uncovered'|'expiring')
- `getUncoveredAssets($instanceId)` → array
- `getExpiringPolicies($instanceId, $daysAhead)` → array

### Client Certificates
- `getClientCertificates($clientId, $instanceId)` → array
- `uploadClientCertificate($clientId, $data)` → int (cert ID)
- `verifyCertificate($certId, $userId)` → bool

### Claims Management
- `getClaims($instanceId, $status)` → array
- `createClaim($data)` → int (claim ID)
- `updateClaimStatus($claimId, $status, $approvedAmount)` → bool

### Analytics
- `getDashboardStats($instanceId)` → array (8 KPIs)
- `calculateMonthlyPremium($instanceId)` → float

## Files Location

```
/db/migrations/20260318170000_insurance_management.php    - Database schema
/src/services/InsuranceService.php                        - Service layer (500 lines)
/src/api/insurance/                                       - 12 API endpoints
/src/insurance/index.php                                  - Main controller
/src/insurance/insurance_index.twig                       - Dashboard template
```

## Support & Troubleshooting

### Migration Failed
- Ensure Phinx is configured correctly
- Check database user has CREATE/ALTER permissions
- Run: `phinx status` to check migration state

### Permission Denied Errors
- User must have INSURANCE:VIEW or INSURANCE:EDIT permissions assigned
- Check instance-level permissions in admin panel

### Coverage Not Showing
- Assets must be linked to policies via `addAssetCoverage()`
- Check that policies are marked as active (`is_active = true`)
- Verify policy valid_until date is in the future

### Uncovered Assets List Empty
- Verify assets exist in the instance
- Check asset records have valid assetTypes_id

## Future Enhancements

- Policy detail and claim detail views (templates ready in controller)
- Batch import/export of policies
- Email notifications for expiring policies
- PDF report generation
- Insurance provider API integration
- Claim document upload and management
- Advanced search and filtering
- Compliance audit logs
