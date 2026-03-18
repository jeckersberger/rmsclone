# Online Booking Portal - Quick Start Guide

## 5-Minute Setup

### Step 1: Run Migration
```bash
vendor/bin/phinx migrate
```
Creates tables: `portal_config`, `portal_inquiries`, `portal_sessions`

### Step 2: Access Portal
```
Admin:   http://yoursite.com/src/portal/admin.php
Public:  http://yoursite.com/src/portal/public/index.php
```

### Step 3: Configure (Admin Panel)
1. Navigate to `/src/portal/admin.php`
2. Check "Enable Portal"
3. Set title, description, logo, color
4. Save

### Step 4: Test
1. Visit `/src/portal/public/?action=catalog`
2. Browse equipment
3. Submit test inquiry

---

## Common Tasks

### Enable Portal
```php
// In admin interface or programmatically:
$portalService = new BookingPortalService($DBLIB);
$portalService->savePortalConfig($instanceId, ['is_active' => 1]);
```

### Get All Inquiries
```php
$inquiries = $portalService->getInquiries($instanceId);
// Filter by status:
$inquiries = $portalService->getInquiries($instanceId, 'new');
```

### Convert Inquiry to Project
```php
$projectId = $portalService->convertToProject($inquiryId, $userId, $instanceId);
```

### Check Equipment Availability
```php
$availability = $portalService->checkAvailability(
    $assetTypeId,
    '2024-04-01',  // start
    '2024-04-07',  // end
    2,             // quantity
    $instanceId
);

if ($availability['available']) {
    echo "Available: " . $availability['available_count'];
    echo "Price: €" . $availability['total_price'];
}
```

### Register Client Programmatically
```php
$clientId = $portalService->registerPortalClient([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'SecurePass123',
    'phone' => '+49123456789',
    'company' => 'ACME Corp'
], $instanceId);

$token = $portalService->createPortalSession($clientId);
```

---

## API Usage Examples

### Browser Console (JavaScript)

#### Get Config
```javascript
fetch('/api/portal/config.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'instances_id=1'
}).then(r => r.json()).then(d => console.log(d.response));
```

#### Get Catalog
```javascript
fetch('/api/portal/catalog.php', {
    method: 'POST',
    body: new URLSearchParams({
        instances_id: 1,
        search: 'projector'
    })
}).then(r => r.json()).then(d => console.log(d.response.items));
```

#### Check Availability
```javascript
fetch('/api/portal/availability.php', {
    method: 'POST',
    body: new URLSearchParams({
        instances_id: 1,
        asset_type_id: 1,
        start_date: '2024-04-01',
        end_date: '2024-04-07',
        quantity: 2
    })
}).then(r => r.json()).then(d => console.log(d.response));
```

#### Submit Inquiry (Guest)
```javascript
const items = [{
    asset_type_id: 1,
    asset_type_name: 'Projector',
    quantity: 2,
    price: 150
}];

fetch('/api/portal/inquiry.php', {
    method: 'POST',
    body: new URLSearchParams({
        instances_id: 1,
        guest_name: 'John Doe',
        guest_email: 'john@example.com',
        guest_phone: '+49123456789',
        rental_start: '2024-04-01',
        rental_end: '2024-04-07',
        items: JSON.stringify(items),
        message: 'Deliver to venue'
    })
}).then(r => r.json()).then(d => alert('Inquiry #' + d.response.inquiry_id));
```

#### Register
```javascript
fetch('/api/portal/register.php', {
    method: 'POST',
    body: new URLSearchParams({
        instances_id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        password: 'SecurePass123',
        phone: '+49123456789',
        company: 'ACME'
    })
}).then(r => r.json()).then(d => {
    localStorage.setItem('portalToken', d.response.token);
    console.log('Logged in! Token:', d.response.token);
});
```

#### Login
```javascript
fetch('/api/portal/login.php', {
    method: 'POST',
    body: new URLSearchParams({
        instances_id: 1,
        email: 'john@example.com',
        password: 'SecurePass123'
    })
}).then(r => r.json()).then(d => {
    localStorage.setItem('portalToken', d.response.token);
    console.log('Login successful!');
});
```

#### Get My Inquiries (Authenticated)
```javascript
const token = localStorage.getItem('portalToken');
fetch('/api/portal/my_inquiries.php', {
    method: 'POST',
    body: new URLSearchParams({
        portal_session: token
    })
}).then(r => r.json()).then(d => console.log(d.response.inquiries));
```

---

## SQL Queries

### List All Inquiries
```sql
SELECT id, guest_name, guest_email, rental_start, rental_end,
       status, created_at
FROM portal_inquiries
WHERE instances_id = 1
ORDER BY created_at DESC;
```

### Count by Status
```sql
SELECT status, COUNT(*) as count
FROM portal_inquiries
WHERE instances_id = 1
GROUP BY status;
```

### Find Overdue Inquiries
```sql
SELECT * FROM portal_inquiries
WHERE instances_id = 1
AND status = 'new'
AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Get Portal Config
```sql
SELECT * FROM portal_config WHERE instances_id = 1;
```

### Active Sessions
```sql
SELECT client_id, token, expires_at
FROM portal_sessions
WHERE expires_at > NOW();
```

---

## Debugging

### Enable Debug Mode
```php
// In .env or config
DEV_MODE=true
```

### Check Logs
```bash
tail -f logs/portal.log
tail -f logs/api.log
```

### Test Endpoint
```bash
curl -X POST http://localhost/api/portal/config.php \
  -d "instances_id=1"
```

### Browser DevTools
1. Open DevTools (F12)
2. Network tab → API calls
3. Console → check for errors
4. Local Storage → check portal token

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Portal not showing | Check `is_active=1` in portal_config |
| 404 errors | Verify file paths (absolute) |
| No availability | Check asset assignments overlap dates |
| Login fails | Verify password is 8+ chars, email format |
| Admin denied | Check user has PORTAL:VIEW permission |
| Session expired | Token expires after 30 days, re-login |
| CORS error | Check origin is in CORS_ALLOWED_ORIGIN |

---

## File Reference

```
Core Files:
  BookingPortalService.php    - Main business logic
  20260318230000_booking_portal.php - Database tables

API Endpoints:
  /api/portal/config.php      - Portal configuration
  /api/portal/catalog.php     - Equipment list
  /api/portal/availability.php - Check availability
  /api/portal/inquiry.php     - Submit inquiry
  /api/portal/register.php    - Register user
  /api/portal/login.php       - Login user
  /api/portal/my_inquiries.php - Client inquiries
  /api/portal/admin_*.php     - Admin endpoints (4)

Frontend:
  /src/portal/public/index.php    - Portal entry point
  /src/portal/public/layout.twig  - Base template
  /src/portal/public/*.twig       - Page templates (6)

Admin:
  /src/portal/admin.php           - Admin controller
  /src/portal/portal_admin.twig   - Admin template
```

---

## Permissions

Add to your role system:
```php
'PORTAL:VIEW'       // View portal and inquiries
'PORTAL:CONFIGURE'  // Configure portal and convert inquiries
```

---

## Default Settings

| Setting | Default | Notes |
|---------|---------|-------|
| Portal enabled | 0 (disabled) | Enable in admin |
| Show prices | 1 (visible) | Toggle in config |
| Require registration | 1 (required) | Can allow guests |
| Admin approval | 1 (required) | Can auto-approve |
| Session expiry | 30 days | Configurable |
| Password min | 8 chars | Validated on register |

---

## Environment Variables

```
# .env
DEV_MODE=true|false
CORS_ALLOWED_ORIGIN=https://yoursite.com
ADMIN_EMAIL=admin@yoursite.com
```

---

## Useful Commands

```bash
# Run migration
vendor/bin/phinx migrate

# Check database
mysql -u user -p database -e "SELECT * FROM portal_config;"

# Clear old sessions (manual cleanup)
DELETE FROM portal_sessions WHERE expires_at < NOW();

# Count inquiries
mysql -u user -p database -e "SELECT COUNT(*) FROM portal_inquiries;"

# Export inquiries to CSV
mysql -u user -p database -e "SELECT * FROM portal_inquiries;" > inquiries.csv
```

---

## Key Methods

### BookingPortalService

```php
// Configuration
getPortalConfig(int $instanceId): ?array
savePortalConfig(int $instanceId, array $data): bool

// Catalog
getPublicCatalog(int $instanceId, ?int $categoryId, ?string $search): array
getCategories(int $instanceId): array

// Availability
checkAvailability(int $assetTypeId, string $start, string $end, int $qty, int $instanceId): array

// Inquiries
submitInquiry(array $data, int $instanceId): int
getInquiries(int $instanceId, ?string $status = null): array
getInquiry(int $id): ?array
updateInquiryStatus(int $id, string $status, ?int $projectId = null): bool
convertToProject(int $inquiryId, int $userId, int $instanceId): int
getClientInquiries(int $clientId): array

// Authentication
registerPortalClient(array $data, int $instanceId): int
loginPortalClient(string $email, string $password, int $instanceId): ?array
createPortalSession(?int $clientId = null): string
validatePortalSession(string $token): ?array
```

---

## Next Steps

1. ✅ Run migration
2. ✅ Configure portal
3. ✅ Test public interface
4. ✅ Test admin interface
5. ✅ Configure permissions
6. ✅ Publish portal URL
7. ✅ Monitor inquiries
8. ✅ Convert to projects

---

For detailed docs, see:
- `PORTAL_SETUP.md` - Complete setup guide
- `API_PORTAL_REFERENCE.md` - Detailed API docs
- `PORTAL_IMPLEMENTATION.md` - Architecture & features
