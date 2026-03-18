# Online Booking Portal - Setup Guide

## Installation Steps

### 1. Run Database Migration
```bash
# Run the migration using Phinx
vendor/bin/phinx migrate -e production

# Or manually execute the migration file
db/migrations/20260318230000_booking_portal.php
```

This creates:
- `portal_config` table
- `portal_inquiries` table
- `portal_sessions` table

### 2. Add Portal Navigation to Admin Menu

Edit your main navigation/menu file to add portal link:

```php
[
    'name' => 'Booking Portal',
    'icon' => 'fas fa-store',
    'url' => '/src/portal/admin.php',
    'permission' => 'PORTAL:VIEW',
    'badge' => 'Beta'
]
```

### 3. Create Permissions

Add these permissions to your role system:

```sql
INSERT INTO permissions (name, slug, description) VALUES
('View Booking Portal', 'PORTAL:VIEW', 'View portal settings and inquiries'),
('Configure Booking Portal', 'PORTAL:CONFIGURE', 'Configure portal and convert inquiries to projects');
```

Or assign to roles:
```php
$AUTH->assignPermissionToRole('PORTAL:VIEW', 'admin');
$AUTH->assignPermissionToRole('PORTAL:CONFIGURE', 'admin');
```

### 4. Configure Portal (via Admin Interface)

1. Navigate to `/src/portal/admin.php`
2. Fill in portal settings:
   - ✓ Enable Portal toggle
   - Title (e.g., "Equipment Rental Portal")
   - Description (short tagline)
   - Logo URL
   - Primary Color (color picker)
   - Show Prices (if applicable)
   - Require Registration
   - Require Admin Approval
   - Terms & Conditions (optional HTML)
3. Click "Save Configuration"

### 5. Set Up Web Routing (Optional but Recommended)

For cleaner URLs, add URL rewrite rules:

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteBase /

# Portal routing
RewriteRule ^portal/?$ /src/portal/public/index.php [QSA,L]
RewriteRule ^portal/(.*)$ /src/portal/public/index.php?action=$1 [QSA,L]
```

This allows:
```
http://example.com/portal
http://example.com/portal/catalog
http://example.com/portal/login
http://example.com/portal/register
```

#### Nginx
```nginx
location ~ ^/portal {
    rewrite ^/portal/?(.*)$ /src/portal/public/index.php?action=$1 last;
}
```

### 6. Configure Email Notifications (Optional)

Add email sending on inquiry submission:

```php
// In BookingPortalService::submitInquiry()
$MAILER->sendTemplate('inquiry_notification', [
    'inquiry_id' => $inquiryId,
    'guest_email' => $data['guest_email'],
    'guest_name' => $data['guest_name'],
    // ...
]);
```

### 7. Database Indexes

Verify indexes are created (automatically by migration):
```sql
-- Check portal_inquiries indexes
SHOW INDEXES FROM portal_inquiries;
```

Expected indexes:
- instances_id
- client_id
- status
- project_id

---

## Usage Guide

### For Customers/Public Users

1. **Browse Equipment**
   - Visit `/portal` or `/src/portal/public/index.php`
   - View equipment catalog with availability
   - Search by name
   - Filter by category

2. **Check Availability**
   - Click "View Details" on any equipment
   - Select start and end dates
   - Enter quantity needed
   - See instant price calculation

3. **Submit Inquiry**
   - Add items to cart
   - Fill in contact information (if guest)
   - Add optional message/special requests
   - Submit inquiry
   - Receive confirmation with inquiry ID

4. **Login/Register**
   - Create account for future bookings
   - Login to view booking history
   - Manage profile information

### For Administrators

1. **Configure Portal**
   - Navigate to `/src/portal/admin.php`
   - Customize branding, colors, pricing
   - Set registration and approval requirements

2. **Manage Inquiries**
   - View all incoming inquiries
   - Filter by status
   - Update inquiry status:
     - new → reviewed (received)
     - reviewed → quoted (sent price quote)
     - quoted → accepted (customer confirmed)
     - accepted → rejected (customer declined)
     - any → cancelled (user cancelled)

3. **Convert to Project**
   - Click "→ Project" button on inquiry
   - Creates MyRMS project from inquiry
   - Automatically links to customer
   - Includes rental dates and items

4. **Monitor Statistics**
   - Quick stats sidebar shows totals
   - Status breakdown chart
   - New inquiries tracker

---

## Configuration Options

### Portal Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| is_active | BOOLEAN | 0 | Enable/disable portal |
| portal_title | VARCHAR(255) | NULL | Portal name |
| portal_description | TEXT | NULL | Portal tagline |
| logo_path | VARCHAR(255) | NULL | Logo image URL |
| primary_color | VARCHAR(7) | #2563eb | Brand color (hex) |
| show_prices | BOOLEAN | 1 | Display pricing publicly |
| require_registration | BOOLEAN | 1 | Require user registration |
| require_admin_approval | BOOLEAN | 1 | Require approval of bookings |
| terms_html | LONGTEXT | NULL | Terms & conditions HTML |

### Access Control

**Public (No Auth):**
- GET /api/portal/config.php
- GET /api/portal/catalog.php
- GET /api/portal/availability.php
- POST /api/portal/inquiry.php (guest)
- POST /api/portal/register.php
- POST /api/portal/login.php

**Portal Session Required:**
- POST /api/portal/inquiry.php (registered client)
- GET /api/portal/my_inquiries.php

**Admin (AUTH + PORTAL:VIEW):**
- GET /api/portal/admin_inquiries.php
- POST /api/portal/admin_inquiry_update.php

**Admin (AUTH + PORTAL:CONFIGURE):**
- POST /api/portal/admin_config.php
- POST /api/portal/admin_convert.php

---

## Customization

### Change Brand Colors

Update CSS in `layout.twig`:
```css
:root {
    --primary-color: {{ config.primary_color|default('#2563eb') }};
}
```

Or in admin interface via color picker.

### Add Custom Fields to Inquiry Form

Edit `cart.twig`:
```twig
<div class="form-group">
    <label for="customField">Custom Field</label>
    <input type="text" id="customField" name="custom_field">
</div>
```

And update `inquiry.php` API endpoint to handle new field.

### Modify Pricing Logic

Edit `BookingPortalService::checkAvailability()`:
```php
// Adjust pricing formula
$totalPrice = 0;
if ($weeks > 0 && $weekRate > 0) {
    $totalPrice += $weeks * $weekRate * 0.9; // 10% discount for weekly
}
```

### Add Email Notifications

Create email template and hook into `submitInquiry()`:
```php
// Send to admin
$mail->to($CONFIG['ADMIN_EMAIL'])
     ->subject('New Portal Inquiry #' . $inquiryId)
     ->body($twig->render('emails/inquiry_notification.twig', $data))
     ->send();

// Send to customer (if email provided)
if ($data['guest_email']) {
    $mail->to($data['guest_email'])
         ->subject('Booking Inquiry Received')
         ->body($twig->render('emails/inquiry_confirmation.twig', $data))
         ->send();
}
```

---

## File Structure

```
/src/
  /api/
    /portal/
      config.php                    # GET portal config (public)
      catalog.php                   # GET equipment catalog (public)
      availability.php              # GET check availability (public)
      inquiry.php                   # POST submit inquiry (public)
      register.php                  # POST register client (public)
      login.php                      # POST client login (public)
      my_inquiries.php              # GET client inquiries (portal session)
      admin_config.php              # POST save config (admin)
      admin_inquiries.php           # GET all inquiries (admin)
      admin_inquiry_update.php      # POST update inquiry (admin)
      admin_convert.php             # POST convert to project (admin)
  /services/
    BookingPortalService.php        # Core service logic (350+ lines)
  /portal/
    /public/
      index.php                     # Portal entry point
      layout.twig                   # Base template
      catalog.twig                  # Equipment listing
      item.twig                     # Equipment detail
      cart.twig                     # Booking form
      login.twig                    # Login/register
      my_inquiries.twig             # Client history
      thank_you.twig                # Confirmation
    admin.php                       # Admin controller
    portal_admin.twig               # Admin template
/db/
  /migrations/
    20260318230000_booking_portal.php  # Database migration
```

---

## Troubleshooting

### Portal Not Showing
- Check `is_active` is set to 1 in portal_config
- Verify instance_id is correct
- Check browser console for JavaScript errors

### Availability Calculation Wrong
- Verify assetTypes_dayRate and assetTypes_weekRate are set
- Check date format (YYYY-MM-DD)
- Review asset assignments for conflicting dates

### Admin Interface 404
- Check user has PORTAL:VIEW permission
- Verify /src/portal/admin.php path is correct
- Check file exists: `/src/portal/admin.php`

### Session Not Persisting
- Check localStorage is enabled in browser
- Verify token is being set after login
- Check cookie/session expiration (30 days)

### Inquiries Not Showing in Admin
- Verify inquiries are in same instance
- Check instances_id in portal_inquiries table
- Ensure inquiry status is set correctly

---

## Performance Tips

1. **Add Database Indexes** (included in migration)
   - instances_id, client_id, status, project_id

2. **Enable Caching**
   - Cache catalog for 1 hour
   - Cache config for session

3. **Optimize Images**
   - Compress logo image
   - Use WebP format
   - Max size: 100KB

4. **Lazy Load Assets**
   - Load jQuery only when needed
   - Defer JavaScript loading
   - Inline critical CSS

5. **Pagination** (future enhancement)
   - Add LIMIT/OFFSET to catalog queries
   - Paginate inquiry listing in admin

---

## Security Checklist

- [ ] Password hashing with bcrypt
- [ ] Session tokens use random_bytes()
- [ ] Instance isolation enforced
- [ ] Permission checks on admin endpoints
- [ ] Input validation on all endpoints
- [ ] CSRF protection (if using sessions)
- [ ] CORS headers configured
- [ ] No sensitive data in logs
- [ ] Rate limiting considered
- [ ] HTTPS enforced in production

---

## Database Backup

Always backup before migration:
```bash
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql
```

---

## Support & Documentation

- API Reference: See `API_PORTAL_REFERENCE.md`
- Implementation Details: See `PORTAL_IMPLEMENTATION.md`
- Service Docs: See `BookingPortalService.php` comments
