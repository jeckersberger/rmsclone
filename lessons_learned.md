# Lessons Learned

## Regeln aus vergangenen Sessions
- [2026-03-17] Regel: Bei MeekroDB immer `getOne('table', null, ['columns'])` verwenden — zweiter Parameter ist WHERE-Kontext, dritter sind Spalten.
  - Grund: Sub-Agent hat `getOne('table', ['id'])` geschrieben, was den Spaltenarray als WHERE interpretiert hätte.
- [2026-03-17] Regel: MeekroDB unterstützt kein `where('(col1 = ? OR col2 = ?)', [$v1, $v2])` — stattdessen zwei separate Queries oder `orWhere` verwenden.
  - Grund: Sub-Agent hat komplexe SQL-Placeholder-Syntax verwendet die MeekroDB nicht unterstützt.
- [2026-03-17] Regel: Bei Sub-Agent-Delegierung immer die DB-Library-Konventionen (MeekroDB vs PDO vs Eloquent) explizit im Prompt erwähnen.
  - Grund: Sub-Agent kannte die spezifische API der verwendeten DB-Library nicht.
- [2026-03-18] Regel: MeekroDB hat kein standalone `groupBy()` — immer `rawQuery()` mit SQL GROUP BY verwenden.
  - Grund: 8 Services hatten ->groupBy() Aufrufe die bei MeekroDB nicht existieren.
- [2026-03-18] Regel: MeekroDB `$this->db->count` existiert nicht — `$this->db->affectedRows()` verwenden.
  - Grund: 2 Services nutzten ->count nach delete() was keine gültige MeekroDB-Property ist.
- [2026-03-18] Regel: Nach großem Batch-Fix immer einen Verifikations-Scan laufen lassen — erste Runde übersieht oft Dateien.
  - Grund: Nach dem Fix von 37 Services fand der Verifikations-Scan noch 6 übersehene getOne()-Stellen in 2 weiteren Dateien.
- [2026-03-18] Regel: Bei systematischen Codebase-Audits grep-basierte Suche statt manuelles Lesen verwenden.
  - Grund: Manuelle Reviews übersehen wiederkehrende Patterns. Regex-Pattern `getOne\([^,]+,\s*\['\w+'` findet zuverlässig alle Fälle.

## Session-Log
### 2026-03-17 — Federation Tag Scanning in allen Scan-Flows
- Aufgaben:
  - TID-basierte Auflösung in `findByTag()` hinzugefügt (CF-H906 liest TIDs, nicht EPCs)
  - Partner-Entity-Handling in `processUniversalScan()` implementiert (checkout/checkin/locate/inventory)
  - Cross-Instance-Fallback in `processScan()` für Legacy-Scan-Action
  - Partner-Tag-Erkennung in `processInventoryScan()` für Inventur
  - Partner-Tag-Erkennung in Box-Scan (StockItemService) mit `partner_items` Array
  - `foreign_loans` DB-Migration erstellt für Fremd-Ausleihe-Tracking
  - `logForeignScan()` Methode in RfidService für automatische Protokollierung
- Fehler:
  - Sub-Agent hat MeekroDB-inkompatible SQL-Syntax verwendet (OR-Klausel mit Placeholders)
  - Sub-Agent hat falsche getOne()-Parametreihenfolge verwendet
  - Beides im Review gefunden und gefixt
- Neue Regeln: MeekroDB-Konventionen dokumentiert (siehe oben)

### 2026-03-17 — Sub-Rental / Weitervermietung + Partner-Details
- Aufgaben:
  - Federation tag_lookup.php angereichert: Gibt jetzt Assignment-Status, Projektname, Serial zurück
  - CrossInstanceLookupService: Alle Asset-Lookups liefern jetzt current_assignment + status
  - tag_lookup.php Step 3 Fallback: MeekroDB-inkompatible OR-Queries durch sequentielle Queries ersetzt
  - RfidService: Partner-Responses enthalten entity_details mit Status + Assignment für App-Anzeige
  - Android ScanResultCard: Zeigt "Gehört: [Firma]" Badge + Entity-Details (Status, Projekt) an
  - Android BoxScanScreen: Zeigt "Fremdgeräte" als eigene Gruppe in orange an
  - scan.php handleUniversalScan + handleScan: is_foreign, owner_name, entity_details durchgereicht
  - Sub-Rental-Logik verifiziert: Partner-Job bleibt aktiv, lokaler Job bekommt foreign_loans-Eintrag
- Fehler:
  - API-Endpunkte (handleUniversalScan, handleScan) haben is_foreign/entity_details nicht durchgereicht
  - Erst im Verifikations-Schritt entdeckt — zeigt wie wichtig End-to-End-Prüfung ist
- Neue Regeln: Siehe oben (API-Durchreichung immer prüfen)

### 2026-03-17 — Domain-Wissen aus User-Gespräch
- **Drei Entity-Kategorien:** Gerät (Asset, einzigartig, RFID+QR), Artikel (Stock Item, gleichartig aber einzeln RFID-getagged), Verbrauchsgegenstand (Consumable, nur Menge — NOCH NICHT GEBAUT)
- **Seriennummern:** Interne S/N fängt bei 1 an pro IDENTISCHEM Modell (z.B. 4x MAC Aura = 1-4), NICHT pro Gerätetyp-Kategorie. assetTypes = spezifisches Modell inkl. Hersteller.
- **Projects = Kundenjobs/Events:** Hochzeit, Festival etc. Gemischte Positionen: Miete + Verkauf + Verbrauch auf einem Job.
- **Federation = immer Remote:** Jede Firma hostet selbst. `partner_servers` ist der Standard-Weg. `partner_links` (gleiche DB) ist Sonderfall. Freundescode-Flow muss noch designt werden.
- **external_items = Leihgeräte von Partnern:** Werden ganz normal verwaltet, gehören aber nicht der eigenen Firma. Permanente Verwaltung im Gegensatz zu foreign_loans (Scan-Log).
- **definableFields_1-5:** Vordefinierte Felder mit fester Bedeutung (Labels noch nicht festgelegt). Nicht frei benennbar durch User.
- **QR + RFID parallel:** CF-H906 hat UHF RFID + 2D Barcode. App muss beides handlen. Scan-Input kann TID (hex), RMS-Format (QR), oder Barcode sein.
- **Kisten-Soll-Inhalt:** Bereits gebaut (case_contents, CaseVerifyScreen). Typ-basiertes Matching ("2x Moving Head", nicht spezifische Instanzen).
- **Box Scan Sessions / Standort-Tracking:** saveBoxScanSession war Ansatz für Standortzuweisung. User will: Geräte scannen → Lagerort zuweisen (Anhänger, Auto, Zwischenlager). Braucht storage_locations-Tabelle + current_location_id auf Assets/Stock Instances.
- **assetsBarcodes Tabelle:** 1:1 Beziehung. Jedes Gerät hat entweder nur RFID oder RFID+QR. Nie mehrere Barcodes pro Gerät.
- **Seriennummer-Zählung:** Pro IDENTISCHEM Modell, nicht pro Gerätetyp-Kategorie. assetTypes IST bereits das spezifische Modell (inkl. Hersteller via manufacturers_id). generateInternalSerial() zählt pro assetTypes_id = korrekt.
- **external_items:** Leihgeräte von Partnerfirmen. Werden ganz normal verwaltet, gehören aber nicht der eigenen Firma. Frage offen: automatisch anlegen beim ersten Scan oder manuell im Web-UI?
- **scan vs universal_scan:** universal_scan ist der aktuelle Standard (Assets + Stock + Partner). scan ist Legacy.
- **Verbrauchsgegenstände (Consumables):** NOCH NICHT GEBAUT. Workflow: Menge raus auf Job, Menge zurück, Differenz = verbraucht. Eigene Tabelle nötig. Nicht für 1.1.

### 2026-03-18 — Phase 9: Finaler Audit aller Services
- Aufgaben:
  - 103 Migration-Dateien auf Syntax geprüft → alle sauber
  - 124 Service-Dateien systematisch auf MeekroDB-Antipatterns gescannt
  - 99 getOne()-Aufrufe in 39 Services korrigiert (Spalten-Array als 3. statt 2. Parameter)
  - 9 groupBy()-Aufrufe in 8 Services durch rawQuery() mit SQL GROUP BY ersetzt
  - 2 count-Properties durch affectedRows() ersetzt
  - 4 Yellow-flagged Services gefixt: CollectiveInvoiceService (DI), InvoiceMailService ($bCMS-Guard), AiActionQueueService (Constructor), ShippingService (konfigurierbarer DHL-Endpoint)
- Fehler:
  - Erster Batch-Fix (37 Services) hat StockWarningService und DocumentRenderer übersehen
  - Verifikations-Scan nach dem Fix hat die 6 fehlenden Stellen gefunden
- Ergebnis: 0 verbleibende MeekroDB-Antipatterns in der gesamten Codebase
- Commit: db986199
