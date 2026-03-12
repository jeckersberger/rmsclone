# AdamRMS - API-Dokumentation

## Authentifizierung
Alle geschuetzten Endpoints erfordern eine gueltige Session (Cookie-basiert).
CSRF-Token muss als `X-CSRF-TOKEN` Header mitgesendet werden.

## Endpunkt-Uebersicht

### Assets

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/assets/searchType.php` | Asset-Typen suchen |
| `POST /api/assets/list.php` | Assets auflisten |
| `POST /api/assets/depreciation/calculate.php` | AfA berechnen |
| `POST /api/assets/lifecycle/manage.php` | Lifecycle-Status verwalten |
| `POST /api/assets/conflicts/check.php` | Konflikte pruefen |

### Projekte

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/projects/clone.php` | Projekt klonen |
| `POST /api/projects/checklist/manage.php` | Checklisten verwalten |
| `POST /api/projects/comments/manage.php` | Kommentare verwalten |
| `POST /api/projects/photos/manage.php` | Fotos verwalten |

### Dokumente & Rechnungen

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/documentLifecycle/preview.php` | PDF-Vorschau |
| `POST /api/payments/record.php` | Zahlung erfassen |
| `POST /api/dunning/generateLetter.php` | Mahnbrief generieren |

### Kunden

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/clients/contacts.php` | Ansprechpartner |
| `POST /api/clients/categories.php` | Kategorien |
| `POST /api/clients/creditCheck.php` | Kreditlimit pruefen |

### Partner

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/partner/invite.php` | Partner einladen |
| `POST /api/partner/accept.php` | Einladung annehmen |
| `POST /api/partner/equipment.php` | Equipment durchsuchen |
| `POST /api/partner/billing/manage.php` | Abrechnung verwalten |

### Benachrichtigungen & Kommunikation

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/inAppNotifications/list.php` | In-App Benachrichtigungen |
| `POST /api/emailTemplates/manage.php` | E-Mail-Vorlagen verwalten |
| `POST /api/webhooks/manage.php` | Webhooks verwalten |

### Integrationen

| Endpoint | Beschreibung |
|----------|-------------|
| `POST /api/calendar/feed.php` | Kalender-Feed (ICS) |
| `POST /api/rfid/manage.php` | RFID-Verwaltung |
| `POST /api/ai/assetLookup.php` | KI-Asset-Suche |
| `POST /api/portal/access.php` | Kunden-Portal |
| `POST /api/discounts/manage.php` | Rabattcodes |

### System

| Endpoint | Beschreibung |
|----------|-------------|
| `GET /api/health.php` | Health-Check (kein Auth) |
| `GET /api/pwa/manifest.php` | PWA Manifest |
| `POST /api/dashboard/overview.php` | Dashboard-Daten |

---

## Detaillierte Endpoint-Beschreibungen

### AfA-Berechnung
`POST /api/assets/depreciation/calculate.php`

**Parameter:**
- `action`: `calculate` oder `useful_life_table`
- `acquisition_cost`: Anschaffungskosten (EUR)
- `useful_life_years`: Nutzungsdauer (Jahre)
- `acquisition_date`: Anschaffungsdatum (YYYY-MM-DD)
- `method`: `linear` oder `degressive`

**Antwort:**
```json
{
  "result": true,
  "response": {
    "method": "linear",
    "acquisition_cost": 10000,
    "yearly_amount": 1428.57,
    "schedule": [
      {"year": 2026, "depreciation": 1190.48, "book_value": 8809.52}
    ]
  }
}
```

### Webhook registrieren
`POST /api/webhooks/manage.php`

**Parameter:**
- `action`: `register`
- `url`: Webhook-URL (HTTPS)
- `events`: JSON-Array der Events (z.B. `["project.created", "invoice.paid"]`)
- `name`: Optionaler Name

**Events:** `project.created`, `project.updated`, `project.deleted`, `invoice.created`, `invoice.paid`, `asset.checked_out`, `asset.returned`, `client.created`, `document.created`, `quote.accepted`

**Webhook-Payload:**
```json
{
  "event": "invoice.created",
  "timestamp": "2026-03-08T10:00:00+01:00",
  "data": {}
}
```
Signatur: `X-Webhook-Signature: sha256=<HMAC-SHA256>`

### Rabattcode validieren
`POST /api/discounts/manage.php`

**Parameter:**
- `action`: `validate`
- `code`: Rabattcode
- `order_value`: Bestellwert (optional)
- `asset_type_id`: Asset-Typ-ID (optional)

### RFID Bulk-Scan
`POST /api/rfid/manage.php`

**Parameter:**
- `action`: `bulk_scan`
- `tags`: JSON-Array der gescannten EPC-Tags
- `gateway_id`: Gateway-Kennung

### KI-Asset-Suche
`POST /api/ai/assetLookup.php`

**Parameter:**
- `product_name`: Produktname (min. 2 Zeichen)
- `manufacturer`: Hersteller (optional)

**Rate-Limit:** 20 Anfragen pro Stunde pro Benutzer.

### Kunden-Portal
`POST /api/portal/access.php`

Token-basierter Zugang fuer Kunden (kein Login erforderlich):
- `portal_token`: Zugangstoken
- `action`: `projects`, `invoices`, `quotes`, `accept_quote`, `feedback`, `update_contact`
