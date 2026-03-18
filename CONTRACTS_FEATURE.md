# Digitales Vertragsmanagement (J1) - Implementation Guide

## Overview

The "Digitales Vertragsmanagement" (J1) feature provides comprehensive contract lifecycle management for MyRMS, including:

- Contract creation, editing, and versioning
- Contract templates with placeholders
- Terms & Conditions (AGB) management
- Electronic signature capture (canvas-based)
- PDF generation and export
- Contract expiration tracking
- Complete audit logging

## Architecture

### Database Schema

#### `contracts` table
Main contracts table with versioning and signature tracking.

**Key columns:**
- `id` - Primary key
- `instances_id` - Multi-tenancy support
- `projects_id` - Optional project linkage
- `clients_id` - Client reference
- `template_id` - Source template
- `title` - Contract title
- `content_html` - Full HTML content
- `status` - ENUM: `draft`, `sent`, `viewed`, `signed`, `active`, `expired`, `cancelled`
- `version` - Version number (incremented on edit)
- `valid_from`, `valid_until` - Date range
- `signed_at`, `signed_by_name`, `signed_by_ip` - Signature metadata
- `signature_data` - Canvas signature (as base64 PNG data)
- `signature_method` - ENUM: `canvas`, `docusign`, `adobe_sign`
- `created_by`, `created_at`, `updated_at` - Audit timestamps

#### `contract_versions` table
Historical versions of contracts for audit trail.

**Key columns:**
- `contracts_id` - FK to contracts
- `version` - Version number
- `content_html` - Content at that version
- `changed_by`, `changed_at`, `change_notes` - Change metadata

#### `contract_templates` table
Reusable contract templates with placeholder support.

**Key columns:**
- `instances_id` - Multi-tenancy
- `name`, `description`, `category` - Metadata
- `content_html` - Template content with placeholders `{placeholder.name}`
- `placeholders` - JSON array of available placeholders
- `agb_set_id` - FK to default AGB set
- `is_active`, `sort_order` - Management fields

#### `contract_agb_sets` table
Terms & Conditions (AGB) sets for inclusion in contracts.

**Key columns:**
- `instances_id` - Multi-tenancy
- `name`, `content_html`, `version` - Content
- `is_active`, `is_default` - Status

#### `contract_audit_log` table
Complete audit trail for compliance.

**Key columns:**
- `contracts_id` - FK to contracts
- `action` - ENUM: `created`, `edited`, `sent`, `viewed`, `signed`, `cancelled`, `expired`
- `users_id`, `ip_address`, `user_agent` - Actor information
- `details` - JSON metadata
- `created_at` - Timestamp

---

## Core Service: ContractService

Located in `/src/services/ContractService.php` (~600 lines)

### Key Methods

#### Contract Management

```php
getContracts(int $instanceId, ?string $status = null, ?int $clientId = null): array
```
Retrieve contracts with optional filtering by status or client.

```php
getContract(int $id): ?array
```
Get single contract with versions and audit log.

```php
createContract(array $data): int
```
Create new contract. Returns contract ID.

```php
updateContract(int $id, array $data, int $userId): bool
```
Update contract (creates new version). Returns success boolean.

```php
deleteContract(int $id): bool
```
Soft delete (sets status to `cancelled`).

---

#### Auto-Generation from Projects

```php
generateFromProject(int $projectId, int $templateId, int $instanceId): int
```
Generate contract from project data and template.

**Process:**
1. Loads project and client data
2. Loads template with placeholders
3. Replaces placeholders with actual values
4. Creates new contract in `draft` status

**Example placeholders:**
- `{kunde.name}` → Client name
- `{projekt.name}` → Project name
- `{projekt.startdatum}` → Start date (d.m.Y format)
- `{equipment.liste}` → Comma-separated equipment list
- `{equipment.gesamtpreis}` → Total equipment price
- `{datum.heute}` → Today's date

---

#### Template Management

```php
getTemplates(int $instanceId): array
createTemplate(array $data): int
updateTemplate(int $id, array $data): bool
```

Templates support:
- Category classification (rental, service, nda, general)
- Automatic placeholder extraction from content
- AGB set association
- Sort ordering

**Example template content:**
```html
<h1>{{projekt.name}} Mietvertrag</h1>

<p>Zwischen <strong>{{firma.name}}</strong> und <strong>{{kunde.firma}}</strong></p>

<h2>Leistungen</h2>
<p>{{equipment.liste}}</p>

<h2>Gesamtpreis</h2>
<p>{{equipment.gesamtpreis}}</p>

<h2>Gültig</h2>
<p>Von {{projekt.startdatum}} bis {{projekt.enddatum}}</p>
```

---

#### AGB Management

```php
getAgbSets(int $instanceId): array
createAgbSet(array $data): int
setDefaultAgb(int $id, int $instanceId): bool
```

Terms & Conditions sets can be:
- Associated with templates
- Set as instance default
- Versioned independently
- Marked as active/inactive

---

#### Signing & Delivery

```php
sendContract(int $contractId, string $recipientEmail, int $userId): bool
```
Send contract for signing via email. Generates secure token and signing URL.

```php
markViewed(int $contractId, string $ip, string $userAgent): bool
```
Track when recipient views contract. Updates status from `sent` to `viewed`.

```php
signContract(int $contractId, string $signerName, string $signatureData, string $ip): bool
```
Record signature (public endpoint, no auth required).
- Accepts canvas-based signature as base64 PNG
- Records signer name, IP address, timestamp
- Updates contract status to `signed`

---

#### Rendering & Export

```php
renderContract(int $contractId): string
```
Render full HTML with placeholders replaced. Used for display and PDF generation.

```php
getContractPdf(int $contractId): string
```
Generate PDF via dompdf. Returns binary PDF data.

```php
replacePlaceholders(string $html, array $data): string
```
Low-level placeholder replacement. Supports both `{key}` and `{{key}}` syntax.

---

#### Lifecycle Tracking

```php
getExpiringContracts(int $instanceId, int $daysAhead = 30): array
```
Get contracts approaching expiration. Used for reminders/notifications.

```php
getAuditLog(int $contractId): array
```
Retrieve complete audit trail for contract.

---

## API Endpoints

All endpoints located in `/src/api/contracts/`

### Authentication
All endpoints (except `/sign.php`) require `apiHeadSecure.php` and permission checks.

### Permissions
- `CONTRACTS:VIEW` - List and view contracts
- `CONTRACTS:EDIT` - Create, update, delete contracts
- `CONTRACTS:SEND` - Send contracts for signing

---

### GET `/api/contracts/list.php`

List contracts with optional filtering.

**Query Parameters:**
- `status` (optional) - Filter by status (draft, sent, signed, etc.)
- `client_id` (optional) - Filter by client ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Mietvertrag Projekt XYZ",
      "clients_id": 5,
      "status": "signed",
      "version": 2,
      "signed_at": "2026-03-15 14:30:00",
      "signed_by_name": "Max Mustermann",
      "valid_until": "2027-03-15",
      "created_at": "2026-03-10 10:00:00"
    }
  ],
  "count": 1
}
```

---

### GET `/api/contracts/get.php?id=123`

Get single contract with versions and audit log.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "title": "Contract",
    "content_html": "...",
    "status": "signed",
    "version": 2,
    "versions": [
      { "version": 1, "changed_at": "2026-03-10", "change_notes": null },
      { "version": 2, "changed_at": "2026-03-12", "change_notes": "Updated terms" }
    ],
    "audit_log": [
      { "action": "created", "users_id": 1, "created_at": "2026-03-10" },
      { "action": "edited", "users_id": 1, "created_at": "2026-03-12" },
      { "action": "sent", "users_id": 1, "created_at": "2026-03-12" }
    ]
  }
}
```

---

### POST `/api/contracts/create.php`

Create new contract.

**Request Body:**
```json
{
  "title": "Mietvertrag 2026",
  "clients_id": 5,
  "projects_id": 10,
  "template_id": 2,
  "content_html": "<h1>Vertrag</h1>...",
  "status": "draft",
  "valid_from": "2026-03-15",
  "valid_until": "2027-03-15"
}
```

**Response:**
```json
{
  "success": true,
  "id": 123,
  "message": "Vertrag erstellt"
}
```

---

### POST `/api/contracts/update.php`

Update contract (creates new version).

**Request Body:**
```json
{
  "id": 123,
  "title": "Updated Title",
  "content_html": "...",
  "change_notes": "Updated pricing terms"
}
```

---

### POST `/api/contracts/delete.php`

Soft delete contract (status → cancelled).

**Request Body:**
```json
{
  "id": 123
}
```

---

### POST `/api/contracts/generate.php`

Generate contract from project and template.

**Request Body:**
```json
{
  "project_id": 10,
  "template_id": 2
}
```

**Response:**
```json
{
  "success": true,
  "id": 124,
  "message": "Vertrag aus Projekt generiert"
}
```

---

### POST `/api/contracts/send.php`

Send contract for signing via email.

**Request Body:**
```json
{
  "id": 123,
  "recipient_email": "customer@example.com"
}
```

---

### POST `/api/contracts/sign.php` [PUBLIC]

Record signature on contract. **No authentication required.**

**Request Body:**
```json
{
  "contract_id": 123,
  "signer_name": "Max Mustermann",
  "signature_data": "data:image/png;base64,iVBORw0KGgo..."
}
```

The signing page (`/contracts/sign.php`) handles the UI and calls this endpoint.

---

### GET `/api/contracts/pdf.php?id=123`

Download contract as PDF. Requires `CONTRACTS:VIEW` permission.

**Response:** Binary PDF file

---

### GET/POST `/api/contracts/templates.php`

List and create contract templates.

**GET Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Mietvertrag Standard",
      "category": "rental",
      "placeholders": {
        "kunde.name": "",
        "projekt.name": "",
        "equipment.liste": ""
      },
      "is_active": true
    }
  ],
  "count": 1
}
```

**POST Request Body:**
```json
{
  "name": "New Template",
  "description": "Description",
  "category": "rental",
  "content_html": "...",
  "agb_set_id": 1
}
```

---

### GET/POST `/api/contracts/agb.php`

List and create AGB sets.

**GET Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Standard AGB 2026",
      "version": 1,
      "is_active": true,
      "is_default": true
    }
  ],
  "count": 1
}
```

**POST Request Body (Create):**
```json
{
  "name": "New AGB Set",
  "content_html": "..."
}
```

**POST Request Body (Set Default):**
```json
{
  "id": 1,
  "set_default": true
}
```

---

## Public Signing Page

**Location:** `/src/contracts/sign.php`

Standalone page for contract signing. No authentication required.

**Features:**
- Display full contract HTML
- Canvas-based signature capture
- Name input field
- AGB checkbox with modal display
- Real-time form validation
- Auto-redirect after signing

**URL Format:**
```
/contracts/sign.php?token=TOKEN&contract=CONTRACT_ID
```

**UI Components:**
1. Contract viewer (scrollable)
2. Signer name input
3. Signature canvas with clear button
4. AGB checkbox with modal
5. Submit and reset buttons
6. Status messages

**Canvas Signature:**
- Supports mouse and touch input
- Draws with black color, 2px width
- Exports as base64 PNG
- Sent to `/api/contracts/sign.php`

---

## Admin UI: Contract Management

**Location:** `/src/contracts/index.php`

Three-tab interface in AdminLTE3 template.

### Tab 1: Verträge (Contracts)
- List all contracts with status badges
- Filter by status
- Actions: View, Edit, Download PDF, Delete
- "New Contract" button
- "Generate from Project" button

### Tab 2: Vorlagen (Templates)
- Card-based template list
- Edit and delete buttons
- "New Template" button
- Category badges

### Tab 3: AGB-Sets
- Table of AGB sets
- Version tracking
- Default indicator
- Set as default button
- Delete button

---

## Placeholder System

**Available placeholders:**

| Placeholder | Description | Example |
|---|---|---|
| `{kunde.name}` | Client name | Mustermann GmbH |
| `{kunde.firma}` | Client company | Mustermann GmbH |
| `{kunde.adresse}` | Client address | Hauptstr. 1, 10115 Berlin |
| `{kunde.email}` | Client email | info@mustermann.de |
| `{projekt.name}` | Project name | Event Setup 2026 |
| `{projekt.startdatum}` | Project start (d.m.Y) | 15.03.2026 |
| `{projekt.enddatum}` | Project end (d.m.Y) | 17.03.2026 |
| `{equipment.liste}` | Equipment list | 10 x Stuhl, 5 x Tisch |
| `{equipment.gesamtpreis}` | Equipment total | 2.500,00 EUR |
| `{datum.heute}` | Today's date | 18.03.2026 |
| `{firma.name}` | Company name | MyRMS GmbH |
| `{firma.adresse}` | Company address | Technologiestr. 1, 10115 Berlin |

**Syntax:** Both `{placeholder}` and `{{placeholder}}` are supported.

---

## Contract Lifecycle

```
draft → sent → viewed → signed → active
  ↓       ↓       ↓        ↓        ↓
  └───→ cancelled → expired
```

**Status Transitions:**
- `draft` - Initial state after creation
- `sent` - Sent to recipient for signing (email with link)
- `viewed` - Recipient has accessed signing page
- `signed` - Signature recorded by recipient
- `active` - Signed and within validity period
- `expired` - Past `valid_until` date
- `cancelled` - Manually deleted or voided

---

## Audit Logging

Every contract action is logged to `contract_audit_log`:

```json
{
  "contracts_id": 123,
  "action": "signed",
  "users_id": null,
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "details": {
    "signer": "Max Mustermann",
    "ip": "192.168.1.100"
  },
  "created_at": "2026-03-18 14:30:00"
}
```

**Logged Actions:**
- `created` - Contract created
- `edited` - Content updated (version increment)
- `sent` - Sent for signing (with recipient email)
- `viewed` - Viewed by recipient
- `signed` - Signature recorded (with signer name)
- `cancelled` - Manually cancelled
- `expired` - Auto-marked expired

---

## Signature Storage

**Canvas Signature Format:**
- Base64-encoded PNG image
- 800×150 pixel canvas
- Black ink, 2px width
- Stored in `contracts.signature_data` column
- Can be rendered as `<img src="data:image/png;base64,...">`

**Example:**
```
data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAyAAAACWCAYAAAD0Iw3SAAAA...
```

---

## PDF Generation

Uses dompdf to convert HTML to PDF.

**Process:**
1. Call `ContractService->renderContract($contractId)`
2. Returns HTML with placeholders replaced
3. Call `ContractService->getContractPdf($contractId)`
4. Returns binary PDF data

**Features:**
- A4 portrait format
- Full HTML/CSS support
- Font embedding
- Page breaks

---

## Multi-Tenancy

All tables include `instances_id` column for complete data isolation:
- `contracts.instances_id`
- `contract_templates.instances_id`
- `contract_agb_sets.instances_id`

---

## Permissions

Define these in your permission system:

```php
'CONTRACTS:VIEW'  => 'View contracts and templates',
'CONTRACTS:EDIT'  => 'Create, update, delete contracts',
'CONTRACTS:SEND'  => 'Send contracts for signing',
```

---

## Migration

Run the migration to create all tables:

```bash
php vendor/bin/phinx migrate -e production
```

Migration file: `/db/migrations/20260318150000_contract_management.php`

---

## Integration Examples

### Generate contract from project
```php
$service = new ContractService($db);
$contractId = $service->generateFromProject(
    $projectId,
    $templateId,
    $instanceId,
    $userId
);
```

### Send for signing
```php
$service->sendContract($contractId, 'customer@example.com', $userId);
```

### Get expiring contracts
```php
$expiringIn30Days = $service->getExpiringContracts($instanceId, 30);
foreach ($expiringIn30Days as $contract) {
    // Send reminder email
}
```

### Render as HTML
```php
$html = $service->renderContract($contractId);
echo $html;
```

### Export to PDF
```php
$pdf = $service->getContractPdf($contractId);
header('Content-Type: application/pdf');
echo $pdf;
```

---

## Future Enhancements

Potential additions:
- DocuSign integration for electronic signatures
- Adobe Sign integration
- Multi-party signature workflows
- Contract templates from external services
- Contract renewal reminders
- Signature timestamp validation
- Contract version comparison UI
- Bulk contract operations
- Contract analytics/reporting
- Integration with email service providers

---

## Files Created

### Database
- `/db/migrations/20260318150000_contract_management.php`

### Services
- `/src/services/ContractService.php`

### API Endpoints
- `/src/api/contracts/list.php`
- `/src/api/contracts/get.php`
- `/src/api/contracts/create.php`
- `/src/api/contracts/update.php`
- `/src/api/contracts/delete.php`
- `/src/api/contracts/generate.php`
- `/src/api/contracts/send.php`
- `/src/api/contracts/sign.php` (public)
- `/src/api/contracts/pdf.php`
- `/src/api/contracts/templates.php`
- `/src/api/contracts/agb.php`

### UI
- `/src/contracts/index.php` (controller)
- `/src/contracts/sign.php` (public signing page)
- `/src/templates/contracts_index.twig` (AdminLTE3 template)

---

## Testing

### Create a test contract
```bash
curl -X POST http://localhost/api/contracts/create.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=..." \
  -d '{
    "title": "Test Contract",
    "clients_id": 1,
    "content_html": "<h1>Test</h1>",
    "valid_until": "2027-03-18"
  }'
```

### List contracts
```bash
curl http://localhost/api/contracts/list.php \
  -H "Cookie: PHPSESSID=..."
```

### Sign contract (public)
```bash
curl -X POST http://localhost/api/contracts/sign.php \
  -H "Content-Type: application/json" \
  -d '{
    "contract_id": 1,
    "signer_name": "Test Signer",
    "signature_data": "data:image/png;base64,..."
  }'
```

---

## Support

For questions or issues, refer to:
- Service documentation: `ContractService.php` inline comments
- API documentation: This guide
- Database schema: Migration file
- UI code: `contracts_index.twig`
