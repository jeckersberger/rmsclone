# RMS Feature-Übersicht

Vollständige Liste aller Features die seit dem Adam RMS Grundprojekt hinzugefügt wurden.
Stand: Release 1.1 | Branch: `feature/rfid-cases-invoices-android`

---

## 1. RFID/Scanner System

### 1.1 TID-basiertes RFID-Pairing
Statt die überschreibbare EPC-Bank zu nutzen, liest das System die fabrikseitig eingebrannte TID (Tag Identifier, Bank 02) aus. Die TID ist einmalig pro Chip und nicht überschreibbar. Dadurch braucht man kein Writer-Gerät — man scannt den Tag einfach und ordnet die TID in der DB zu.

- DB-Spalten: `assets.assets_rfidTid`, `stock_instances.rfid_tid`, `external_items.rfid_tid`
- EPC-Felder bleiben für Abwärtskompatibilität erhalten
- Migration: `20260316230000_tid_based_rfid_pairing.php`

### 1.2 Tag Format Service
Versteht und parsed alle Tag-Formate:

- Neues Format: `RMS-a3f7b2c1-A-000042`
- QR-Format: `RMS://a3f7b2c1/A/000042`
- Legacy-Formate: `RMS-A-000042`, `RMS-XXXX-A-000042`
- Binary EPC: 96-bit und 128-bit hex
- Raw RFID: Beliebige EPC-Speicherung

Generiert Company Codes (8 hex Chars aus MD5-Hash) pro Instanz mit Collision Detection.

- Service: `src/services/TagFormatService.php`
- DB: `instances.instances_companyCode`, `company_code_history`

### 1.3 Universal Scan Handler
Zentraler Scan-Endpunkt mit 20+ Actions:

- `scan`, `bulk_scan` — Basis-Scans
- `universal_scan`, `universal_lookup` — Erkennt Assets, Stock Instances, External Items und Partner-Geräte automatisch
- `box_scan`, `box_scan_action` — Kisten-Scanning
- `pair_tid`, `unpair_tid`, `lookup_tid` — TID-Management
- `start_inventory`, `inventory_scan`, `complete_inventory` — Inventur-Sessions
- `list_assets` — Asset-Übersicht

API: `/api/rfid/scan.php`
Service: `src/services/RfidService.php` (927 Zeilen)

### 1.4 RFID Tag Management
Tag-Zuordnung, Asset-Suche per Tag, Bulk-Scan, Inventurprüfung, Gateway-Registrierung.

- API: `/api/rfid/manage.php`

### 1.5 Label-Druck System
JSON-basierte Label-Templates mit Elementen (Text, Barcode, Line, Image, QR). Vorkonfigurierte Templates für Assets, Stock Items, External Items. ZPL/Zebra-Drucker-Integration.

- API: `/api/rfid/label.php`
- DB: `label_templates`
- Migration: `20260316140000_external_items.php` (enthält label_templates)

### 1.6 Inventur-Sessions
Start/Stop-Inventur mit Tag-Scanning, fehlende Items erkennen, Session-Statistiken.

- DB: `rfidInventorySessions`, `rfidInventoryScans`

### 1.7 Scan-Historie
Logging aller Scans mit Timestamp, User, Action.

- DB: `assetsBarcodesScans`

---

## 2. Federation (Partner-Netzwerk)

### 2.1 Federation Handshake Protokoll
Server-zu-Server HTTPS REST API. Bidirektionaler Handshake: Server B sendet Partner-Code, Server A verifiziert und generiert beidseitige API-Keys. Status-Tracking: pending → active → rejected/revoked. Company Code Collision Detection beim Handshake.

- API: `/api/federation/handshake.php`
- Auth Middleware: `/api/federation/federationHead.php`
- Service: `src/services/FederationService.php` (600+ Zeilen)
- DB: `partner_servers`
- Migration: `20260306220000_partner_federation.php`

### 2.2 Federation Tag Lookup
Remote-Partner können Tags auf unserem Server suchen. Unterstützt TID, RMS-Format, Raw RFID. Gibt Entity-Details zurück inkl. Assignment-Status und Projekt.

- API: `/api/federation/tag_lookup.php`

### 2.3 Cross-Instance Lookup Service
Kaskadierte Tag-Suche über mehrere Instanzen:

1. Lokale Instanz
2. Lokale Partner (selbe DB via `partner_links`)
3. Federated Remote-Server (separate Server)

Gibt Entity-Details + Status + Assignment zurück.

- Service: `src/services/CrossInstanceLookupService.php` (559 Zeilen)

### 2.4 Equipment-Katalog-Austausch
Equipment-Listen zwischen Partnern teilen.

- API: `/api/federation/equipment.php`

### 2.5 Equipment-Anfragen
Geräte bei Partnern anfragen.

- API: `/api/federation/request.php`

### 2.6 Federation Disconnect
Partnerschaft sauber beenden.

- API: `/api/federation/disconnect.php`

### 2.7 Federation Logging
Audit Trail aller Federation-Kommunikation (Direction, Endpoint, Status, Message).

- DB: `partner_federation_log`

### 2.8 Foreign Loans Tracking
Wenn ein Partner-Gerät gescannt wird (Checkout/Checkin/Locate/Inventory), wird das protokolliert. Für Sub-Rental/Weitervermietung: Partner-Job bleibt aktiv, lokaler Job bekommt foreign_loans-Eintrag.

- DB: `foreign_loans`
- Migration: `20260317120000_foreign_loans_tracking.php`

### 2.9 Federation Ping
Verbindungs-Probe zu Partner-Servern.

- API: `/api/federation/ping.php`

---

## 3. Stock Items (Artikel-Verwaltung)

### 3.1 Zwei-Schicht-Modell
`stock_items` = Artikeltypen (z.B. "HDMI-Kabel 3m"), `stock_instances` = einzelne physische Exemplare mit eigenem RFID-Tag. Jede Instanz hat `instance_number` (laufende Nummer), Status (available/checked_out/damaged/lost/retired), Condition (good/fair/poor).

- Service: `src/services/StockItemService.php` (500+ Zeilen)
- DB: `stock_items`, `stock_instances`
- Migration: `20260316100000_stock_items_and_instances.php`

### 3.2 Stock Item CRUD
18+ Actions: list_items, get_item, create_item, update_item, delete_item, list_instances, create_instances, update_instance, assign_rfid, categories, overview, low_stock.

- API: `/api/stock/items.php`

### 3.3 Preise & SKU
Unit Value, Day Rate, Week Rate pro Artikeltyp. SKU-Tracking.

- DB: `stock_items.unit_value`, `stock_items.day_rate`, `stock_items.week_rate`, `stock_items.sku`

### 3.4 Stock Warnings
Min-Stock-Alerts, Condition-Alerts.

- API: `/api/stock/warnings.php`
- DB: `stock_items.min_stock`

### 3.5 Stock Assignments
Checkout/Checkin-Tracking für einzelne Stock Instances mit Projekt-Zuordnung.

- DB: `stock_assignments` (stock_instance_id, projects_id, assignment_start, assignment_end)

---

## 4. External Items (Fremdmaterial)

### 4.1 External Item Management
Geliehenes/gemietetes Equipment von anderen Firmen verwalten. Owner-Tracking, Return Date, Status (bei_uns/zurueckgegeben/verloren), Barcode/RFID Support.

- Service: `src/services/ExternalItemService.php` (340 Zeilen)
- API: `/api/external/items.php` (9 Actions: list, get, create, update, delete, scan_lookup, mark_returned, mark_lost, stats, print_label)
- DB: `external_items`
- Migration: `20260316140000_external_items.php`

### 4.2 Return Tracking
Wer hat wann empfangen (`received_by`), wer hat wann zurückgegeben (`returned_by`, `returned_at`).

---

## 5. Case/Box Management (Kisten-Verwaltung)

### 5.1 Case Content Definition
Was SOLL in welcher Kiste sein. Type-basiertes Matching: "2x Moving Head" heißt beliebige 2 Moving Heads, nicht spezifische Exemplare. Unterstützt Asset-Typen UND Stock Items gemischt. Sort Order für Reihenfolge.

- Service: `src/services/CaseContentsService.php` (300+ Zeilen)
- DB: `case_contents` (case_asset_id, content_type, content_type_id, quantity, notes, sort_order)
- Migration: `20260316170000_company_code_and_case_contents.php`

### 5.2 Case Content Verification
Bei Checkout und Checkin wird geprüft ob der Soll-Inhalt da ist. Missing Items und Extra Items werden als JSON gespeichert. Acknowledgement-Workflow: Manager muss Abweichungen bestätigen.

- DB: `case_content_checks` (check_type, all_complete, missing_items, extra_items, acknowledged, acknowledged_by)

### 5.3 Asset als Kiste markieren
- DB: `assets.is_case` Flag

### 5.4 Cases API
7 Actions: list_cases, get_contents, add_content, remove_content, update_content, verify_contents, get_check_status.

- API: `/api/cases/contents.php`

### 5.5 Box Scan Sessions
Bulk-RFID-Scanning für Kisten mit Scan-Modi (count/checkout/checkin).

- DB: `box_scan_sessions` (scan_mode, total_scanned, total_assets, total_stock, total_unknown, scan_data JSON)

---

## 6. Packlisten

### 6.1 Packlisten-Generierung
Automatische Generierung aus Projekt-Zuweisungen. Multi-Entity: Assets + Stock Instances + External Items auf einer Liste.

- Service: `src/services/PackingListService.php` (200+ Zeilen)
- DB: `packing_lists` (list_number, title, projects_id, status, total_items, total_weight)
- DB: `packing_list_items` (assets_id, assetsAssignments_id, item_name, quantity, weight, sort_order, packed, packed_by, packed_at)

### 6.2 Scan-basiertes Abhaken
Item scannen → System erkennt ob Asset/Stock/External → automatisch als gepackt markieren.

- DB: `packing_checks`
- Migration: `20260316150000_packing_checks.php`

### 6.3 Packlisten-API
- `/api/packing/list.php` — Unified API (get_list, check_item, uncheck_item, scan_check, get_progress)
- `/api/packingLists/generate.php` — Packliste generieren
- `/api/packingLists/get.php` — Packliste laden
- `/api/packingLists/togglePacked.php` — Item als gepackt markieren
- `/api/packingLists/updateStatus.php` — Status ändern

### 6.4 Gewicht-Tracking
Kumulatives Gewicht pro Packliste und pro Item.

### 6.5 PDF-Export
Packlisten als PDF zum Drucken.

---

## 7. Android App (Chafon CF-H906)

### 7.1 App-Architektur
Kotlin, Jetpack Compose, Retrofit/OkHttp, Room (lokale SQLite), Coroutines. 45 Kotlin-Dateien, 12 UI-Screens.

- Verzeichnis: `android-app/`

### 7.2 RFID Manager
Interface für Chafon CF-H906 UHF RFID SDK. TID Bank 02 Reading, Power Management (5-30 dBm), Serial Port Communication (/dev/ttyS4). MockRfidManager für Testing.

- `ChafonRfidManager.kt` — Stubs mit detaillierten TODOs für SDK-Integration
- `RfidManager.kt` — Interface
- `MockRfidManager.kt` — Test-Dummy
- `RfidEvent.kt` — Event Model (TagRead, Error, Connected, Disconnected)

### 7.3 Hardware-Trigger System
Physische Scan-Buttons (F1-F5, Chafon Pistolgriff-Trigger Keycodes 280-282) lösen Scan aus.

- `ScanTriggerManager.kt` — Singleton mit SharedFlow für Trigger-Events

### 7.4 2D Barcode Receiver
BroadcastReceiver in MainActivity für 4 verschiedene Broadcast-Actions (com.scanner.broadcast, android.intent.ACTION_DECODE_DATA, etc.). Empfängt Barcode-Wert vom 2D-Scanner des CF-H906.

**Status:** Empfängt den Wert, leitet aber aktuell nur den Trigger weiter — der Barcode-Wert selbst geht verloren. Fix für Dual-Scanning steht noch aus.

### 7.5 UI-Screens

| Screen | Funktion |
|--------|----------|
| **LoginScreen** | Server-Adresse + Credentials |
| **MainMenuScreen** | Navigation Hub |
| **CheckoutScreen** | Projekt wählen → RFID scannen → Checkout |
| **CheckinScreen** | Offene Items anzeigen, Damage Reporting |
| **BoxScanScreen** | Bulk-RFID-Scan (Count/Checkout/Checkin), Fremdgeräte-Sektion |
| **InventoryScreen** | Inventur-Session Start/Stop, Missing Items Detection |
| **PackingListScreen** | Projekt wählen, Items scannen, Progress-Tracking |
| **LocationScreen** | Assets/Stock nach Ort filtern & scannen |
| **ExternalItemScreen** | Fremdmaterial-Verwaltung |
| **CaseVerifyScreen** | Kisten-Soll/Ist-Vergleich |
| **TagPairScreen** | TID zuordnen (Scan → Assign zu Entity) |
| **SettingsScreen** | Server-URL, Offline Mode, Cache Management |

### 7.6 API Layer
- `RmsApiService.kt` — Alle Endpoints definiert (universal_scan, box_scan, inventory, packing, etc.)
- `RmsRepository.kt` — Data Access Layer mit Result<T> Wrapping
- Models: `ApiResponse.kt`, `PackingModels.kt`, `LocationModels.kt`, `CaseModels.kt`, `ExternalItemModels.kt`

### 7.7 Partner-Geräte Anzeige
- `ScanResultCard.kt` — Oranger "Gehört: [Firma]" Badge für Fremdgeräte mit Entity-Details
- `BoxScanScreen.kt` — "Fremdgeräte" als eigene Sektion in orange

### 7.8 Auto-Update
GitHub Releases API → SemVer-Vergleich → DownloadManager → FileProvider → Install Intent.

---

## 8. Web-Interface Erweiterungen

### 8.1 RFID Scanner Web Interface
Mode Selector (Checkout/Checkin/Inventory/Locate/Box Scan), RFID Input Field mit Fokus-basierter Eingabe, Project Selector, farbiges Feedback (grün/rot/gelb).

- Template: `src/rfid/scanner.twig`

### 8.2 Stock Management Interface
Item Type CRUD, Instance Bulk Creation, Category Management, Stock Level Warnings, RFID Assignment UI, Box Scan Interface.

- Template: `src/stock/manage.twig`

### 8.3 Case/Container Management UI
Case Definition Editor, Content Type Selection, Quantity & Notes, Drag-Drop Reordering, Checklisten für Checkout/Checkin.

### 8.4 Packing List UI
Automatische Generierung aus Projekt, Item-by-Item Checkbox, RFID Scan Support, Progress Bar, PDF Export, Weight Tracking.

- Template: `src/project/project_packinglist.twig`

### 8.5 AdminLTE Dark-Pink Design
Farbschema: #343a40 Dark Background, #e83e8c Pink Accent, #007bff Primary.

---

## 9. Datenbank-Erweiterungen

### 9.1 Neue Tabellen (RFID/Stock/Federation)

| Tabelle | Zweck | Migration |
|---------|-------|-----------|
| `stock_items` | Artikeltypen | 20260316100000 |
| `stock_instances` | Einzelne Exemplare mit RFID | 20260316100000 |
| `stock_assignments` | Ausleihe-Tracking Stock | 20260316100000 |
| `box_scan_sessions` | Kisten-Scan Sessions | 20260316100000 |
| `external_items` | Fremdmaterial | 20260316140000 |
| `label_templates` | Label-Designs (JSON) | 20260316140000 |
| `locations` | Lagerort-Verwaltung | 20260316120000 |
| `case_contents` | Kisten-Soll-Inhalt | 20260316170000 |
| `case_content_checks` | Kisten-Verification | 20260316170000 |
| `company_code_history` | Company Code Audit | 20260316170000 |
| `packing_lists` | Packlisten-Header | (in stock migration) |
| `packing_list_items` | Packlisten-Items | (in stock migration) |
| `packing_checks` | Abhaken-Tracking | 20260316150000 |
| `partner_servers` | Verbundene Partner | 20260306220000 |
| `partner_federation_log` | Federation-Log | 20260306220000 |
| `foreign_loans` | Fremdgeräte-Tracking | 20260317120000 |
| `rfidInventorySessions` | Inventur-Sessions | (in rfid migration) |
| `rfidInventoryScans` | Inventur-Scans | (in rfid migration) |

### 9.2 Neue Spalten auf bestehenden Tabellen

| Tabelle.Spalte | Zweck |
|----------------|-------|
| `assets.assets_rfidTid` | TID für Assets |
| `assets.is_case` | Asset ist Transportkiste |
| `instances.instances_companyCode` | 8-hex Company ID |

### 9.3 Indizes

```sql
UNIQUE INDEX idx_assets_rfidTid ON assets(assets_rfidTid)
UNIQUE INDEX idx_stock_rfid_tid ON stock_instances(rfid_tid)
UNIQUE INDEX idx_external_rfid_tid ON external_items(rfid_tid)
UNIQUE INDEX idx_company_codes ON instances(instances_companyCode)
UNIQUE INDEX idx_partner_api_key ON partner_servers(partner_servers_apiKey)
INDEX idx_partner_instances ON partner_servers(instances_id)
INDEX idx_box_sessions ON box_scan_sessions(instances_id, session_started)
INDEX idx_packing_checks ON packing_checks(entity_type, entity_id, project_id)
```

---

## 10. Infrastruktur

### 10.1 Docker
Docker-Setup für PHP 8.3 + MySQL 8.0 + Nginx.

### 10.2 Hardware-Integration
Chafon CF-H906 UHF RFID PDA: Android 9.0 (API 28), UHF 865-868MHz bis 20m Reichweite, 2D Barcode Scanner, 5.7" 720x1440, IP65, 5800mAh.

### 10.3 MeekroDB
Database Library. Konventionen: `getOne('table', null, ['columns'])`, keine komplexen OR-Placeholder — stattdessen sequenzielle Queries.

---

## 11. Offene Features / TODOs

### 11.1 Chafon SDK Integration
ChafonRfidManager.kt enthält Stubs mit detaillierten TODOs. SDK muss von Chafon heruntergeladen und als .aar eingebunden werden.

### 11.2 QR+RFID Dual-Scanning
BroadcastReceiver empfängt Barcode-Wert, aber leitet nur den Trigger weiter. Der Barcode-Wert muss durch die gleiche Scan-Pipeline wie RFID-Tags geroutet werden.

### 11.3 Verbrauchsgegenstände (Consumables)
Dritte Entity-Kategorie neben Gerät und Artikel. Menge raus auf Job, Menge zurück, Differenz = verbraucht. Noch nicht implementiert.

### 11.4 Standort-Tracking (Location Assignment)
Scan → Lagerort zuweisen (Trailer, Auto, Lager). `locations` Tabelle existiert bereits, aber Assignment-Flow fehlt noch.

### 11.5 Federation Equipment Auto-Sharing
Partner-Geräte sollen automatisch über die Federation-Verbindung sichtbar/geteilt werden, statt manuell als External Items angelegt.

### 11.6 Offline Mode (Android)
Teilweise implementiert (AppPreferences vorhanden). Sync-Mechanismus, Conflict Resolution und Offline Queue fehlen noch.

### 11.7 Federation Freundescode Pairing Flow
Konkreter User-Flow für das Verbinden zweier Instanzen per Freundescode muss noch designed werden.
