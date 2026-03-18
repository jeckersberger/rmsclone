# Online Booking Portal - API Reference

## Base URL
```
/api/portal/
```

## Public Endpoints (No Authentication Required)

### 1. Get Portal Configuration
```http
POST /api/portal/config.php
Content-Type: application/x-www-form-urlencoded

instances_id=1
```

**Response:**
```json
{
  "result": true,
  "response": {
    "portal_title": "Equipment Rental Portal",
    "portal_description": "Professional rental services",
    "logo_path": "https://example.com/logo.png",
    "primary_color": "#2563eb",
    "show_prices": true,
    "require_registration": true,
    "require_admin_approval": true
  }
}
```

---

### 2. Get Equipment Catalog
```http
POST /api/portal/catalog.php
Content-Type: application/x-www-form-urlencoded

instances_id=1&category_id=5&search=projector
```

**Parameters:**
- `instances_id` (required): Instance ID
- `category_id` (optional): Asset category ID
- `search` (optional): Search term

**Response:**
```json
{
  "result": true,
  "response": {
    "items": [
      {
        "assetTypes_id": 1,
        "assetTypes_name": "Projector 4K",
        "assetTypes_description": "Professional 4K projector",
        "assetTypes_dayRate": "150.00",
        "assetTypes_weekRate": "750.00",
        "assetCategories_id": 5,
        "available_count": 3
      }
    ]
  }
}
```

---

### 3. Check Availability & Pricing
```http
POST /api/portal/availability.php
Content-Type: application/x-www-form-urlencoded

instances_id=1&asset_type_id=1&start_date=2024-04-01&end_date=2024-04-07&quantity=2
```

**Parameters:**
- `instances_id` (required)
- `asset_type_id` (required)
- `start_date` (required): YYYY-MM-DD
- `end_date` (required): YYYY-MM-DD
- `quantity` (required): Number of units

**Response (Available):**
```json
{
  "result": true,
  "response": {
    "available": true,
    "available_count": 2,
    "total_count": 3,
    "rental_days": 7,
    "day_rate": 150.00,
    "week_rate": 750.00,
    "total_price": 1500.00,
    "quantity": 2
  }
}
```

**Response (Unavailable):**
```json
{
  "result": true,
  "response": {
    "available": false,
    "message": "Only 1 available for this period",
    "available_count": 1
  }
}
```

---

### 4. Submit Booking Inquiry (Guest)
```http
POST /api/portal/inquiry.php
Content-Type: application/x-www-form-urlencoded

instances_id=1
guest_name=John Doe
guest_email=john@example.com
guest_phone=+49123456789
guest_company=Acme Corp
rental_start=2024-04-01
rental_end=2024-04-07
items=[{"asset_type_id":1,"quantity":2,"price":150}]
message=Please deliver to venue
```

**Parameters (Guest):**
- `instances_id` (required)
- `guest_name` (required)
- `guest_email` (required)
- `guest_phone` (optional)
- `guest_company` (optional)
- `rental_start` (required): YYYY-MM-DD
- `rental_end` (required): YYYY-MM-DD
- `items` (required): JSON array of items
- `message` (optional)

**Parameters (Registered Client):**
- `instances_id` (required)
- `portal_session` (required): Session token
- Instead of guest_*, uses client from session

**Response:**
```json
{
  "result": true,
  "response": {
    "inquiry_id": 42
  }
}
```

---

### 5. Register Portal Client
```http
POST /api/portal/register.php
Content-Type: application/x-www-form-urlencoded

instances_id=1
name=John Doe
email=john@example.com
password=SecurePassword123
phone=+49123456789
company=Acme Corp
```

**Parameters:**
- `instances_id` (required)
- `name` (required)
- `email` (required): Valid email format
- `password` (required): 8+ characters
- `phone` (optional)
- `company` (optional)

**Response:**
```json
{
  "result": true,
  "response": {
    "client_id": 123,
    "token": "a1b2c3d4e5f6..."
  }
}
```

**Errors:**
- "Invalid email format"
- "Password must be at least 8 characters"
- "Email already registered"

---

### 6. Login Portal Client
```http
POST /api/portal/login.php
Content-Type: application/x-www-form-urlencoded

instances_id=1
email=john@example.com
password=SecurePassword123
```

**Parameters:**
- `instances_id` (required)
- `email` (required)
- `password` (required)

**Response:**
```json
{
  "result": true,
  "response": {
    "client_id": 123,
    "name": "John Doe",
    "email": "john@example.com",
    "token": "a1b2c3d4e5f6..."
  }
}
```

**Error:**
```json
{
  "result": false,
  "error": {
    "message": "Invalid email or password"
  }
}
```

---

### 7. Get Client's Inquiries
```http
POST /api/portal/my_inquiries.php
Content-Type: application/x-www-form-urlencoded

portal_session=a1b2c3d4e5f6...
```

**Parameters:**
- `portal_session` (required): Valid session token

**Response:**
```json
{
  "result": true,
  "response": {
    "inquiries": [
      {
        "id": 42,
        "instances_id": 1,
        "client_id": 123,
        "items": "[{...}]",
        "rental_start": "2024-04-01",
        "rental_end": "2024-04-07",
        "status": "quoted",
        "created_at": "2024-03-18 10:30:00",
        "updated_at": "2024-03-18 15:00:00"
      }
    ]
  }
}
```

---

## Admin Endpoints (Authentication + Permissions Required)

All admin endpoints require:
- Valid session authentication (via HEAD request)
- Instance permission check

### 1. Save Portal Configuration
```http
POST /api/portal/admin_config.php
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer <session_token>

is_active=1
portal_title=My Portal
portal_description=Professional equipment rental
logo_path=https://example.com/logo.png
primary_color=%232563eb
show_prices=1
require_registration=1
require_admin_approval=1
terms_html=<p>Terms here</p>
```

**Parameters:**
- `is_active` (optional): Enable/disable portal
- `portal_title` (optional): Portal title
- `portal_description` (optional): Portal description
- `logo_path` (optional): Logo URL
- `primary_color` (optional): Hex color code
- `show_prices` (optional): Show prices to public
- `require_registration` (optional): Require user registration
- `require_admin_approval` (optional): Require admin approval for bookings
- `terms_html` (optional): Terms and conditions HTML

**Permission Required:** `PORTAL:CONFIGURE`

**Response:**
```json
{
  "result": true,
  "response": {
    "message": "Configuration saved"
  }
}
```

---

### 2. List All Inquiries
```http
POST /api/portal/admin_inquiries.php
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer <session_token>

status=new
```

**Parameters:**
- `status` (optional): Filter by status (new, reviewed, quoted, accepted, rejected, cancelled)

**Permission Required:** `PORTAL:VIEW`

**Response:**
```json
{
  "result": true,
  "response": {
    "inquiries": [
      {
        "id": 42,
        "instances_id": 1,
        "client_id": 123,
        "guest_name": "John Doe",
        "guest_email": "john@example.com",
        "items": "[...]",
        "rental_start": "2024-04-01",
        "rental_end": "2024-04-07",
        "status": "new",
        "message": "Please deliver to venue",
        "created_at": "2024-03-18 10:30:00"
      }
    ]
  }
}
```

---

### 3. Update Inquiry Status
```http
POST /api/portal/admin_inquiry_update.php
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer <session_token>

inquiry_id=42
status=quoted
project_id=99
```

**Parameters:**
- `inquiry_id` (required): Inquiry ID
- `status` (required): new, reviewed, quoted, accepted, rejected, cancelled
- `project_id` (optional): Link to project

**Permission Required:** `PORTAL:VIEW`

**Response:**
```json
{
  "result": true,
  "response": {
    "message": "Inquiry updated"
  }
}
```

---

### 4. Convert Inquiry to Project
```http
POST /api/portal/admin_convert.php
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer <session_token>

inquiry_id=42
```

**Parameters:**
- `inquiry_id` (required): Inquiry ID

**Permission Required:** `PORTAL:CONFIGURE`

**Response:**
```json
{
  "result": true,
  "response": {
    "project_id": 99
  }
}
```

---

## Error Responses

### Standard Error Format
```json
{
  "result": false,
  "error": {
    "code": null,
    "message": "Error description"
  }
}
```

### Common Errors
```
"Portal is not available" - Portal not enabled for instance
"Instance ID required" - Missing or invalid instances_id
"Missing required fields" - Missing required parameters
"Missing required parameters" - GET request missing parameters
"Invalid date format" - Start/end dates not valid
"Start date must be before end date" - Dates in wrong order
"Permission denied" - User lacks required permission
"Session required" - Portal session token required
"Invalid or expired session" - Session token invalid/expired
```

---

## Item JSON Format

Items array structure:
```json
[
  {
    "asset_type_id": 1,
    "asset_type_name": "Projector 4K",
    "quantity": 2,
    "price": 150.00
  }
]
```

---

## Session Token Storage

Frontend should store tokens as:
```javascript
// After registration/login
localStorage.setItem('portalToken', response.token);
localStorage.setItem('clientId', response.client_id);

// When submitting inquiry
const token = localStorage.getItem('portalToken');
```

---

## CORS & Security Headers

All responses include:
```
Access-Control-Allow-Origin: (configured origin)
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
```

---

## Rate Limiting

Currently no rate limiting implemented. Consider adding:
- Login attempts: 5 attempts per 15 minutes
- API calls: 100 requests per minute per IP
- Registration: 1 account per email per day
