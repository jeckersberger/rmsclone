# RFID-Lagersystem Konzept

## Uebersicht

Integration eines RFID-Systems in das bestehende RMS-Lagerverwaltungssystem.
Ziel: Schnellere und fehlerfreie Ein-/Ausbuchung von Equipment ueber stationaere
RFID-Scanner und RFID-Labels statt manueller Barcode-Scans.

---

## 1. Hardware

### 1.1 RFID-Tags (Labels)

| Eigenschaft        | Empfehlung                                              |
|--------------------|----------------------------------------------------------|
| **Standard**       | UHF RFID (EPC Gen2 / ISO 18000-63)                      |
| **Frequenz**       | 865-868 MHz (EU-Band)                                    |
| **Formfaktor**     | Klebeetiketten 50x25mm (passend zu bestehenden Zebra-Labels) |
| **Material**       | On-Metal-Tags fuer Metallequipment (Traversen, Scheinwerfer), Standard-Inlays fuer Cases |
| **Speicher**       | 96-bit EPC (reicht fuer Asset-ID + Instance-ID Kodierung) |
| **Reichweite**     | 2-8m je nach Leser und Umgebung                          |

**Kodierung des EPC:**
```
EPC (96 bit) = Header(8) + Instance-ID(24) + Asset-ID(32) + Pruefsumme(16) + Reserve(16)
```
Alternativ einfacher: Die Asset-URL als User-Memory schreiben (`{baseUrl}/asset/?id={assets_id}`),
damit die Tags sowohl per RFID als auch per NFC/QR lesbar bleiben.

### 1.2 RFID-Drucker

| Eigenschaft        | Empfehlung                                              |
|--------------------|----------------------------------------------------------|
| **Drucker**        | Zebra ZD621R oder ZD421R (RFID-faehig)                   |
| **Kompatibilitaet**| ZPL-kompatibel (bestehender ZPL-Code wiederverwendbar)   |
| **RFID-Encoding**  | Inline-Encoding beim Drucken (^RFW ZPL-Befehl)          |
| **Labels**         | Zebra RFID-Inlay-Labels (z.B. Zebra Z-Perform 1500T mit Impinj Monza R6) |

**ZPL-Erweiterung fuer RFID-Encoding:**
```zpl
^XA
^CI28
^PW408
^LL203
^RS8           ; RFID-Setup
^RFW,H,1,2,48 ; Schreibe EPC: 48 hex chars (24 bytes)
^FD{epcHex}^FS
^FO10,10^BQN,2,4^FDMA,{qrData}^FS    ; QR-Code (Fallback)
^FO140,10^A0N,22,22^FD{assetTag}^FS   ; Sichtbarer Text
^FO140,38^A0N,16,16^FD{typeName}^FS
^FO10,160^BY1,2,30^BCN,30,N,N^FD{barcode}^FS  ; Barcode (Fallback)
^XZ
```

### 1.3 Stationaerer RFID-Scanner

| Eigenschaft        | Empfehlung                                              |
|--------------------|----------------------------------------------------------|
| **Leser**          | Zebra FX7500 (stationaer) oder FX9600 (4-8 Antennen)    |
| **Alternativ**     | Impinj Speedway R420 oder guenstiger: Nordic ID EXA51e   |
| **Antennen**       | 2-4 Kreis-Polarisations-Antennen am Lagertor/Ausgabe     |
| **Anbindung**      | Ethernet (LLRP-Protokoll) oder USB HID                   |
| **Budget-Option**  | USB-Desktop-Leser (z.B. Zebra RFD40) fuer einzelne Scans |

### 1.4 Mobiler RFID-Scanner (optional)

| Eigenschaft        | Empfehlung                                              |
|--------------------|----------------------------------------------------------|
| **Geraet**         | Zebra MC3330xR oder guenstiger: Chainway C72             |
| **Alternativ**     | Zebra RFD40 Sled fuer vorhandene Smartphones             |
| **Einsatz**        | Inventur, Lagersuche, mobiles Ein-/Ausbuchen             |

---

## 2. Software-Architektur

### 2.1 Neue Dateien

```
src/
  services/
    RfidService.php           -- Kernlogik: Tag-Verwaltung, Bulk-Scan-Verarbeitung
    RfidGatewayService.php    -- LLRP-Protokoll-Anbindung zum stationaeren Leser
  api/
    rfid/
      scan.php                -- Eingehende Scans vom Gateway verarbeiten
      bulkScan.php            -- Mehrere Tags gleichzeitig (Tor-Scanner)
      register.php            -- Neuen RFID-Tag mit Asset verknuepfen
      unregister.php          -- RFID-Tag von Asset loesen
      status.php              -- Gateway-Status abfragen
      inventory.php           -- Inventur starten/abschliessen
  rfid/
    gateway.php               -- Admin-Seite: Gateway-Konfiguration
    gateway.twig              -- UI fuer Gateway-Status und Live-Scan-Anzeige
    station.php               -- Stationaerer Scanner: Vollbild Ein-/Ausbuch-Terminal
    station.twig              -- Terminal-UI (optimiert fuer Touchscreen am Tor)
    inventory.php             -- Inventur-Seite
    inventory.twig            -- Inventur-UI
db/
  migrations/
    YYYYMMDD_rfid_tables.php  -- Neue DB-Tabellen
```

### 2.2 Datenbank-Erweiterungen

```sql
-- RFID-Tags (erweitert assetsBarcodes)
CREATE TABLE rfid_tags (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    instances_id    INT NOT NULL,
    assets_id       INT NULL,           -- NULL = unzugeordnet
    epc             VARCHAR(64) NOT NULL UNIQUE,  -- EPC-Hex
    tid             VARCHAR(64) NULL,    -- Tag-Identifier (Chip-Seriennr)
    tag_type        ENUM('uhf','hf','nfc') DEFAULT 'uhf',
    label_printed   TINYINT DEFAULT 0,
    label_printed_at DATETIME NULL,
    assigned_at     DATETIME NULL,
    last_seen_at    DATETIME NULL,
    last_seen_location VARCHAR(100) NULL,
    status          ENUM('active','lost','damaged','retired') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(instances_id),
    INDEX(assets_id),
    INDEX(epc)
);

-- RFID-Scan-Log (hochfrequent, ggf. partitionieren)
CREATE TABLE rfid_scans (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    instances_id    INT NOT NULL,
    rfid_tags_id    INT NULL,
    epc             VARCHAR(64) NOT NULL,
    rssi            SMALLINT NULL,      -- Signalstaerke
    antenna         TINYINT NULL,       -- Antennen-Nr
    gateway_id      VARCHAR(50) NULL,   -- Welcher Leser
    scan_type       ENUM('gate_in','gate_out','inventory','manual','unknown') DEFAULT 'unknown',
    direction       ENUM('in','out','unknown') DEFAULT 'unknown',
    processed       TINYINT DEFAULT 0,  -- Wurde der Scan verarbeitet?
    projects_id     INT NULL,           -- Zugeordnetes Projekt
    users_userid    INT NULL,           -- Wer war anwesend
    scanned_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(instances_id, scanned_at),
    INDEX(epc),
    INDEX(processed)
);

-- RFID-Gateways (stationaere Leser)
CREATE TABLE rfid_gateways (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    instances_id    INT NOT NULL,
    name            VARCHAR(100) NOT NULL,  -- z.B. "Lagertor 1"
    location        VARCHAR(200) NULL,
    ip_address      VARCHAR(45) NULL,
    port            INT DEFAULT 5084,       -- LLRP Standard-Port
    gateway_type    ENUM('llrp','usb','webhook') DEFAULT 'llrp',
    mode            ENUM('gate','station','inventory') DEFAULT 'gate',
    status          ENUM('online','offline','error') DEFAULT 'offline',
    last_heartbeat  DATETIME NULL,
    config_json     TEXT NULL,              -- Zusaetzliche Konfiguration
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(instances_id)
);

-- Inventur-Sessions
CREATE TABLE rfid_inventories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    instances_id    INT NOT NULL,
    title           VARCHAR(200) NOT NULL,
    status          ENUM('running','completed','cancelled') DEFAULT 'running',
    started_by      INT NOT NULL,
    started_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME NULL,
    total_expected  INT DEFAULT 0,
    total_found     INT DEFAULT 0,
    total_missing   INT DEFAULT 0,
    total_unexpected INT DEFAULT 0,
    notes           TEXT NULL,
    INDEX(instances_id)
);

-- Inventur-Ergebnisse
CREATE TABLE rfid_inventory_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    rfid_inventories_id INT NOT NULL,
    assets_id       INT NULL,
    rfid_tags_id    INT NULL,
    epc             VARCHAR(64) NULL,
    status          ENUM('found','missing','unexpected','no_tag') DEFAULT 'missing',
    scanned_at      DATETIME NULL,
    INDEX(rfid_inventories_id)
);
```

### 2.3 Bestehende Tabellen erweitern

```sql
-- assets: RFID-Referenz hinzufuegen
ALTER TABLE assets ADD COLUMN rfid_tags_id INT NULL AFTER assets_tag;
ALTER TABLE assets ADD COLUMN rfid_epc VARCHAR(64) NULL AFTER rfid_tags_id;

-- assetsBarcodes: RFID als Barcode-Typ unterstuetzen
-- (Bestehend: CODE_128, QR) -> Neuer Typ: RFID_UHF
-- Kein Schema-Change noetig, einfach neuen Wert verwenden

-- asset_checkinout: RFID-Scan-Referenz
ALTER TABLE asset_checkinout ADD COLUMN rfid_scans_id BIGINT NULL;
ALTER TABLE asset_checkinout ADD COLUMN scan_method ENUM('manual','barcode','rfid','bulk_rfid') DEFAULT 'manual';
```

---

## 3. Ablauf / Workflows

### 3.1 Label-Erstellung (RFID + QR + Barcode)

```
1. Admin waehlt Assets in der Asset-Liste aus
2. Klick auf "RFID-Labels drucken"
3. System generiert fuer jedes Asset:
   a) EPC aus Instance-ID + Asset-ID
   b) ZPL mit ^RFW (RFID-Write) + QR-Code + Barcode + Text
4. ZPL wird an Zebra RFID-Drucker gesendet
5. Drucker schreibt EPC auf RFID-Inlay UND druckt Label
6. System speichert rfid_tags Eintrag mit EPC -> assets_id Zuordnung
7. Asset-Datensatz wird mit rfid_epc aktualisiert
```

### 3.2 Ausbuchen ueber Tor-Scanner (Gate-Out)

```
1. Mitarbeiter waehlt am Terminal (station.twig) das Projekt aus
2. Equipment wird durch das Tor geschoben/getragen
3. RFID-Leser erfasst alle Tags im Durchgangsbereich
4. Gateway sendet Scans per Webhook an /api/rfid/bulkScan.php
5. System ordnet jeden EPC einem Asset zu
6. Fuer jedes erkannte Asset:
   a) Automatischer Check-Out via CheckInOutService
   b) Status-Update in assetsAssignments
   c) Eintrag in rfid_scans mit direction='out'
7. Terminal zeigt:
   - Gruene Liste: Erkannte Assets (zum Projekt gehoerend)
   - Gelbe Liste: Assets eines anderen Projekts (Warnung)
   - Rote Liste:  Unbekannte Tags
   - Fehlende:    Erwartete aber nicht gescannte Assets
8. Mitarbeiter bestaetigt oder korrigiert
```

### 3.3 Einbuchen ueber Tor-Scanner (Gate-In)

```
1. Equipment kommt zurueck, wird durch Tor geschoben
2. RFID-Leser erfasst alle Tags
3. System findet offene Check-Outs fuer jedes Asset
4. Automatischer Check-In via CheckInOutService
5. Terminal fragt Zustand ab (optional per Asset oder pauschal)
6. Beschaedigte Items werden markiert
```

### 3.4 Inventur

```
1. Admin startet Inventur-Session auf /rfid/inventory.php
2. System erstellt Liste aller aktiven Assets mit RFID-Tags
3. Mitarbeiter geht mit mobilem Scanner durchs Lager
   ODER laesst stationaeren Scanner im Inventur-Modus laufen
4. Gescannte EPCs werden als "found" markiert
5. Nach Abschluss zeigt System:
   - Gefunden: X von Y Assets
   - Fehlend:  Liste mit Standort-Vermutung (letzter Scan)
   - Unerwartet: Tags ohne Asset-Zuordnung
6. Export als CSV/PDF moeglich
```

### 3.5 Asset-Suche per RFID

```
1. In der Mobile App: "Asset suchen" -> Asset auswaehlen
2. App zeigt EPC und letzten bekannten Standort
3. Mit mobilem RFID-Scanner: Geiger-Modus (RSSI-basiert)
   -> Je naeher am Asset, desto staerker das Signal
4. Akustisches Feedback zur Lokalisierung
```

---

## 4. Implementierungsplan

### Phase A: Grundlagen (Datenbank + Service)

**Aufwand: ~2-3 Tage Entwicklung**

1. Migration: `rfid_tags`, `rfid_scans`, `rfid_gateways` Tabellen anlegen
2. Migration: `assets` Tabelle erweitern (rfid_tags_id, rfid_epc)
3. Migration: `asset_checkinout` erweitern (rfid_scans_id, scan_method)
4. `RfidService.php` erstellen:
   - `registerTag(instanceId, assetId, epc)` - Tag mit Asset verknuepfen
   - `unregisterTag(instanceId, tagId)` - Tag loesen
   - `findByEpc(epc)` - Asset anhand EPC finden
   - `processScan(instanceId, epc, gatewayId, direction)` - Einzelscan verarbeiten
   - `processBulkScan(instanceId, epcs[], gatewayId, projectId, direction)` - Bulk-Scan
   - `generateEpc(instanceId, assetId)` - EPC generieren
5. API-Endpunkte: `rfid/register.php`, `rfid/scan.php`, `rfid/bulkScan.php`

### Phase B: Label-Druck mit RFID-Encoding

**Aufwand: ~1 Tag Entwicklung**

1. `labels.php` erweitern: RFID-ZPL-Modus mit `^RFW` Befehlen
2. Beim Drucken automatisch `rfid_tags` Eintrag anlegen
3. UI-Button "RFID-Labels drucken" in Asset-Liste und Mobile App

### Phase C: Stationaerer Scanner (Gate)

**Aufwand: ~3-4 Tage Entwicklung**

1. `RfidGatewayService.php`: LLRP-Protokoll oder Webhook-Empfang
2. Gateway-Admin-Seite (`rfid/gateway.twig`): IP, Port, Modus konfigurieren
3. Terminal-Seite (`rfid/station.twig`):
   - Vollbild-Touchscreen-UI
   - Projekt-Auswahl (Dropdown oder Barcode-Scan)
   - Live-Scan-Anzeige mit Ampel-Farben
   - Bestaetigung durch Mitarbeiter
4. Webhook-Endpunkt fuer eingehende LLRP-Daten
5. Integration mit CheckInOutService (automatisch Check-In/Check-Out)

### Phase D: Inventur

**Aufwand: ~2 Tage Entwicklung**

1. Inventur-Tabellen (rfid_inventories, rfid_inventory_items)
2. Inventur-UI: Start, Live-Fortschritt, Abschluss
3. Ergebnis-Export (CSV mit fehlenden Assets)
4. Integration mit mobilem Scanner

### Phase E: Mobile App Integration

**Aufwand: ~1 Tag Entwicklung**

1. RFID-Scan-Seite in Mobile App (fuer Bluetooth-RFID-Sleds)
2. Asset-Suche per EPC
3. Inventur-Modus in Mobile App

---

## 5. Gateway-Anbindung (Technisch)

### Option A: LLRP-Protokoll (Standard)

Viele stationaere RFID-Leser unterstuetzen LLRP (Low Level Reader Protocol).
Ein PHP-Daemon oder Node.js-Service verbindet sich per TCP zum Leser
und empfaengt Tag-Reports.

```
[RFID-Leser] --LLRP/TCP:5084--> [rfid-daemon] --HTTP POST--> [/api/rfid/bulkScan.php]
```

**Daemon (rfid-gateway-daemon.php oder Node.js):**
- Verbindet sich zum LLRP-Reader
- Sammelt Tags ueber konfigurierbares Zeitfenster (z.B. 5 Sekunden)
- Sendet Batch per HTTP POST an die RMS-API
- Heartbeat an `/api/rfid/status.php`

### Option B: Webhook / HTTP Push

Manche Leser (z.B. Zebra FX-Serie mit SmartLens) koennen direkt
HTTP-Webhooks senden. In dem Fall entfaellt der Daemon.

```
[RFID-Leser] --HTTP POST--> [/api/rfid/bulkScan.php]
```

### Option C: USB HID (Budget)

Einfache USB-Desktop-Leser senden den EPC als Tastatureingabe.
Die Terminal-Seite (station.twig) empfaengt die Eingabe per JavaScript
`keydown`-Event und sendet sie per AJAX an die API.

```
[USB-Leser] --Tastatur--> [Browser/station.twig] --AJAX--> [/api/rfid/scan.php]
```

---

## 6. Kosten-Schaetzung Hardware

| Komponente                          | ca. Preis     |
|-------------------------------------|---------------|
| Zebra ZD621R RFID-Drucker           | 1.500-2.000 EUR |
| RFID-Labels (1000 Stueck)           | 80-150 EUR    |
| On-Metal RFID-Tags (100 Stueck)     | 100-200 EUR   |
| Stationaerer Leser (Zebra FX7500)   | 1.500-2.500 EUR |
| 2x UHF-Antenne                      | 200-400 EUR   |
| Budget USB-Desktop-Leser            | 150-300 EUR   |
| Touchscreen-Terminal fuer Tor       | 300-500 EUR   |
| **Gesamt (Basisausstattung)**       | **~3.000-5.000 EUR** |

**Budget-Variante (USB-Leser statt Gate):** ~2.000 EUR

---

## 7. Integration mit bestehendem System

### 7.1 Barcode-Search API erweitern

Die bestehende `/api/assets/barcodes/search.php` wird erweitert:

```php
// Neuer Typ: RFID_UHF
if ($_POST['type'] === 'RFID_UHF' || $_POST['type'] === 'RFID') {
    $DBLIB->where('epc', $_POST['text']);
    $DBLIB->where('instances_id', $instanceId);
    $rfidTag = $DBLIB->getOne('rfid_tags');
    if ($rfidTag) {
        // Asset ueber rfid_tags.assets_id laden
        $barcode = ['assets_id' => $rfidTag['assets_id'], 'assetsBarcodes_id' => null];
        // Scan loggen
        $DBLIB->insert('rfid_scans', [...]);
    }
}
```

### 7.2 Check-In/Check-Out erweitern

Der `CheckInOutService` bekommt eine neue Methode:

```php
public function rfidBulkCheckOut(int $instanceId, int $projectId, int $userId, array $epcs): array
{
    $results = ['checked_out' => [], 'unknown' => [], 'wrong_project' => []];
    $rfidSvc = new RfidService($this->db);

    foreach ($epcs as $epc) {
        $asset = $rfidSvc->findByEpc($epc);
        if (!$asset) {
            $results['unknown'][] = $epc;
            continue;
        }
        $this->checkOut($instanceId, $asset['assets_id'], $projectId, $userId, [
            'condition' => 'good',
            'scan_method' => 'bulk_rfid',
        ]);
        $results['checked_out'][] = $asset;
    }
    return $results;
}
```

### 7.3 Labels-Seite erweitern

Die bestehende `labels.php` wird um RFID-Encoding erweitert:

```php
if ($format === 'rfid_zpl') {
    // ZPL mit ^RFW Befehlen fuer RFID-Encoding
    foreach ($assets as $a) {
        $epc = RfidService::generateEpc($instanceId, $a['assets_id']);
        // ... ZPL mit RFID-Write ...
    }
}
```

### 7.4 Mobile App erweitern

Die PWA (`mobile_app.twig`) bekommt einen RFID-Tab:
- Bluetooth-RFID-Sled Anbindung (Web Bluetooth API)
- Live-Scan-Liste mit Asset-Zuordnung
- Quick Check-In/Out per RFID

---

## 8. Richtungserkennung (Gate In/Out)

Fuer die automatische Erkennung ob Equipment rein oder raus geht:

### Option 1: Zwei Antennen-Zonen
- Antenne A (aussen) und Antenne B (innen)
- Reihenfolge A->B = rein, B->A = raus
- Erfordert 2 Antennen + Timing-Logik

### Option 2: Manuelle Auswahl am Terminal
- Mitarbeiter drueckt "Ausgabe" oder "Ruecknahme" am Touchscreen
- Einfacher und zuverlaessiger
- **Empfohlen fuer den Anfang**

### Option 3: Lichtschranke + RFID
- Lichtschranke am Tor erkennt Durchgangsrichtung
- RFID liest gleichzeitig die Tags
- Komplex aber vollautomatisch

---

## 9. Fallback-Strategie

Nicht jedes Asset wird einen RFID-Tag haben (z.B. Kleinteile, Kabel).
Das System muss parallel funktionieren:

1. **RFID-Tag vorhanden** -> Automatischer Scan am Gate
2. **QR-Code vorhanden** -> Manueller Scan per Kamera/Handy
3. **Barcode vorhanden** -> Manueller Scan per Handscanner
4. **Nichts vorhanden** -> Manuelle Eingabe/Auswahl

Die bestehenden Barcode- und QR-Code-Workflows bleiben vollstaendig erhalten.
RFID ist eine zusaetzliche, schnellere Scan-Methode.

---

## 10. Zusammenfassung

| Was                    | Wie                                                    |
|------------------------|--------------------------------------------------------|
| **Label drucken**      | Zebra RFID-Drucker, ZPL mit ^RFW, QR+Barcode+RFID     |
| **Ausbuchen**          | Tor-Scanner oder USB-Leser, Projekt waehlen, durchgehen |
| **Einbuchen**          | Tor-Scanner oder USB-Leser, automatische Rueckbuchung   |
| **Inventur**           | Scanner im Inventur-Modus, Soll/Ist-Vergleich          |
| **Asset suchen**       | Mobiler Scanner, RSSI-basierte Ortung                   |
| **Anbindung**          | LLRP-Daemon, Webhook, oder USB HID                     |
| **Fallback**           | QR-Code und Barcode bleiben parallel nutzbar            |
| **Kosten**             | Ab ~2.000 EUR (Budget) bis ~5.000 EUR (vollstaendig)    |
