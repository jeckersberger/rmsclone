# Digitales Vertragsmanagement (J1) - Quick Start

## Installation

1. **Run migration:**
   ```bash
   cd /path/to/rmsclone
   php vendor/bin/phinx migrate
   ```

2. **Define permissions** in your permission system:
   ```
   CONTRACTS:VIEW
   CONTRACTS:EDIT
   CONTRACTS:SEND
   ```

3. **Add navigation link** in your main menu to `/contracts/`

---

## Quick Usage

### Create Template
```php
$service = new ContractService($db);
$templateId = $service->createTemplate([
    'instances_id' => $instanceId,
    'name' => 'Mietvertrag',
    'category' => 'rental',
    'content_html' => '<h1>{{projekt.name}}</h1><p>{{kunde.firma}}</p>'
]);
```

### Generate Contract from Project
```php
$contractId = $service->generateFromProject(
    $projectId,    // ID of project
    $templateId,   // ID of template
    $instanceId,   // Instance ID
    $userId        // Current user ID
);
```

### Send for Signing
```php
$service->sendContract($contractId, 'customer@example.com', $userId);
// Email sent with link to /contracts/sign.php?token=XXX&contract=123
```

### Get Contract with Audit Log
```php
$contract = $service->getContract($contractId);
// Returns: contract, versions[], audit_log[]
```

### Export to PDF
```php
$pdf = $service->getContractPdf($contractId);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="contract.pdf"');
echo $pdf;
```

---

## Database Tables Created

- `contracts` - Main contracts table
- `contract_versions` - Historical versions
- `contract_templates` - Reusable templates
- `contract_agb_sets` - Terms & conditions
- `contract_audit_log` - Complete audit trail

---

## Available Placeholders

| Placeholder | Value |
|---|---|
| `{kunde.name}` | Client name |
| `{kunde.firma}` | Client company |
| `{kunde.email}` | Client email |
| `{projekt.name}` | Project name |
| `{projekt.startdatum}` | Start date (d.m.Y) |
| `{projekt.enddatum}` | End date (d.m.Y) |
| `{equipment.liste}` | Equipment list |
| `{equipment.gesamtpreis}` | Equipment total price |
| `{datum.heute}` | Today's date |
| `{firma.name}` | Company name |
| `{firma.adresse}` | Company address |

---

## Contract Status Flow

```
draft → sent → viewed → signed → active
  ↓       ↓       ↓        ↓        ↓
  └───→ cancelled → expired
```

---

## UI Endpoints

**Admin UI:** `/contracts/`
- List contracts
- Create/edit contracts
- Manage templates
- Manage AGB sets

**Public Signing:** `/contracts/sign.php?token=TOKEN&contract=ID`
- View contract
- Capture signature (canvas)
- Accept AGB
- Submit signature

---

## API Endpoints

```
GET  /api/contracts/list.php?status=draft&client_id=5
GET  /api/contracts/get.php?id=123
POST /api/contracts/create.php
POST /api/contracts/update.php
POST /api/contracts/delete.php
POST /api/contracts/generate.php
POST /api/contracts/send.php
POST /api/contracts/sign.php [PUBLIC - NO AUTH]
GET  /api/contracts/pdf.php?id=123
GET  /api/contracts/templates.php
POST /api/contracts/templates.php
GET  /api/contracts/agb.php
POST /api/contracts/agb.php
```

---

## Service Methods

### Basic CRUD
```php
getContracts($instanceId, $status = null, $clientId = null)
getContract($id)
createContract($data)
updateContract($id, $data, $userId)
deleteContract($id)
```

### Advanced
```php
generateFromProject($projectId, $templateId, $instanceId, $userId)
sendContract($contractId, $email, $userId)
signContract($contractId, $signerName, $signatureData, $ip)
renderContract($contractId)
getContractPdf($contractId)
replacePlaceholders($html, $data)
```

### Lifecycle
```php
getExpiringContracts($instanceId, $daysAhead = 30)
getAuditLog($contractId)
markViewed($contractId, $ip, $userAgent)
```

### Templates & AGB
```php
getTemplates($instanceId)
createTemplate($data)
updateTemplate($id, $data)
getAgbSets($instanceId)
createAgbSet($data)
setDefaultAgb($id, $instanceId)
```

---

## Files Location

```
/db/migrations/
  20260318150000_contract_management.php

/src/services/
  ContractService.php

/src/api/contracts/
  list.php
  get.php
  create.php
  update.php
  delete.php
  generate.php
  send.php
  sign.php
  pdf.php
  templates.php
  agb.php

/src/contracts/
  index.php
  sign.php

/src/templates/
  contracts_index.twig
```

---

## Permissions Required

- `CONTRACTS:VIEW` - List and view contracts
- `CONTRACTS:EDIT` - Create/update/delete contracts
- `CONTRACTS:SEND` - Send for signing

(Public signing endpoint does not require authentication)

---

## Example: Complete Workflow

```php
// 1. Create template
$templateId = $service->createTemplate([
    'instances_id' => $instanceId,
    'name' => 'Standard Mietvertrag',
    'category' => 'rental',
    'content_html' => '<h1>Mietvertrag {{projekt.name}}</h1>...'
]);

// 2. Generate from project
$contractId = $service->generateFromProject(
    $projectId, $templateId, $instanceId, $userId
);

// 3. Edit if needed
$service->updateContract($contractId, [
    'content_html' => '<h1>Updated...</h1>',
    'change_notes' => 'Updated terms'
], $userId);

// 4. Send to customer
$service->sendContract($contractId, 'customer@example.com', $userId);

// 5. Customer signs via /contracts/sign.php

// 6. Get signed contract
$contract = $service->getContract($contractId);
// $contract['status'] == 'signed'
// $contract['signed_by_name'] == 'Max Mustermann'
// $contract['signed_at'] == '2026-03-18 14:30:00'

// 7. Get PDF
$pdf = $service->getContractPdf($contractId);

// 8. Check audit trail
$auditLog = $contract['audit_log'];
// All actions tracked: created, edited, sent, viewed, signed
```

---

## Key Features

✅ **Versioning** - Each edit creates new version, old versions preserved
✅ **Templating** - Reusable templates with automatic placeholder extraction
✅ **Auto-fill** - Generate contracts from project data
✅ **Signatures** - Canvas-based signature capture (no auth required)
✅ **Audit Trail** - Complete logging of all actions
✅ **Multi-tenancy** - Full instance isolation
✅ **AGB Management** - Terms & conditions versioning
✅ **PDF Export** - Full HTML to PDF conversion
✅ **Status Tracking** - Detailed lifecycle management
✅ **Expiration** - Auto-tracking of contract validity

---

## Troubleshooting

### Migration fails
- Ensure Phinx is installed: `composer require robmorgan/phinx`
- Check database permissions

### Placeholders not replaced
- Verify placeholder syntax: `{placeholder.name}` or `{{placeholder.name}}`
- Check placeholder exists in `getAvailablePlaceholders()`

### Signature not saved
- Ensure signature canvas has minimum size (check canvas.toDataURL())
- Verify `signature_data` LONGTEXT column exists

### PDF generation fails
- Ensure dompdf is installed: `composer require dompdf/dompdf`
- Check HTML content validity

---

## Performance Notes

- Pagination on large contract lists recommended
- Audit log can grow large - consider archival after 7+ years
- PDF generation is synchronous - consider async jobs for bulk exports

---

## Security Notes

- Public signing page has no auth (intentional - to sign contracts)
- All API endpoints check permissions except `/sign.php`
- Signature data stored as-is (base64 PNG) - validate on frontend before sending
- Audit log IP/user-agent for fraud detection

---

## Next Steps

1. ✅ Run migration
2. ✅ Define permissions
3. ✅ Create first template
4. ✅ Generate test contract from project
5. ✅ Test signing flow
6. ✅ Integrate with email system for contract delivery
7. ✅ Add contract renewal reminders
8. ✅ Build reporting/analytics
