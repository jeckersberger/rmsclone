# Online-Buchungsportal (L3) - Implementation Guide

## Overview
Complete implementation of the Online Booking Portal for MyRMS with public-facing equipment catalog, guest booking, and admin management interface.

## Components Implemented

### 1. Database Migration
**File:** `/sessions/vibrant-kind-edison/rmsclone/db/migrations/20260318230000_booking_portal.php`

Creates three tables:
- **portal_config**: Portal settings per instance (branding, colors, pricing visibility)
- **portal_inquiries**: Guest and registered client booking inquiries
- **portal_sessions**: Session tokens for guest/client authentication

### 2. Service Layer
**File:** `/sessions/vibrant-kind-edison/rmsclone/src/services/BookingPortalService.php`

Core service (~350 lines) with methods:
- `getPortalConfig(int $instanceId): ?array` - Get portal configuration
- `savePortalConfig(int $instanceId, array $data): bool` - Save/update config
- `getPublicCatalog(int $instanceId, ?int $categoryId = null, ?string $search = null): array` - Equipment catalog with availability
- `checkAvailability(int $assetTypeId, string $startDate, string $endDate, int $quantity, int $instanceId): array` - Check availability and calculate pricing
- `submitInquiry(array $data, int $instanceId): int` - Submit booking inquiry
- `getInquiries(int $instanceId, ?string $status = null): array` - Get inquiries (admin)
- `getInquiry(int $id): ?array` - Get single inquiry
- `updateInquiryStatus(int $id, string $status, ?int $projectId = null): bool` - Update status
- `convertToProject(int $inquiryId, int $userId, int $instanceId): int` - Convert inquiry to project
- `registerPortalClient(array $data, int $instanceId): int` - Register new client
- `loginPortalClient(string $email, string $password, int $instanceId): ?array` - Client login
- `createPortalSession(?int $clientId = null): string` - Create session token
- `validatePortalSession(string $token): ?array` - Validate session
- `getClientInquiries(int $clientId): array` - Get client's inquiries
- `getCategories(int $instanceId): array` - Get asset categories

### 3. Public API Endpoints (No Auth Required)
**Location:** `/sessions/vibrant-kind-edison/rmsclone/src/api/portal/`

#### Portal Configuration
- **config.php** - GET portal configuration (public data only)
  - Returns: title, colors, branding, pricing visibility, registration requirements

#### Equipment Catalog & Availability
- **catalog.php** - GET public equipment catalog
  - Parameters: instances_id, category_id (optional), search (optional)
  - Returns: Asset types with availability count

- **availability.php** - GET check availability and pricing
  - Parameters: asset_type_id, start_date, end_date, quantity
  - Returns: Available count, rental days, calculated price

#### Booking Management
- **inquiry.php** - POST submit booking inquiry
  - Guest: guest_name, guest_email, guest_phone (optional), guest_company (optional)
  - Registered: portal_session token
  - Returns: inquiry_id

- **register.php** - POST register as portal client
  - Parameters: name, email, password (8+ chars), phone, company
  - Returns: client_id, session token

- **login.php** - POST login as portal client
  - Parameters: email, password
  - Returns: client_id, name, email, session token

- **my_inquiries.php** - GET client's own inquiries
  - Requires: valid portal_session token
  - Returns: List of inquiries for authenticated client

### 4. Admin API Endpoints (Standard Auth Required)
**Location:** `/sessions/vibrant-kind-edison/rmsclone/src/api/portal/`

Require permission: `PORTAL:CONFIGURE` or `PORTAL:VIEW`

- **admin_config.php** - POST save portal configuration (requires PORTAL:CONFIGURE)
  - All configuration fields
  - Returns: success message

- **admin_inquiries.php** - GET list all inquiries (requires PORTAL:VIEW)
  - Optional filter: status
  - Returns: List of all inquiries for instance

- **admin_inquiry_update.php** - POST update inquiry status (requires PORTAL:VIEW)
  - Parameters: inquiry_id, status (new/reviewed/quoted/accepted/rejected/cancelled), project_id (optional)
  - Returns: success message

- **admin_convert.php** - POST convert inquiry to project (requires PORTAL:CONFIGURE)
  - Parameters: inquiry_id
  - Returns: project_id

### 5. Public Portal Templates (No AdminLTE, Standalone CSS)
**Location:** `/sessions/vibrant-kind-edison/rmsclone/src/portal/public/`

Mobile-first, card-based design with custom CSS:

- **layout.twig** - Base layout template
  - Responsive header with logo/branding
  - Navigation (catalog, my inquiries, login/register)
  - Footer with links
  - Customizable colors from portal config
  - Modern card-based styling

- **catalog.twig** - Equipment listing page
  - Search functionality
  - Category filtering
  - Availability indicators
  - Price display (if enabled)
  - "View Details" buttons

- **item.twig** - Single asset type detail
  - Equipment specifications
  - Pricing breakdown (day/week rates)
  - Availability calendar integration
  - Booking form with date/quantity selection
  - Estimated price calculation

- **cart.twig** - Booking inquiry form
  - Cart summary with items
  - Rental dates and total price
  - Guest contact form (if not logged in)
  - Submit inquiry button
  - Session storage for cart persistence

- **login.twig** - Login and registration
  - Login form with email/password
  - Registration form with name, email, password, phone, company
  - Password validation (8+ characters)
  - Session token storage in localStorage

- **my_inquiries.twig** - Client's inquiry history
  - Table view of all inquiries
  - Status badges (color-coded)
  - Rental dates
  - Item counts
  - View details button

- **thank_you.twig** - Post-submission confirmation
  - Success message
  - Inquiry ID display
  - Next steps explanation
  - Continue shopping link

### 6. Public Portal Entry Point
**File:** `/sessions/vibrant-kind-edison/rmsclone/src/portal/public/index.php`

Single-page entry point handling:
- Route handling (catalog, item, cart, login, register, my-inquiries)
- Portal session validation
- Template rendering with Twig
- Error handling

Access:
```
http://example.com/src/portal/public/index.php
http://example.com/src/portal/public/?action=catalog
http://example.com/src/portal/public/?action=item&id=123
http://example.com/src/portal/public/?action=cart
http://example.com/src/portal/public/?action=login
http://example.com/src/portal/public/?action=register
http://example.com/src/portal/public/?action=my-inquiries
```

### 7. Admin Interface
**Controller:** `/sessions/vibrant-kind-edison/rmsclone/src/portal/admin.php`
**Template:** `/sessions/vibrant-kind-edison/rmsclone/src/portal/portal_admin.twig`

Features:
- Portal configuration form
  - Enable/disable portal
  - Set title, description, logo
  - Choose primary color (color picker)
  - Show/hide prices
  - Registration requirements
  - Admin approval toggle
  - Terms & conditions editor

- Quick statistics sidebar
  - Total inquiries count
  - New inquiries
  - Pending review count
  - Status breakdown

- Inquiries management table
  - All inquiries with guest/client info
  - Rental date ranges
  - Status badges (color-coded)
  - Edit button
  - "Convert to Project" button

Permissions:
- `PORTAL:VIEW` - View portal settings and inquiries
- `PORTAL:CONFIGURE` - Configure portal and convert inquiries to projects

## Database Schema

### portal_config
```sql
instances_id (PK, FK)
is_active BOOLEAN
portal_title VARCHAR(255)
portal_description TEXT
logo_path VARCHAR(255) nullable
primary_color VARCHAR(7) - hex color
show_prices BOOLEAN
require_registration BOOLEAN
require_admin_approval BOOLEAN
terms_html LONGTEXT nullable
created_at DATETIME
updated_at DATETIME
```

### portal_inquiries
```sql
id (PK)
instances_id (FK)
client_id (FK, nullable)
guest_name VARCHAR(255, nullable)
guest_email VARCHAR(255, nullable)
guest_phone VARCHAR(20, nullable)
guest_company VARCHAR(255, nullable)
items JSON
rental_start DATE
rental_end DATE
message TEXT nullable
status ENUM - new, reviewed, quoted, accepted, rejected, cancelled
project_id (FK, nullable)
created_at DATETIME
updated_at DATETIME
Indexes: instances_id, client_id, status, project_id
```

### portal_sessions
```sql
id (PK)
token VARCHAR(64) UNIQUE
client_id (FK, nullable)
ip_address VARCHAR(45)
user_agent VARCHAR(500, nullable)
expires_at DATETIME
created_at DATETIME
Indexes: token, client_id, expires_at
```

## Availability Logic

The `checkAvailability()` method:
1. Gets asset type pricing
2. Counts total assets of type
3. Queries for busy assignments in date range
4. Calculates available count
5. Computes rental duration (days)
6. Applies week rates if available (7+ days)
7. Returns availability, availability_count, and total_price

## Pricing Calculation

For a rental from start_date to end_date with quantity:
1. Calculate total rental days (inclusive)
2. If weeks >= 1 and week_rate exists: use week rate
3. Otherwise: use day rate * days
4. Apply quantity multiplier
5. Support for mixed week/day rates

Example: 15-day rental
- 2 weeks × €500/week = €1000
- 1 remaining day × €100/day = €100
- Total: €1100 per unit

## Session Management

Portal sessions stored in `portal_sessions` table:
- Token: 64-character hex string (bin2hex(random_bytes(32)))
- Expiration: 30 days from creation
- Cleanup: Sessions automatically expire (check on validate)
- No IP/User-Agent locking (for flexibility)

## CSS Features

Modern, mobile-first CSS included in layout.twig:
- CSS Variables for theming (--primary-color)
- Responsive grid layouts
- Card-based component design
- Form styling with focus states
- Color-coded status badges
- Smooth transitions and hover effects
- Accessible color contrast
- Loading spinners for async operations

## Front-End Features (JavaScript)

- Client-side availability checking
- Real-time price calculation
- Search and filter in catalog
- Form validation
- Session token storage in localStorage
- Cart persistence via sessionStorage
- Dynamic cart rendering
- Loading states and spinners

## Security Features

1. **Input Validation**: All endpoints validate required fields
2. **Password Hashing**: bcrypt with PASSWORD_BCRYPT
3. **Session Tokens**: Cryptographically secure (random_bytes)
4. **Date Validation**: Check start < end, valid formats
5. **Permission Checks**: Admin endpoints require auth + specific permissions
6. **Instance Isolation**: All queries scoped to current instance
7. **Ownership Verification**: Clients can only see their own inquiries

## Integration Points

### With Existing MyRMS System
1. Uses existing `clients` table for registered portal users
2. References `assetTypes` and `assets` tables for catalog
3. Creates `projects` from inquiries using existing project structure
4. Respects instance isolation (instances_id foreign key)
5. Uses AUTH system for admin permission checks

### Navigation Menu Integration
To add portal to admin menu in AdminLTE:
```php
// Add to navigation menu
[
    'name' => 'Booking Portal',
    'icon' => 'fas fa-store',
    'url' => '/src/portal/admin.php',
    'permission' => 'PORTAL:VIEW'
]
```

## Testing Checklist

- [ ] Migration runs without errors
- [ ] Public catalog displays equipment
- [ ] Availability check calculates correct pricing
- [ ] Guest can submit inquiry
- [ ] Guest can register and login
- [ ] Registered client can view own inquiries
- [ ] Admin can configure portal
- [ ] Admin can view all inquiries
- [ ] Admin can update inquiry status
- [ ] Admin can convert inquiry to project
- [ ] Portal colors update from admin config
- [ ] Prices hidden when show_prices = false
- [ ] Portal accessible at /src/portal/public/index.php
- [ ] Admin interface at /src/portal/admin.php

## Performance Considerations

- Asset availability check uses single SQL query
- Catalog queries indexed on instances_id, assetTypes_deleted
- Sessions auto-cleanup via expiry time check
- JSON fields for item storage (flexible structure)
- No N+1 queries in main paths

## Future Enhancements

1. Email notifications for inquiry submissions
2. Payment gateway integration
3. Inquiry messaging/communication system
4. Automated quote generation from templates
5. Calendar view for availability
6. Multi-language support
7. Custom form fields for specific industries
8. Analytics dashboard for portal usage
9. Image uploads for asset portfolio
10. Custom branding/white-label options
