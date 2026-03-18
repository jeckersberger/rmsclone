# Contract Management (J1) - Integration Guide

## System Integration Checklist

### 1. Database & Migrations

✅ **Migration File Created:**
```
/db/migrations/20260318150000_contract_management.php
```

**To apply migration:**
```bash
cd /path/to/rmsclone
php vendor/bin/phinx migrate -e production
```

**Tables created:**
- `contracts`
- `contract_versions`
- `contract_templates`
- `contract_agb_sets`
- `contract_audit_log`

---

### 2. Permission System Integration

Add these permissions to your permission system:

```php
// In your permission definitions
[
    'CONTRACTS:VIEW' => [
        'name' => 'Verträge anzeigen',
        'description' => 'Anzeigen und Herunterladen von Verträgen',
    ],
    'CONTRACTS:EDIT' => [
        'name' => 'Verträge bearbeiten',
        'description' => 'Erstellen, Bearbeiten und Löschen von Verträgen',
    ],
    'CONTRACTS:SEND' => [
        'name' => 'Verträge versenden',
        'description' => 'Verträge zum Unterzeichnen versenden',
    ],
]
```

**Check in PHP:**
```php
if (hasPermission('CONTRACTS:VIEW')) {
    // User can view contracts
}
```

---

### 3. Navigation Menu Integration

Add link to main navigation menu:

**Location:** Your main template/layout file

```html
<li class="nav-item">
    <a class="nav-link" href="/contracts/">
        <i class="fas fa-file-contract"></i>
        <span>Vertragsmanagement</span>
    </a>
</li>
```

Or use `dropdown` if part of larger section:

```html
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="documentsMenu" role="button" data-bs-toggle="dropdown">
        <i class="fas fa-file"></i> Dokumente
    </a>
    <ul class="dropdown-menu" aria-labelledby="documentsMenu">
        <li><a class="dropdown-item" href="/contracts/">Vertragsmanagement</a></li>
        <li><a class="dropdown-item" href="/invoices/">Rechnungen</a></li>
    </ul>
</li>
```

---

### 4. Service Loading

The `ContractService` is autoloaded via PHP namespace or can be explicitly required:

```php
require_once __DIR__ . '/src/services/ContractService.php';
$service = new ContractService($db);
```

**Or with PSR-4 autoloader:**
```php
use MyRMS\Services\ContractService;
$service = new ContractService($db);
```

---

### 5. Email Integration

To send contracts via email, integrate with your email service:

**In `/src/services/ContractService.php`, update the `sendContract()` method:**

```php
public function sendContract(int $contractId, string $recipientEmail, int $userId): bool
{
    // ... existing code ...

    // Send email using your email service
    $emailService = new EmailService(); // Your email service
    $signingUrl = BASE_URL . '/contracts/sign.php?token=' . $token . '&contract=' . $contractId;

    $result = $emailService->send([
        'to' => $recipientEmail,
        'subject' => 'Vertrag zur Unterzeichnung: ' . $contract['title'],
        'body' => $this->buildSigningEmail($contract, $signingUrl),
        'html' => true,
    ]);

    if (!$result) {
        return false;
    }

    // ... rest of code ...
}

private function buildSigningEmail(array $contract, string $signingUrl): string
{
    return <<<HTML
    <h2>Vertrag zur Unterzeichnung</h2>
    <p>Sehr geehrte/r,</p>
    <p>bitte unterzeichnen Sie den folgenden Vertrag:</p>
    <p><strong>{$contract['title']}</strong></p>
    <p>
        <a href="{$signingUrl}" class="btn btn-primary">
            Vertrag unterzeichnen
        </a>
    </p>
    <p>Der Link ist 30 Tage gültig.</p>
    <p>Mit freundlichen Grüßen</p>
    HTML;
}
```

---

### 6. Project Integration

Link contracts to projects in project detail view:

**In your project controller:**

```php
$service = new ContractService($db);
$contracts = $service->getContracts(
    $instanceId,
    null,
    $clientId,
    $projectId // Add if method supports filtering by project
);
```

**In your project template, add contracts section:**

```twig
<div class="card mt-4">
    <div class="card-header">
        <h5>Verträge</h5>
    </div>
    <div class="card-body">
        <div id="projectContracts">
            <!-- Populated by JavaScript -->
        </div>
        <a href="/contracts/?project_id={{ project.id }}" class="btn btn-primary mt-3">
            <i class="fas fa-plus"></i> Neuer Vertrag
        </a>
    </div>
</div>

<script>
    // Load project contracts
    fetch('/api/contracts/list.php')
        .then(r => r.json())
        .then(data => {
            // Filter for current project
            const filtered = data.data.filter(c => c.projects_id === {{ project.id }});
            // Display in #projectContracts
        });
</script>
```

---

### 7. Client Integration

Display contracts in client profile:

**In your client controller:**

```php
$service = new ContractService($db);
$contracts = $service->getContracts(
    $instanceId,
    null,
    $clientId
);
```

**In client template:**

```twig
<h4>Verträge</h4>
<table class="table">
    <thead>
        <tr>
            <th>Titel</th>
            <th>Status</th>
            <th>Gültig bis</th>
            <th>Aktion</th>
        </tr>
    </thead>
    <tbody>
        {% for contract in contracts %}
        <tr>
            <td>{{ contract.title }}</td>
            <td>{{ contract.status|badge }}</td>
            <td>{{ contract.valid_until|date('d.m.Y') }}</td>
            <td>
                <a href="/api/contracts/pdf.php?id={{ contract.id }}" class="btn btn-sm btn-primary">
                    PDF
                </a>
            </td>
        </tr>
        {% endfor %}
    </tbody>
</table>
```

---

### 8. Dashboard Integration

Show contract statistics on dashboard:

```php
$service = new ContractService($db);

// Expiring contracts
$expiringContracts = $service->getExpiringContracts($instanceId, 30);

// Unsigned contracts
$unsignedContracts = $service->getContracts($instanceId, 'sent');

// Recently signed
$db->where('instances_id', $instanceId);
$db->where('status', 'signed');
$db->orderBy('signed_at', 'DESC');
$db->limit(5);
$recentlySigned = $db->get('contracts');
```

**Dashboard card:**

```twig
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3>{{ expiringContracts|length }}</h3>
                <p>Verträge ablaufen demnächst</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3>{{ unsignedContracts|length }}</h3>
                <p>Warten auf Unterschrift</p>
            </div>
        </div>
    </div>
</div>
```

---

### 9. Search/Filter Integration

Add contracts to global search:

```php
// In your global search/filter handler
$search = $_GET['q'] ?? '';

if ($search) {
    $db->where('instances_id', $instanceId);
    $db->where('title', '%' . $search . '%', 'LIKE');
    $contracts = $db->get('contracts', null, ['id', 'title', 'status']);

    $results[] = [
        'type' => 'contract',
        'items' => $contracts,
    ];
}
```

---

### 10. Workflow Automation

Create contract automatically on project creation:

```php
// In project creation handler
$projectId = createProject($data); // Your project creation

if (!empty($data['auto_create_contract_template'])) {
    $service = new ContractService($db);
    $contractId = $service->generateFromProject(
        $projectId,
        $data['template_id'],
        $instanceId,
        $userId
    );
}
```

---

### 11. Reporting Integration

Add contracts to reports:

```php
// Contract summary report
class ContractReporting {
    public function getSummary($instanceId, $startDate, $endDate) {
        return [
            'total_created' => $this->db->count('contracts',
                "WHERE instances_id = ? AND created_at BETWEEN ? AND ?",
                [$instanceId, $startDate, $endDate]),
            'total_signed' => $this->db->count('contracts',
                "WHERE instances_id = ? AND status = 'signed' AND signed_at BETWEEN ? AND ?",
                [$instanceId, $startDate, $endDate]),
            'total_expired' => $this->db->count('contracts',
                "WHERE instances_id = ? AND status = 'expired' AND valid_until BETWEEN ? AND ?",
                [$instanceId, $startDate, $endDate]),
        ];
    }
}
```

---

### 12. Notification/Reminder System

Create contract expiration reminders:

```php
// In your scheduled task/cron job
$service = new ContractService($db);

// Get expiring contracts
$expiringIn7Days = $service->getExpiringContracts($instanceId, 7);

foreach ($expiringIn7Days as $contract) {
    // Send notification to user
    $notification = new Notification();
    $notification->create([
        'users_id' => $contract['created_by'],
        'type' => 'contract_expiring',
        'title' => 'Vertrag läuft ab',
        'message' => $contract['title'] . ' läuft ab am ' . $contract['valid_until'],
        'related_id' => $contract['id'],
        'related_type' => 'contract',
    ]);
}
```

---

### 13. Audit Integration

If you have a system-wide audit log, add contract actions:

```php
// In ContractService, after actions:
$this->db->insert('system_audit_log', [
    'instances_id' => $instanceId,
    'users_id' => $userId,
    'action' => 'contract_signed',
    'entity_type' => 'contract',
    'entity_id' => $contractId,
    'changes' => json_encode(['status' => 'signed']),
    'created_at' => date('Y-m-d H:i:s'),
]);
```

---

### 14. API Webhook Integration

If you have webhook system, add contract events:

```php
// In ContractService methods:
private function triggerWebhook($event, $data) {
    $webhooks = $this->db->get('webhooks',
        "WHERE instances_id = ? AND events LIKE ?",
        [$data['instances_id'], '%' . $event . '%']);

    foreach ($webhooks as $webhook) {
        // Send POST to webhook URL
        curl_post($webhook['url'], [
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
        ]);
    }
}

// In createContract:
$this->triggerWebhook('contract.created', ['id' => $contractId, ...]);
```

---

### 15. Backup/Export Integration

Include contracts in your backup routine:

```php
// In backup handler
$contracts = $db->get('contracts', "WHERE instances_id = ?", [$instanceId]);
$contractVersions = $db->get('contract_versions',
    "WHERE contracts_id IN (SELECT id FROM contracts WHERE instances_id = ?)",
    [$instanceId]);
$contractAudit = $db->get('contract_audit_log',
    "WHERE contracts_id IN (SELECT id FROM contracts WHERE instances_id = ?)",
    [$instanceId]);

$backup['contracts'] = [
    'contracts' => $contracts,
    'versions' => $contractVersions,
    'audit' => $contractAudit,
];
```

---

## Testing Integration

### 1. Verify Service Loading
```php
require_once 'src/services/ContractService.php';
$service = new ContractService($db);
var_dump($service->getAvailablePlaceholders()); // Should return array
```

### 2. Test API Endpoints
```bash
# List contracts
curl -H "Cookie: PHPSESSID=..." http://localhost/api/contracts/list.php

# Create contract
curl -X POST http://localhost/api/contracts/create.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=..." \
  -d '{"title":"Test","clients_id":1,"content_html":"<p>Test</p>"}'
```

### 3. Test UI
- Navigate to `/contracts/`
- Create new contract
- Generate from project
- Test signing page at `/contracts/sign.php`

### 4. Test Permissions
- Log in as user without `CONTRACTS:VIEW`
- Verify 403 error on API endpoints
- Verify UI not accessible

---

## Dependencies

**Required:**
- PHP 8.3+
- MysqliDb (already in MyRMS)
- Phinx (migrations) - `robmorgan/phinx`
- Dompdf (PDF generation) - `dompdf/dompdf`

**Optional:**
- Email service (for sending contracts)
- Document signing services (DocuSign, Adobe Sign - future)

---

## Configuration

No additional configuration required. All settings are stored in database:
- `contract_templates` - Define available templates
- `contract_agb_sets` - Define AGB sets
- Per-instance configuration via `instances_id`

---

## Migration Rollback

If needed to rollback migration:

```bash
php vendor/bin/phinx rollback -e production
```

This will drop all contract-related tables. **Warning:** This deletes all contract data.

---

## Performance Optimization

For large instances:

1. **Index audit log by contract_id and created_at:**
   ```sql
   ALTER TABLE contract_audit_log
   ADD INDEX idx_contract_audit (contracts_id, created_at);
   ```

2. **Archive old audit logs:**
   ```php
   $db->delete('contract_audit_log',
       "WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 YEAR)");
   ```

3. **Paginate contract lists:**
   ```php
   $page = $_GET['page'] ?? 1;
   $perPage = 25;
   $offset = ($page - 1) * $perPage;

   $db->limit($perPage);
   $db->offset($offset);
   $contracts = $service->getContracts(...);
   ```

---

## Security Considerations

1. **Public Signing Endpoint** - `/api/contracts/sign.php` intentionally has no auth
   - Token-based validation recommended for production
   - Consider rate limiting

2. **Signature Data** - Base64 PNG stored in database
   - Consider encryption at rest
   - Verify client-side signature before sending

3. **Audit Log** - All actions logged
   - Review regularly for anomalies
   - Archive for compliance

4. **Multi-tenancy** - All queries include `instances_id`
   - Verify isolation in your setup

---

## Troubleshooting

### Contracts don't appear in list
- Check `instances_id` filter
- Verify permissions (`CONTRACTS:VIEW`)
- Check database table exists

### PDF generation fails
- Verify dompdf installed: `composer require dompdf/dompdf`
- Check HTML validity
- Check temp directory writable

### Signature not capturing
- Check canvas element exists in DOM
- Verify canvas.toDataURL() returns valid PNG
- Check browser console for errors

### Email not sending
- Implement email service in `sendContract()`
- Verify SMTP configuration
- Check recipient email valid

---

## Support Files

- **Service Documentation:** `CONTRACTS_FEATURE.md`
- **Quick Start:** `CONTRACTS_QUICK_START.md`
- **This File:** `CONTRACTS_INTEGRATION.md`

---

## Next Steps After Integration

1. Create default contract templates
2. Test contract generation from project
3. Configure email sending
4. Set up contract reminders in scheduler
5. Train users on contract management
6. Add to documentation/help system
7. Monitor audit logs for usage patterns
8. Plan for advanced features (e.g., DocuSign integration)
