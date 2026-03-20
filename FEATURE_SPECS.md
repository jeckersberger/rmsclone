# MyRMS – Feature-Spezifikationen (ausformuliert)

**Stand:** 20. März 2026
**Zweck:** Detaillierte Beschreibung aller geplanten und implementierten Module. Jeder Baustein wird ausformuliert: Was soll er tun, welche Logik steckt dahinter, welche Ein-/Ausgaben gibt es, wie verhält sich die UI.

---

## L1 – Flexible Lagerorte

### Überblick
Jedes Asset und jeder Lagerbestand soll einem frei definierbaren Lagerort zugeordnet werden können. Lagerorte sind nicht fest vorgegeben, sondern werden vom Benutzer selbst angelegt – z.B. „Auto 1", „Anhänger blau", „Büro EG", „Garage Hinten", „Keller Regal 3". Jeder Lagerort bekommt ein Emoji-Icon zur schnellen visuellen Unterscheidung. Assets bleiben weiterhin frei buchbar (Verleih, Projekt-Zuordnung etc.), der Lagerort ist eine zusätzliche Information, die überall angezeigt wird.

### Bausteine im Detail

**1. DB-Migration: emoji_icon + instances_id auf locations, bulk_move_log**
Die bestehende `locations`-Tabelle wird um ein `emoji_icon`-Feld (VARCHAR, z.B. „🚗" oder „📦") und eine `instances_id`-Spalte (Mandantentrennung) erweitert. Zusätzlich wird eine neue Tabelle `bulk_move_log` angelegt, die protokolliert, wann welche Assets oder Lagerbestände von einem Ort zum anderen verschoben wurden (Zeitstempel, User, Quell-Ort, Ziel-Ort, betroffene Asset-IDs).

**2. LocationService: getLocationSummary()**
Eine Methode, die für jeden Lagerort eine Zusammenfassung liefert: Anzahl der Assets am Ort, Anzahl der Lagerbestands-Einheiten, Gesamtwert der gelagerten Gegenstände, letzter Bewegungszeitpunkt. Diese Daten werden als Array zurückgegeben und im Dashboard sowie in der Lagerort-Verwaltung angezeigt.

**3. LocationService: bulkMoveAssets(), bulkMoveStockInstances()**
Ermöglicht das Verschieben mehrerer Assets oder Lagerbestände auf einmal von einem Lagerort zu einem anderen. Der Benutzer wählt z.B. „alle 15 Kabel von Auto 1 nach Anhänger blau verschieben". Jede Bulk-Verschiebung wird im `bulk_move_log` protokolliert. Die Methode prüft, ob alle ausgewählten Assets tatsächlich am Quell-Ort sind, und gibt Fehlermeldungen zurück, wenn ein Asset bereits woanders ist.

**4. LocationService: getAssetsAtLocation(), createLocationInline()**
`getAssetsAtLocation()` liefert eine paginierte Liste aller Assets an einem bestimmten Lagerort, inklusive Asset-Typ, Status und letzter Bewegung. `createLocationInline()` erlaubt das schnelle Anlegen eines neuen Lagerorts direkt aus einem Dropdown heraus (z.B. beim Zuweisen eines Assets tippt man einen neuen Lagerort-Namen ein und er wird sofort angelegt, ohne die Seite zu verlassen).

**5–8. API-Endpunkte (Location Summary, Bulk Move, Assets at Location, Inline Create)**
Vier REST-Endpunkte unter `/api/locations/`:
- `GET /api/locations/summary` – Gibt die Zusammenfassung aller Lagerorte zurück (Anzahl Assets, Wert, letzter Move).
- `POST /api/locations/bulk-move` – Nimmt eine Liste von Asset-IDs und einen Ziel-Lagerort entgegen, verschiebt alle auf einmal.
- `GET /api/locations/{id}/assets` – Liefert alle Assets an einem bestimmten Lagerort, paginiert, mit Suchfilter.
- `POST /api/locations/inline-create` – Erstellt einen neuen Lagerort mit Name und optionalem Emoji, gibt die neue ID zurück.

**9. UI: manage.twig mit Emoji-Icons und Asset-Counts**
Die Lagerort-Verwaltungsseite zeigt alle Lagerorte als Karten oder Liste an. Jede Karte zeigt: Emoji-Icon groß, Name des Lagerorts, Anzahl Assets, Anzahl Lagerbestände, Gesamtwert. Per Klick öffnet sich die Detail-Ansicht mit allen Assets an diesem Ort. Drag & Drop zum Verschieben von Assets zwischen Lagerorten wäre ein Nice-to-have.

**10. Lagerort-Spalte in Asset-Listen**
In allen bestehenden Asset-Tabellen (Inventar-Übersicht, Projekt-Assets, Suchergebnisse) soll eine neue Spalte „Lagerort" erscheinen, die den aktuellen Lagerort des Assets mit Emoji anzeigt. Die Spalte soll sortierbar und filterbar sein. Wenn kein Lagerort zugewiesen ist, bleibt die Zelle leer.

**11. Lagerort-Abfrage bei Check-In**
Wenn ein Asset von einem Projekt zurückkommt (Check-In), soll das System fragen: „An welchen Lagerort soll dieses Asset zurück?" Der Benutzer wählt aus einem Dropdown den Lagerort aus oder legt inline einen neuen an. Der vorherige Lagerort wird als Vorschlag angezeigt. So bleibt die Lagerort-Zuordnung immer aktuell.

**12. Dashboard-Widget: Lagerort-Zusammenfassung**
Ein Widget auf dem Haupt-Dashboard, das die wichtigsten Lagerort-Informationen auf einen Blick zeigt: Wie viele Lagerorte gibt es, wie viele Assets sind insgesamt verteilt, welcher Lagerort hat die meisten Assets, gibt es Assets ohne Lagerort-Zuordnung. Klick auf das Widget führt zur Lagerort-Verwaltung.

**13. Tests**
Unit-Tests für alle LocationService-Methoden: getLocationSummary() mit verschiedenen Datenkonstellationen, bulkMoveAssets() mit gültigen und ungültigen Szenarien (Asset nicht am Quell-Ort, Ziel-Ort existiert nicht), createLocationInline() mit Duplikat-Check, getAssetsAtLocation() mit Paginierung.

---

## L2 – Preiskalkulations-Engine

### Überblick
Eine vollständige Preiskalkulations-Engine, die verschiedene Preismodelle unterstützt: Staffelpreise (unterschiedliche Preise je nach Mietdauer – Tag/Woche/Monat), Mengenrabatte (ab X Stück günstiger), Saisonzuschläge (Hochsaison teurer), Bundle-Pricing (Paketpreise für Geräte-Kombinationen) und kundenspezifische Preislisten (VIP-Kunden bekommen andere Preise). Die Engine berechnet den finalen Preis automatisch durch eine Kaskade: Basispreis → Staffel → Menge → Saison → Kundenliste → Bundle.

### Bausteine im Detail

**1. DB-Migration: 7 Tabellen**
Sieben neue Tabellen werden angelegt:
- `pricing_tiers` – Staffelpreise: Asset-Typ-ID, Dauer-Von (Tage), Dauer-Bis (Tage), Preis pro Tag. Beispiel: 1–3 Tage = 50€/Tag, 4–7 Tage = 40€/Tag, 8–30 Tage = 30€/Tag.
- `volume_discounts` – Mengenrabatte: Asset-Typ-ID, Ab-Menge, Rabatt-Prozent. Beispiel: ab 5 Stück 10% Rabatt, ab 10 Stück 20%.
- `seasonal_surcharges` – Saisonzuschläge: Zeitraum-Von, Zeitraum-Bis, Zuschlag-Prozent, Name. Beispiel: „Sommerhochsaison" 01.06.–31.08. +15%.
- `bundles` – Bundle-Definitionen: Name, Beschreibung, Gesamtpreis, Gültig-Von, Gültig-Bis.
- `bundle_items` – Items in einem Bundle: Bundle-ID, Asset-Typ-ID, Menge.
- `customer_lists` – Kundenpreislisten: Name, Beschreibung, Priorität (höhere Priorität überschreibt).
- `list_items` – Einträge in einer Kundenpreisliste: Liste-ID, Asset-Typ-ID, Sonderpreis pro Tag.

**2. PricingEngineService: calculatePrice() mit Kaskade**
Die zentrale Methode, die den Endpreis berechnet. Eingabe: Asset-Typ, Menge, Mietdauer (Tage), Kunde, Datum. Die Berechnung läuft in einer festen Reihenfolge (Kaskade):
1. Basispreis aus dem Asset-Typ holen
2. Staffelpreis anwenden (falls Mietdauer in einen Staffelbereich fällt, wird der Tagespreis überschrieben)
3. Mengenrabatt berechnen (falls Menge über Schwellwert, prozentualen Rabatt abziehen)
4. Saisonzuschlag prüfen (falls aktuelles Datum in einen Saisonzeitraum fällt, Zuschlag aufschlagen)
5. Kundenpreisliste prüfen (falls der Kunde eine Sonderpreisliste hat, den Preis überschreiben)
6. Bundle-Preis prüfen (falls alle Items eines Bundles gebucht werden, Bundle-Gesamtpreis statt Einzelpreise)
Rückgabe: Aufgeschlüsselter Preis (Basis, Staffelrabatt, Mengenrabatt, Saisonzuschlag, Kundenrabatt, Bundle-Ersparnis, Endpreis).

**3. PricingEngineService: Staffelpreis-CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Staffelpreisen pro Asset-Typ. Validierung: Dauer-Bereiche dürfen sich nicht überlappen (z.B. nicht 1–5 Tage und 3–7 Tage gleichzeitig). Der Preis muss > 0 sein. Beim Löschen eines Staffelpreises gilt wieder der Basispreis für diesen Dauerbereich.

**4. PricingEngineService: Mengenrabatte CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Mengenrabatten. Pro Asset-Typ können mehrere Stufen definiert werden (ab 5 Stück, ab 10 Stück, ab 20 Stück). Validierung: Mengenschwellen müssen aufsteigend sein, Rabatt-Prozent muss zwischen 0 und 100 liegen.

**5. PricingEngineService: Saisonzuschläge CRUD**
Verwaltung von Saisonzuschlägen. Jeder Zuschlag hat einen Namen (z.B. „Weihnachtsmarkt-Saison"), einen Zeitraum und einen Prozentsatz. Mehrere Saisonzuschläge können sich überlappen – in dem Fall werden sie addiert. Negative Werte sind erlaubt (Nebensaison-Rabatt).

**6. PricingEngineService: Bundle-Verwaltung**
Bundles sind Pakete aus mehreren Asset-Typen mit einem Paketpreis. Beispiel: „Veranstaltungspaket" = 10× Stuhl + 2× Tisch + 1× Zelt = 500€/Tag statt 650€/Tag einzeln. Der Benutzer kann Bundles zusammenstellen, Items hinzufügen/entfernen und den Paketpreis festlegen. Die Engine erkennt automatisch, wenn alle Items eines Bundles in einer Buchung enthalten sind, und bietet den Bundle-Preis an.

**7. PricingEngineService: Kundenpreislisten**
VIP-Kunden oder Stammkunden können einer Preisliste zugeordnet werden. Eine Preisliste enthält Sonderpreise für bestimmte Asset-Typen. Beispiel: Kunde „Müller GmbH" bekommt Bagger für 80€/Tag statt 100€/Tag. Preislisten haben eine Priorität – bei Zugehörigkeit zu mehreren Listen gewinnt die höchste Priorität.

**8. API: 10 Endpunkte in /api/pricing/**
- `GET /api/pricing/calculate` – Preis berechnen (Asset-Typ, Menge, Dauer, Kunde, Datum)
- `GET/POST/PUT/DELETE /api/pricing/tiers` – Staffelpreise CRUD
- `GET/POST/PUT/DELETE /api/pricing/discounts` – Mengenrabatte CRUD
- `GET/POST/PUT/DELETE /api/pricing/seasonal` – Saisonzuschläge CRUD
- `GET/POST/PUT/DELETE /api/pricing/bundles` – Bundle CRUD
- `GET/POST/PUT/DELETE /api/pricing/customer-lists` – Kundenpreislisten CRUD

**9. UI: pricing_manage.twig mit 5-Tab Layout**
Eine Verwaltungsseite mit 5 Tabs:
- Tab 1: **Staffelpreise** – Tabelle mit Asset-Typ, Dauer-Bereichen und Preisen. Inline-Bearbeitung.
- Tab 2: **Mengenrabatte** – Tabelle mit Mengenstufen und Rabattprozenten.
- Tab 3: **Saisonzuschläge** – Kalenderansicht oder Tabelle mit Zeiträumen und Zuschlägen.
- Tab 4: **Bundles** – Bundle-Cards mit enthaltenen Items und Paketpreis.
- Tab 5: **Kundenpreislisten** – Preislisten mit zugeordneten Kunden und Sonderpreisen.

**10. JS: Echtzeit-Preisrechner**
Ein JavaScript-Widget, das live den Preis berechnet, während der Benutzer Parameter ändert. Sobald Asset-Typ, Menge, Dauer oder Kunde geändert wird, ruft das Widget die API auf und zeigt sofort den aufgeschlüsselten Preis an (Basispreis, Rabatte, Zuschläge, Endpreis). Wird in der Projekt-Anlage und in der Angebotserstellung eingebunden.

**11. Integration in Projektanlage**
Wenn ein neues Projekt angelegt wird und Assets zugewiesen werden, soll die Preisberechnung automatisch im Hintergrund laufen. Der berechnete Preis wird als Vorschlag angezeigt und kann manuell überschrieben werden. Bei Änderungen (andere Mietdauer, andere Menge) wird automatisch neu kalkuliert.

**12. Integration in Rechnungserstellung**
Wenn eine Rechnung aus einem Projekt generiert wird, sollen die Preise aus der Engine übernommen werden. Die Rechnung zeigt die Aufschlüsselung: Basispreis, angewendete Rabatte, Zuschläge, Endpreis. Der Benutzer kann den Preis vor Rechnungsstellung noch manuell anpassen.

**13. Tests**
Unit-Tests für die calculatePrice()-Kaskade mit allen Kombinationen: nur Basispreis, Staffelpreis aktiv, Mengenrabatt aktiv, Saisonzuschlag aktiv, Kundenpreisliste aktiv, Bundle aktiv, alle gleichzeitig aktiv. Edge Cases: Menge = 0, Dauer = 0, kein passender Staffelpreis, überlappende Saisonzuschläge.
