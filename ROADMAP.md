# MyRMS - Comprehensive Extension & UI Improvement Plan

**Date:** March 2026
**Project:** MyRMS (Adam RMS Clone) - Rental Management System
**Status:** Research Phase - Ready for Prioritization

---

## Executive Summary

This document outlines a comprehensive roadmap for extending MyRMS with modern UI/UX improvements, architectural modernization, and significant feature additions. The plan is organized into strategic tracks with realistic effort estimates and prioritization levels.

**Current State:**
- Mature backend with 100+ database tables
- AdminLTE 3 + Bootstrap 4 UI (functional, dated)
- PHP 8.3 with Twig templating, no MVC framework
- 130+ API endpoints (not RESTful)
- 120+ Service classes for business logic
- Mobile PWA support (basic)
- Comprehensive German compliance (KUR, GoBD, DSGVO, ZUGFeRD)

**Strategic Goals:**
1. Modernize UI/UX for 2026+ standards
2. Improve developer experience with proper architecture
3. Add real-time collaboration features
4. Expand reporting and analytics capabilities
5. Enhance mobile-first design approach

---

## Part A: UI/UX Improvements

### A1. Dashboard Redesign & Analytics Hub
**Effort:** M (3-4 weeks)
**Priority:** HIGH
**Impact:** Daily visibility + decision support

#### Current State:
- Widget-based dashboard with card-columns layout
- Calendar widget + customizable widgets
- Limited KPI visibility
- Mobile layout is suboptimal

#### Proposed Improvements:

**A1.1 Executive Dashboard (Tier 1)**
| Feature | Effort | Details |
|---------|--------|---------|
| KPI Cards | S | Revenue (MTD/YTD), Open Invoices, Utilization %, Pending Projects |
| Real-time Revenue Chart | M | Line chart: daily revenue, 30-day trend with forecast |
| Top Assets Utilization | S | Bar chart: most rented items this month |
| Overdue Invoices Alert | S | Red banner with count + quick links |
| Upcoming Deadlines | S | Next 7 days: deliveries, returns, maintenance |
| Cash Flow Forecast | M | 30/60/90-day projection based on historical patterns |

**Detaillierte Erklärung - Executive Dashboard:**

Das Executive Dashboard stellt die oberste Ebene dar, auf der Geschäftsführer und Eigentümer auf einen Blick die kritischsten Kennzahlen einsehen können. Im Mietequipmentsegment sind die fünf bis sieben wichtigsten KPIs (Key Performance Indicators) gemäß aktueller Industrie-Best-Practices (2025/2026) die Auslastungsquote (Occupancy/Utilization Rate), Gesamtumsatz im Monat und Jahr (Monthly-to-Date und Year-to-Date Revenue), Nettoeinnahmen (Net Operating Income), Mietquote-Ausfallsicherheit (Rent Collection Rate) und Mietverteilungsanalyse (Rent Roll Analysis). Diese KPIs bilden die finanzielle und operative Gesundheit des Unternehmens ab und ermöglichen es Führungskräften, strategische Entscheidungen in Echtzeit zu treffen.

Die technische Implementierung erfolgt über Chart.js, eine leichte JavaScript-Bibliothek, die sich besonders für Produktionsumgebungen eignet, wo Seitenlade-Performance kritisch ist. Chart.js benötigt keine externen Abhängigkeiten wie jQuery-Plugins und rendert sowohl auf Desktop als auch auf mobilen Geräten problemlos. Die Diagramme werden per WebSocket oder AJAX aktualisiert (Standard-Refresh-Intervall: 30-60 Sekunden, konfigurierbar pro Benutzer). Dies entspricht modernen Anforderungen an „Real-Time KPI Dashboards", die laut Industrie-Analysen zur unverzichtbaren Führungs-Infrastruktur in 2026 geworden sind. Alternativ kann Recharts (wenn React im Tech-Stack vorhanden) oder ECharts (für komplexere Visualisierungen) in Betracht gezogen werden.

Die Gestaltung der KPI-Karten muss nach dem „progressive disclosure"-Prinzip erfolgen: Die oberflächliche Anzeige zeigt die nackte Zahl (z.B. „€127.450 MTD Revenue"), beim Hover oder Klick erscheinen Kontext-Informationen (Vergleich zum Vormonat, Trend-Indikator als Pfeil, Zielabweichung). Dies reduziert kognitive Last und macht das Dashboard schneller erfassbar. Die Farbe der KPI-Karten sollte nicht nur Rot/Grün verwenden (inklusiv für Farbenblindheit), sondern auch Formen (Pfeil-Richtung, Icon-Art) zur Unterscheidung nutzen.

Das Cash-Flow-Forecast-Feature nutzt historische Daten der letzten 12 Monate, um eine rollende Prognose für die nächsten 30, 60 und 90 Tage zu berechnen. Dies ist ein wichtiger Aspekt für Liquiditätsplanung, besonders in Zeiten volatiler Zahlungsverhalten. Die Prognose basiert auf durchschnittlichen Zahlungszyklen und saisonalen Mustern, die aus den Daten extrahiert werden. Ein Warnsystem (z.B. rote Markierung) zeigt Tage an, an denen Liquidität knapp wird.

---

**A1.2 Project Manager Dashboard (Tier 2)**
| Feature | Effort | Details |
|---------|--------|---------|
| Project Pipeline | M | Kanban: stages (enquiry→quote→confirmed→active→closed) |
| Asset Dispatch Board | S | Kanban: not picked→picked→in-use→returned→checked |
| Team Availability | S | Calendar: crew availability heatmap |
| Resource Conflicts | S | Alerts for double-booked assets/crew |
| Daily Briefing | M | Auto-generated list: today's tasks, deliveries, critical items |

**Detaillierte Erklärung - Project Manager Dashboard:**

Das Project Manager Dashboard richtet sich an Projektleiter und Einsatzkoordinatoren, die täglich mit der Verwaltung von Mietprojekten und Ressourcentransfers betraut sind. Das Kanban-System (Stages: Enquiry → Quote → Confirmed → Active → Closed) visualisiert den gesamten Projektlebenszyklus in einem Blick. Dies basiert auf bewährten Agile- und Lean-Prinzipien, die zeigen, dass visuelle Workflow-Darstellungen die Durchsatzzeit (Cycle Time) um durchschnittlich 25-30% reduzieren können.

Die „Asset Dispatch Board" ist ein spezialisiertes Kanban für den physischen Fluss von Equipment: von „not picked" (noch nicht abgeholt) über „picked" (abgeholt, bereit für Transport), „in-use" (im Einsatz beim Kunden), „returned" (zurückgebracht vom Kunden) bis „checked" (geprüft auf Schäden/Funktionsfähigkeit). Jede Karte in diesem Board sollte Echtzeit-Informationen anzeigen: Equipment-Name, zugeordnete Crew-Mitglieder, Kundennamen, Lieferadresse und geschätzte Rückkehr-Zeit. Diese Kanban-Bretter sollten per WebSocket-Updates in Echtzeit synchronisiert werden, sodass mehrere Mitarbeiter die gleiche Ansicht sehen und Konflikte sofort erkannt werden.

Die „Team Availability"-Komponente zeigt eine Kalender-Heatmap für Crew-Mitglieder: Grüne Zellen bedeuten „verfügbar", Gelb „teilweise verfügbar", Rot „nicht verfügbar". Dies ermöglicht schnelle visuelle Erfassung, wer für kommende Projekte eingeplant werden kann. Die Heatmap-Ansicht ist effizienter als Listen-Ansichten für zeitbasierte Ressourcenplanung. Technisch basiert dies auf einer einfachen Matrix aus Datentabellen-Datensätzen.

Das „Resource Conflict"-Alerting prüft automatisch auf doppelte Buchungen: Wenn ein Asset oder eine Crew-Person zwei gleichzeitig buchungen hat, erscheint ein rotes Alert-Banner. Dies verhindert logistische Katastrophen und spart Zeit beim manuellen Überprüfen von Kalender-Konflikten.

Das „Daily Briefing" ist eine maschinell generierte Zusammenfassung der heutigen Aufgaben: bevorstehende Lieferungen, Rückkünfte, kritische Wartungen, fällige Zahlungen und neue Kundenanfragen. Dies wird beim Einloggen am Morgen angezeigt und kann als PDF exportiert oder per Email verschickt werden. Dies folgt dem Konzept des „Management-by-Exception": Nur kritische und unmittelbar relevant Informationen werden priorisiert.

---

**A1.3 Financial Dashboard (Tier 3)**
| Feature | Effort | Details |
|---------|--------|---------|
| Revenue vs. Budget | M | Variance analysis with threshold warnings |
| Margin Analysis | S | Gross margin by asset type, customer, project |
| Customer A/R Aging | S | Pyramid: 0-30 / 30-60 / 60-90 / 90+ days |
| Profitability per Customer | M | Table: revenue, COGS, margin % ranked |
| Cash Position | S | Current balance, next 7 days cash in/out |

**Detaillierte Erklärung - Financial Dashboard:**

Das Financial Dashboard ist speziell für CFOs, Buchhalter und Finanzmanager konzipiert und bietet granulare Einblicke in finanzielle Leistung und Gesundheit. Die „Revenue vs. Budget"-Komponente zeigt eine Varianzanalyse: Soll-Umsatz vs. Ist-Umsatz für den aktuellen Monat/Quartal/Jahr. Ein Schwellenwert-System warnt automatisch, wenn die Abweichung (Variance) über ±5-10% liegt. Dies entspricht modernen Controllership-Standards, wo Budgetabweichungen früh erkannt werden müssen.

Die Margin-Analyse zerlegt den Bruttogewinn (Gross Margin) nach verschiedenen Dimensionen: nach Equipment-Kategorie (z.B. Kameras vs. Beleuchtung), nach Kunden (um zu sehen, welche Kunden profitabel sind) oder nach Projekten. Dies ist kritisch, um zu verstehen, wo Rentabilität entsteht oder verloren geht. Technisch erfolgt dies durch GROUP-BY-Queries in der Datenbank, mit MySQL-Aggregation oder mittels PHP-Anwendungslogik für komplexere Berechnungen.

Die „Customer A/R Aging"-Pyramide (Accounts Receivable Aging) kategorisiert ausstehende Zahlungen nach Tagen seit Rechnungsdatum:
- 0-30 Tage: Current (grüner Bereich)
- 30-60 Tage: 1-2 Monate überfällig (gelb)
- 60-90 Tage: 2-3 Monate überfällig (orange)
- 90+ Tage: Kritisch überfällig (rot)

Diese Pyramiden-Visualisierung ist intuitiver als Tabellen und zeigt sofort, wie viel „bad debt risk" das Unternehmen trägt. Breite rote Basis bedeutet ein Problem mit Zahlungsmoral; eine schmale rote Basis bedeutet gutes Forderungsmanagement.

Die „Profitability per Customer"-Tabelle rangiert Kunden nach Rentabilität. Berechnung: (Einnahmen aus Miet-Projekten - COGS [Kosten für Equipment-Abnutzung/Transport/Admin]) / Einnahmen = Margin %. Dies hilft zu erkennen, welche Kundenbeziehungen tatsächlich wertvoll sind. Oft findet sich heraus, dass Großkunden niedriger Margin aufweisen als spezialisierte, kleine Nischen-Kunden.

Der „Cash Position"-Widget zeigt den aktuellen Kontostand und einen 7-Tage-Cashflow-Plan: an welchen Tagen Geld reinkommen wird, an welchen Ausgaben anfallen. Dies ist lebensnotwendig für tägliche Liquiditätsmanagement-Entscheidungen.

---

**A1.4 Inventory Dashboard (Tier 4)**
| Feature | Effort | Details |
|---------|--------|---------|
| Stock Levels | S | Low-stock alerts, reorder recommendations |
| Asset Health | S | Maintenance due, damage flags, depreciation curve |
| Location Summary | S | Assets per warehouse/location with utilization |
| Lifecycle Overview | M | Assets by age: new / mid-life / EOL warnings |

**Detaillierte Erklärung - Inventory Dashboard:**

Das Inventory Dashboard richtet sich an Operations-Manager, Lager-Leiter und Supply-Chain-Koordinatoren. Das „Stock Levels"-Feature überwacht Bestandsmengen und triggert automatische Alerts, wenn ein Asset-Typ unter einen konfigurierten Mindestbestand fällt. Eine Reorder-Engine kann, basierend auf durchschnittlicher monatlicher Auslastung, proaktiv Empfehlungen geben: „Sie sollten 5 weitere Hochleistungs-Dimmschalter kaufen, da Sie in den nächsten 4 Wochen wahrscheinlich verkauft sein werden."

Das „Asset Health"-Feature aggregiert Wartungs-Status für alle Assets. Es zeigt an:
- Wartungen, die überfällig sind (rote Markierung)
- Bekannte Schäden (z.B. „Lens-Kratzer", „Batterie-Kontakt-Oxidation")
- Depreciations-Kurve: wie schnell ein Asset an Wert verliert (wichtig für Versicherung und Bilanzkalkulation)

Eine Depreciation-Kurve könnte beispielsweise sein: Jahr 1: 100% (Neuwert €10.000), Jahr 2: 70%, Jahr 3: 50%, Jahr 4: 30%, Jahr 5+: Schrottwert. Dies ist essentiell für korrekte Bilanzierung und für Entscheidungen, wann Assets ausgemustert werden sollten.

Das „Location Summary"-Widget gibt einen Überblick über Lagerverwaltung: „Warehouse Hamburg: 347 Assets (87% ausgelastet), Warehouse Berlin: 512 Assets (92% ausgelastet), Showroom Munich: 45 Assets (demo only)". Dies hilft, Lagerfläche effizient zu nutzen und Überbestände zu erkennen.

Das „Lifecycle Overview"-Feature kategorisiert Assets nach Alter:
- New (0-1 Jahr): Vollständig funktional, maximale Einsatzfähigkeit
- Mid-life (1-3 Jahre): Noch zuverlässig, aber regelmäßige Wartung nötig
- EOL (End-of-Life) Warnung (3+ Jahre): Zuverlässigkeit sinkt, sollte bald ersetzt werden

Dies ist kritisch für Kapitalplanung: Wann müssen Investitionen in neue Equipment getätigt werden?

---

**Implementation Notes:**
- Use Chart.js or Recharts for visualization (no jQuery plugins)
- Implement dashboard refresh interval (user-configurable, 30-60s default)
- Add dashboard preset templates (Finance, Operations, Executive)
- Implement drag-to-customize layout with localStorage persistence
- Support multiple dashboards per role

**Detaillierte Implementierungs-Hinweise:**

Die Wahl von Chart.js gegenüber älteren jQuery-Plugins (wie Flot oder Highcharts) ist bewusst, um Performance zu optimieren: Chart.js ist nur ca. 60 KB groß, während Enterprise-Lösungen mehrere MB aufblasen können. Auf mobilen Verbindungen (3G/LTE in Außenbereichen) ist diese Größendifferenz spürbar.

Das user-konfigurierbare Refresh-Intervall ermöglicht es, den Kompromiss zwischen Echtzeit-Aktualität und Serverlast zu steuern. Ein Projektmanager könnte sein Dashboard auf 10-Sekunden-Refresh einstellen (aggressiv, hohes Datenvolumen), während ein CFO 5-Minuten-Refresh bevorzugt (ruhigere Ansicht, weniger Traffic).

Preset-Templates sparen Zeit: Neue Benutzer müssen nicht ihr Dashboard von Grund auf konfigurieren, sondern können einfach die vordefinierte „Finance"-Vorlage laden. Diese Templates werden im Backend als JSON gespeichert und können pro Rolle konfiguriert werden.

Das „Drag-to-Customize"-System nutzt HTML5 Drag & Drop API und localStorage für Persistierung. Wenn ein Benutzer Widgets ändert (z.B. Revenue-Chart nach links verschiebt), wird die Layout-JSON sofort in localStorage geschrieben und beim nächsten Login wieder hergestellt. Optional kann diese auch in der Datenbank synchronisiert werden für Cross-Device-Konsistenz.

Das Multi-Dashboard-Support bedeutet, dass ein Benutzer mehrere verschiedene Dashboard-Layouts speichern kann (z.B. „My Morning Review", „Deep-Dive Analysis", „Investor Presentation Mode"). Ein schnelles Dropdown-Menü wechselt zwischen ihnen.

---

### A2. Navigation & Information Architecture Overhaul
**Effort:** M (3-4 weeks)
**Priority:** HIGH
**Impact:** 40% reduction in click-depth

#### Current Issues:
- Sidebar is cluttered with 20+ top-level items
- No breadcrumb navigation
- No contextual "back" links
- Search is isolated, not contextual

#### A2.1 Sidebar Restructuring
```
Structure:
├── Dashboard (icon: chart-bar)
├── Projects (icon: briefcase)
│   ├── Active Projects
│   ├── Pipeline (Enquiries → Quotes)
│   ├── Completed
│   └── Dispatch Board
├── Equipment (icon: box)
│   ├── Asset Directory
│   ├── Categories
│   ├── Manufacturers
│   ├── Stock Management
│   └── Availability Calendar
├── Customers (icon: users)
│   ├── Customer List
│   ├── Contacts
│   ├── Communication Log
│   └── Credit Management
├── Finance (icon: wallet)
│   ├── Invoices & Quotes
│   ├── Payments & Collections
│   ├── Reports & Analytics
│   └── Accounting Integration
├── Operations (icon: cogs)
│   ├── Crew Management
│   ├── Locations/Warehouses
│   ├── Maintenance Schedule
│   ├── Transport Planning
│   └── Damage Reports
├── Admin (icon: lock)
│   ├── Users & Roles
│   ├── Business Settings
│   ├── Compliance
│   ├── Integrations
│   └── System Health
└── Workspace (icon: window)
    ├── Messages
    ├── Notifications
    ├── Calendar
    └── Documents
```

**Detaillierte Erklärung - Sidebar-Restrukturierung:**

Das aktuelle Sidebar-Layout mit 20+ Top-Level-Items ist ein klassisches Zeichen von Information Architecture Decay: Neue Features wurden hinzugefügt, ohne alte zu entfernen oder umzustrukturieren. Dies führt zu einer langen, unorganisierten Liste, die kognitive Last erhöht und Onboarding-Zeiten verlängert.

Die neue Struktur gruppiert Funktionen in 8 Haupt-Kategorien (Domains), von denen jede ein klares, wiedererkennbares Icon hat. Diese Anzahl (5-9) folgt George Miller's „Magical Number Seven" aus der Kognitionspsychologie: Menschen können etwa 7±2 Konzepte gleichzeitig im Arbeitsspeicher halten. Mit 8 Hauptkategorien bleiben wir im optimalen Bereich.

Jede Kategorie hat 3-5 Unterpunkte (Nested Level 1). Dies reduziert die Anzahl der Items pro Stufe und schafft logische Gruppierungen. Beispiel: Statt „Customers" als flaches Item zu haben, wird es zu einer expandierbaren Gruppe mit „Customer List", „Contacts", „Communication Log", „Credit Management". Dies basiert auf Domain-Driven Design (DDD) Prinzipien, wo jede Domäne ihre eigenen, zusammenhängenden Funktionen bündelt.

Das Icon-System ist essentiell: nicht nur für visuelle Differenzierung, sondern auch für Benutzer mit Legasthenie oder Farbenblindheit. Icons sollten aus einem konsistenten Icon-Set (z.B. Feather Icons, Font Awesome) stammen und die Konzepte repräsentieren (Briefcase = Projekte, Box = Equipment, Users = Kunden). Das Icon wird links, der Text rechts angeordnet. Auf mobilen Geräten können bei Platzmangel Icons allein ohne Text angezeigt werden (nach Hover-Testing validieren).

Die zweite Ebene (z.B. „Active Projects" unter „Projects") sollte per Click expandierbar sein, mit einem Chevron-Icon (> oder v) zur Zustandsanzeige. Ein expandierter State wird gespeichert (localStorage oder Backend), sodass Benutzer ihre bevorzugte View wieder sehen beim nächsten Login.

---

**A2.2 Breadcrumb Navigation**
- Add breadcrumb trail on all content pages
- Context-aware: show parent → current page
- Example: `Dashboard > Projects > Manufacturing Inc. > Equipment Allocation`
- Click-through navigation for parent contexts

**Detaillierte Erklärung - Breadcrumb Navigation:**

Breadcrumbs sind eine bewährte UI-Pattern seit dem Web 1.0, aber oft mangelhaft implementiert. Eine gute Breadcrumb-Implementierung zeigt den aktuellen Ort des Benutzers in der Hierarchie und ermöglicht schnelle Rückkehr zu übergeordneten Seiten.

Beispiel einer korrekten Breadcrumb-Struktur:
```
Home > Projects > Manufacturing Inc. > Equipment Allocation
```

Jeder Link (außer dem letzten) ist klickbar. Beim Klick auf „Projects" springt der Benutzer zur Projekt-Listenseite, ohne dass der Zustand (Filter, Sortierung) verloren geht - das erfordert URL-basierte State (Query-Parameter statt nur Fragment).

Context-Awareness bedeutet: Die Breadcrumb zeigt den Pfad, dem der Benutzer gefolgt ist, nicht nur die theoretische Hierarchie. Wenn jemand über die Suche zu einem Projekt kam, könnte die Breadcrumb sein: `Home > Search Results > Manufacturing Inc. > Equipment Allocation`. Dies ist kognitiv korrekter als eine statische Hierarchie.

Breadcrumbs sollten auf allen Seiten außer der Homepage angezeigt werden. Sie sind besonders wertvoll auf tiefen Seiten (Level 3+). Auf der Homepage entfallen sie.

Ein visueller Standard für Breadcrumbs (WCAG-konform):
- Schrift-Größe: gleich wie Body-Text
- Farbe: Grau, Links in Akzent-Farbe
- Separator: `>` oder `/` (nicht zu groß)
- Hover-State: Underline oder Fett, aber kein Farbwechsel
- Text-Alignment: Left-aligned, Padding small (8px)
- Sticky Position: Für lange Listen ist die Breadcrumb über dem Seiteninhalt hilfreich

---

**A2.3 Contextual Quick Actions**
- Add floating action button (FAB) on each section
- Quick-add: "New Project", "New Asset", "New Invoice"
- Context menu: right-click on list items
- Batch actions: select multiple items with checkboxes

**Detaillierte Erklärung - Contextual Quick Actions:**

Ein Floating Action Button (FAB) ist ein runder Button, der in der unteren-rechten Ecke schwebend über dem Seiten-Inhalt platziert wird (z.B. bei mobilen Apps wie Gmail, Google Maps). Im Web ist dieser Pattern weniger verbreitet, aber sehr effektiv für häufige Aktionen.

In MyRMS könnte der FAB auf der Projects-Seite ein „+" Icon zeigen. Beim Klick öffnet sich ein Menü mit Optionen: „New Project", „Bulk Import", „Project Template". Dies ist schneller als in ein Menü zu navigieren und reduziert Klick-Tiefe.

Das Context-Menü (Rechtsklick auf List-Items) ist ein oft übersehenes UX-Pattern. Wenn der Benutzer mit der rechten Maustaste auf einen Projekt-Eintrag klickt, erscheint ein Popup-Menü mit Aktionen: „Edit", „Duplicate", „Archive", „Assign to Team", „Mark as Complete". Dies ist besonders auf Desktop effektiv und reduziert die Notwendigkeit für separate Buttons pro Item.

Batch-Aktionen nutzen Checkboxes links neben jedem Item. Wenn mindestens ein Item selektiert ist, erscheint eine Kontroll-Leiste oben/unten mit Aktionen: „Bulk Edit", „Assign Team", „Change Status", „Export", „Delete". Dies ist essentiell für effiziente Daten-Verwaltung: Ein Benutzer kann z.B. 50 überfällige Invoices schnell als „Sent Payment Reminder" markieren, statt jede einzeln zu öffnen.

Das Checkbox-System nutzt einen Master-Checkbox in der Tabellen-Header, der alle sichtbaren Elemente selektiert. Aber Achtung: Das ist kontrovers! Manche User erwarten damit, dass alle Elemente (auch auf anderen Seiten) selektiert werden, nicht nur die sichtbaren. Dies sollte durch ein Hinweistext geklärt werden: „3 of 47 items selected" wenn nur auf dieser Seite selektiert.

---

**A2.4 Global Search Enhancement**
```
Current: Basic text search in assets/clients
Proposed:
├── Search box in header (always visible)
├── Multi-type results
│   ├── Projects
│   ├── Assets
│   ├── Customers
│   ├── Invoices
│   ├── Documents
│   └── Users
├── Search history (localStorage)
├── Recent items (star icon to save)
├── Advanced filters dropdown
└── Search suggestions (fuzzy match)
```

**Detaillierte Erklärung - Global Search Enhancement:**

Das aktuelle Suchen-Feature beschränkt sich auf einzelne Entitäten (nur Assets, oder nur Kunden). Eine globale Suche ermöglicht es, nach „Lamp" zu suchen und Ergebnisse aus mehreren Kategorien zu sehen: das Asset „Lamp", der Kunde „Lamp Rental Solutions", das Projekt „Stage Lighting for Theater Lamp", die Invoice „INV-2024-Lamp-001", usw.

Die Such-Box wird prominent in der Header-Leiste platziert (oben, mittig oder rechts). Sie ist immer sichtbar, auf allen Seiten. Die Suchfeld-Styling sollte ein großes Textfeld mit Lupe-Icon sein (mindestens 44px Höhe auf Mobile für Touch-freundlich).

Die Implementierung nutzt eine Such-Engine. Die Optionen sind:

1. **Meilisearch** (empfohlen für MyRMS): Rust-basiert, extrem schnell (<50ms Antwort-Zeit), Typo-tolerant (sucht nach „Lampe" auch wenn „Lamp" eingegeben), einfache PHP-SDK. Kosten: selbst-hosted kostenlos, oder Cloud-Service €25-100/Monat je nach Volumen. Größter Vorteil: Einfache Einrichtung, große PHP-Community Support.

2. **Elasticsearch**: Heavy-Weight, aber mächtig für sehr große Datenmengen (100M+ Dokumente). Benötigt dediziertes Wissen zur Konfiguration. Kosten: Server-Infrastruktur + Betrieb. Nur empfohlen wenn MyRMS bereits sehr groß ist.

3. **Fallback - MySQL Fulltext Search**: Interim-Lösung mit MySQL's FULLTEXT Index. Nicht so schnell/flexibel wie Meilisearch, aber ohne externe Dependencies. Guter Kompromiss für Phase 1.

Bei Meilisearch wird jede Entität (Project, Asset, Customer, Invoice) als separates „Collection" (oder „Index") konfiguriert. Beim Tipen im Suchfeld wird parallel gegen alle Collections gesucht und Ergebnisse gruppiert nach Type angezeigt:

```
Sucheingabe: "lamp"

RESULTS:
━━━━━━━━━━━━━━━━
🔧 ASSETS (3 matches)
  • Lamp ETC Source Four - Category: Lighting
  • Lamp LED Technobeam - Category: Lighting
  • Lamp Holder Stand Set - Category: Accessory

👥 CUSTOMERS (1 match)
  • Lamp Rental Solutions Inc. - Berlin

📋 PROJECTS (2 matches)
  • Stage Lighting for Theater Lamp - Status: Active
  • Temporary Event Lamp Setup - Status: Completed

💰 INVOICES (1 match)
  • INV-2024-LAM-001 - Lamp Rental Solutions Inc.
```

Die Such-Ergebnisse sollten nach Relevanz rankiert werden. Perfekte Matches (z.B. Name beginnt mit „lamp") oben, Partial Matches unten.

**Search History** speichert die letzten 10-15 Suchanfragen pro Benutzer in localStorage oder Backend-DB. Wenn der Suchfeld leer ist (oder Focus erhält), werden kürzliche Suchen angezeigt. Dies ermöglicht schnelle Wiedersuche nach ähnlichen Begriffen.

**Recent Items with Star Icon**: Häufig braucht ein Benutzer wieder auf die gleiche Seite (z.B. „Kunde XYZ"). Im Such-Ergebnis gibt es ein Stern-Icon neben jedem Item. Beim Klick wird das Item zu den „Favorites" hinzugefügt. Diese erscheinen dann oben in der Search-Dropdown, oder in der Sidebar als Shortcut.

**Advanced Filters Dropdown**: Für Power-User gibt es ein „Advanced Search"-Modus (Lupe + Filter-Icon). Hier können spezifische Filter gesetzt werden:
- Asset-Kategorie: „Lighting nur"
- Status: „In Use nur"
- Preis-Range: „über €5000"
- Datum-Range: „letzte 30 Tage"

Dies ist ähnlich wie Google-Advanced-Search und reduziert Rauschen in Ergebnissen.

**Fuzzy Matching und Typo-Toleranz**: Meilisearch unterstützt Typo-Toleranz out-of-the-box. Wenn der Benutzer „Lampe" (deutsch) statt „Lamp" eingibt, wird trotzdem korrekt gesucht (wenn in Datenbank beide Formen existieren). Levenshtein-Distanz wird berechnet: Editierungsabstand von max. 2 Zeichen ist erlaubt.

---

### A3. Mobile-First Redesign
**Effort:** L (4-6 weeks)
**Priority:** MEDIUM
**Impact:** 30%+ users on mobile

#### Current Issues:
- AdminLTE responsive, but not mobile-optimized
- Tables break on small screens
- Forms have too many fields visible at once
- No touch-friendly UI (buttons too small, no swipe gestures)

**Detaillierte Erklärung - Motivation für Mobile-First:**

Gemäß aktuellen Industrie-Statistiken (2025) stammen 59-64% des Web-Traffics von mobilen Geräten. Das ist für eine B2B-Anwendung wie MyRMS weniger als für Consumer-Apps, aber immer noch ein großer Anteil. Insbesondere Projektmanager und Crew-Members im Außeneinsatz nutzen Tablets und Smartphones für schnelle Zugriffe auf Informationen (z.B. „Wo ist Asset XYZ gerade?", „Nächste Aufgabe?").

Die aktuelle AdminLTE-Basis ist zwar responsive (passt sich an Bildschirm-Größe an), aber nicht „mobile-optimized" (nicht für Touch-Interaktion optimiert). Der Unterschied:
- **Responsive**: Flexibles Layout, das auf kleine Screens schrumpft. Aber Buttons könnten immer noch 20x20px sein (unmöglich mit Finger zu treffen).
- **Mobile-Optimized**: Layout UND Interaktion-Design für Touch. Buttons sind 48x48px, Formulare haben große Eingabefelder, Swipe-Gesten werden unterstützt.

Firmen mit mobile-optimierten Websites sehen durchschnittlich einen 67% Anstieg in Conversion-Rate (= schnellere Abschlüsse, weniger Frustration). Für ein RMS bedeutet das: Projektmanager erledigen ihre Aufgaben schneller, weniger Fehler durch schlechte UI auf Mobile.

---

#### A3.1 Responsive Breakpoints Strategy
```
xs: < 576px    (small phone)       - Single column, collapsed nav
sm: 576-768px  (large phone)       - Single column, bottom tab bar
md: 768-992px  (tablet)            - 2 columns, sidebar toggles
lg: 992-1200px (laptop)            - Full sidebar visible
xl: > 1200px   (desktop)           - Optimal spacing
```

**Detaillierte Erklärung - Breakpoints:**

Diese Breakpoints folgen Bootstrap 5 Standard und haben sich seit Jahren als Industrie-Standard bewährt. Sie sind nicht willkürlich gewählt, sondern basieren auf tatsächlichen Bildschirm-Größen:

- **xs (< 576px)**: iPhone SE (375px), iPhone 11 (390px), kleine Android-Phones. Single Column Layout ist notwendig - zwei Spalten würden nur 250px pro Spalte geben, unpraktisch.
- **sm (576-768px)**: Large iPhones (412px), kleinere Tablets im Portrait. Immer noch Single Column für Body, aber eine Bottom-Tab-Bar kann hinzukommen (6 Icon-Tabs nebeneinander passen in 600px).
- **md (768-992px)**: iPad (768px), größere Tablets. 2-Spalten sind sinnvoll: Sidebar (250px) + Content (500px).
- **lg (992-1200px)**: Laptop-Screens, Sidebar ist immer sichtbar.
- **xl (> 1200px)**: Desktop/Monitor. Optimal spacing mit padding, große Schriftgrößen.

Jeder Breakpoint hat korrespondierende CSS-Medienqueries. Beispiel:
```css
/* Mobile first */
.sidebar { display: none; }
.content { width: 100%; }

/* Tablet and up */
@media (min-width: 768px) {
  .sidebar { display: block; width: 250px; }
  .content { width: calc(100% - 250px); }
}

/* Large screens */
@media (min-width: 992px) {
  .sidebar { width: 280px; }
  .content { width: calc(100% - 280px); }
}
```

---

#### A3.2 Mobile Navigation Patterns
- **Header:** Logo + hamburger menu (3-line icon)
- **Bottom Tab Bar (sm/xs only):** Home | Projects | Assets | Customers | Profile
- **Sticky Header:** Title + search + quick actions
- **Collapse/Expand:** Group related fields in accordion (forms)
- **Swipe Gestures:** Swipe left → details sidebar; swipe right → back

**Detaillierte Erklärung - Mobile Navigation Patterns:**

Das **Hamburger-Menü** (☰ Icon) ist der Standard für Mobile-Navigation seit Jahren. Im Header oben-links platziert, beim Klick öffnet sich ein Slide-Out-Menü (Drawer) mit den Navigations-Items. Auf Mobile sollte dieses Menü die volle Höhe einnehmen (Fullscreen oder 90% Breite). Die Animationen sollten schnell sein (<200ms) und Hardware-accelerated (GPU-genutzt) zur Flüssigkeit.

Die **Bottom Tab Bar** ist ein iOS/Android Pattern, der sich auf Mobile-Web bewährt hat. 4-6 Haupt-Bereiche als Tab-Icons unten: Home (Dashboard), Projects, Assets, Customers, Profile. Dies reduziert die Notwendigkeit, das Hamburger-Menü zu öffnen. Jedes Tab-Icon sollte beschriftet sein und aktiv hervorgehoben (Farbe/Unterline). Die Tab-Bar ist sticky, d.h. bleibt beim Scrollen sichtbar.

Die **Sticky Header** bleibt beim Seitenaufwärts-Scrollen an der Spitze sichtbar. Sie enthält:
- Links: Zurück-Button (< Icon)
- Mitte: Seiten-Titel (z.B. „Projects")
- Rechts: Suchfeld oder Quick-Action (+ Icon)

Dies ist kritisch, denn ohne Sticky Header müssen Benutzer nach oben scrollen, um zu navigieren.

**Accordion-Formulare** sind für Mobile essentiell. Statt alle 15 Eingabefelder auf einmal zu zeigen (würde viel Scrolling erfordern), gruppiert man Felder in expandierbare Sections:
```
▼ Projekt-Details (expandiert)
  [Project Name]
  [Customer]
  [Start Date]

► Asset-Zuweisung (kollabiert)

► Kosten & Budgets (kollabiert)
```

Benutzer expandieren nur die Sections, die sie brauchen. Dies reduziert kognitiv Load.

**Swipe-Gesten** sind powerful auf Mobile:
- **Swipe left** (über einem Projekt-Item): Details-Panel kommt von rechts hereingefahren. Zeigt Projekt-Infos, Aktionen.
- **Swipe right**: Geht eine Seite zurück (wie Browser-Back).

Dies basiert auf iOS-Standard und fühlt sich natürlich für Mobile-User an. Implementierung nutzt Hammer.js oder ähnliche Touch-Bibiliothek.

---

#### A3.3 Table Optimization for Mobile
```
Desktop View:
| Asset | Category | Manufacturer | Last Used | Price/Day |
| Lamp | Lighting | ETC | 2026-03-10 | €45 |

Mobile View (Card Layout):
┌─────────────────────┐
│ Lamp                │ (tap for details)
│ Lighting / ETC      │
│ €45/day             │
│ Last: 2026-03-10    │
└─────────────────────┘

Swipe-able actions (left):
← [Edit] [Delete] [Info]
```

**Detaillierte Erklärung - Mobile Tabellen-Optimierung:**

Horizontale Tabellen funktionieren nicht auf Mobile Screens (zu schmal). Die beste Strategie ist, Tabellen in **Card Layouts** umzuwandeln:

Statt eine Tabellenspalte zu scrollen, scrollt der Benutzer vertikal durch Karten. Jede Karte ist ein komplettes Item mit den wichtigsten Infos:
- Titel (z.B. Asset-Name)
- 2-3 wichtigste Attribute (Kategorie, Status, Preis)
- Optional: Thumbnail-Bild

Die Karte ist tappbar (clickable) und öffnet die Detail-Seite. Links der Karte kann swipeig werden, um Aktions-Buttons zu enthüllen (Edit, Delete, Archive, etc.). Dies ist das Standard-Pattern aus Native Mobile Apps.

Auf Desktop (lg+) können Tabellen bleiben, wie sie sind. Die Transformation zu Cards erfolgt per CSS Media Query oder per JavaScript.

Ein wichtiges Detail: Karten sollten Mindesthöhe 64px haben (äquivalent zu 44px Tappable Height + Padding). Kleinere Karten sind unbequem zum Antippen.

---

#### A3.4 Form Handling
- **Large Input Fields:** Minimum 44px height (touch-friendly)
- **Mobile-First Field Stacking:** Single column on xs/sm
- **Autocomplete:** Dropdown search for select fields
- **Modal Forms:** Full-screen on mobile, modal on desktop
- **Progress Indicator:** Show form step (3 of 5) on long forms

**Detaillierte Erklärung - Mobile Formular-Optimierung:**

**Touch Target Size**: Die WCAG-Richtlinie empfiehlt Mindestens 44x44px für interaktive Elemente. Im Kontext von Formularen bedeutet das: Input-Felder sollten 44px hoch sein (plus Padding oben/unten). Dies ist größer als Standard HTML-Input (36px), aber notwendig für Nutzer mit größeren Fingern, älteren Nutzern, etc.

**Single-Column Layout** auf Mobile ist nicht optional - es ist essentiell. Zwei Spalten (nebeneinander) auf 375px Bildschirm-Breite ergibt nur 150px pro Spalte, unmöglich zu bedienen. Formulare sollten auf Mobile immer Single-Column sein, auf Tablet/Desktop können 2-3 Spalten sein.

**Autocomplete** ist ein großes UX-Improvement auf Mobile. Statt ein Dropdown mit 200 Kunden-Namen anzuzeigen (Benutzer müsste Scrollen), wird getippt und die Liste filtert sich. Beispiel:
```
Kunden: [Thea...]
        → Theaterkunstprojekte GmbH
        → Theine Ltd.
        → Theophil Events
```

Dies ist auch zugänglicher für Screenreader.

**Modal Forms** auf Desktop sind normal. Aber auf Mobile sollte ein Formular lieber Fullscreen gehen, statt in einem kleinen Modal-Dialog. Dies gibt mehr Platz und verhindert, dass das Formular kleiner als 375px wird.

**Progress Indicator** für mehrstufige Formulare ist kritisch auf Mobile. Wenn ein Formular 5 Schritte hat, sollte oben gezeigt werden: „Step 3 of 5" mit einem Progress-Bar. Dies gibt Kontext und Motivation (man sieht, wie viel noch zu tun ist).

---

#### A3.5 Mobile-Specific Features
| Feature | Implementation |
|---------|-----------------|
| QR Code Scanner | Camera access + barcode scanning (already PWA) |
| Offline Mode | Service worker caching for read-only views |
| Quick Check-In | One-tap asset check-in from project card |
| Voice Dictation | Dictate notes (using Web Speech API) |
| Geolocation | Map view of asset locations/deliveries |
| Notifications | Push notifications for overdue items, new messages |

**Detaillierte Erklärung - Mobile-Spezifische Features:**

**QR Code Scanner**: MyRMS ist bereits PWA (Progressive Web App), also installierbar auf Homescreen wie native App. Mit Camera-API können QR-Codes gescannt werden. Anwendung: Equipment-Labels haben QR-Codes. Beim Scan wird das Asset automatisch geladen. Dies ersetzt manuelle Text-Eingabe und reduziert Fehler (Mensch tippt falschen Namen ein, QR-Code ist objektiv korrekt).

**Offline Mode**: Service Worker speichert kritische Seiten im Cache (Dashboard, Projekt-Liste, Asset-List). Wenn der Benutzer offline ist (Baustelle ohne Netz), kann er trotzdem diese Read-Only-Seiten ansehen. Änderungen können im localStorage gepuffert und later, wenn Netzwerk zurück ist, synchronisiert werden. Dies ist essentiell für Feldarbeit.

**Quick Check-In**: Ein Button auf der Projekt-Karte ermöglicht Eintipp Bestätigung, dass ein Asset angekommen ist / ausgeliefert wurde. Dies ist schneller als die vollständige Dispatch-Seite zu öffnen. Einer einziger Tap, und ein Timestamp wird gespeichert.

**Voice Dictation**: Web Speech API (verfügbar in Chrome, Edge) ermöglicht es, zu sprechen statt zu tippen. „Add note: Equipment damaged at corner" wird in Text umgewandelt und zum Notiz-Feld hinzugefügt. Dies ist schneller und für Benutzer mit motorischen Beeinträchtigungen wertvoll.

**Geolocation**: Wenn ein Benutzer der App Zugriff auf seinen Ort erlaubt, kann eine Karte zeigen: „Hier bin ich", „Lagerhaus 5km weg", „Kunde-Adresse 2km weg". Dies ist hilfreich für Dispatch-Planung in Echtzeit.

**Push Notifications**: Browser-Benachrichtigungen (über Service Worker) können Events triggern: „Invoice ABC ist überfällig!", „Team-Nachricht von John", „Equipment-Rückgabe in 2 Stunden fällig". Dies erfordert Benutzer-Opt-In und sollte sparsam genutzt werden (nicht zu oft pushen, sonst Fatigue).

---

### A4. Dark Mode & Accessibility
**Effort:** M (2-3 weeks)
**Priority:** MEDIUM
**Impact:** User preference + better accessibility

#### A4.1 Dark Mode Implementation
```
Approach:
1. CSS Variables for theming
2. User preference toggle in header
3. System theme detection (prefers-color-scheme)
4. Persist choice in localStorage + backend user settings
5. Lazy-load dark CSS or use CSS-in-JS

Colors:
Dark BG:     #1a1a1a (almost black, less eye strain)
Dark Surface: #2d2d2d
Text:        #e0e0e0
Accent:      #4a9eff (slightly softer blue)
```

**Detaillierte Erklärung - Dark Mode Implementierung:**

Dark Mode ist nicht nur eine Trend, sondern hat reale Accessibility-Vorteile. Benutzer mit Astigmatismus (ein optischer Defekt, der Diffraktion verursacht) finden Weiß auf Schwarz ermüdend, weil die Überkontrast zu Halo-Effekten führt. Ein dunkler Mode mit grauem Text auf dunklem Hintergrund ist angenehmer.

Die Implementierung nutzt **CSS Custom Properties (Variablen)**, nicht klassisches Stylesheet-Toggle. Beispiel:

```css
:root {
  --bg-primary: #ffffff;
  --bg-secondary: #f5f5f5;
  --text-primary: #000000;
  --text-secondary: #666666;
  --accent-color: #2196f3;
}

@media (prefers-color-scheme: dark) {
  :root {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --accent-color: #4a9eff;
  }
}

body { background-color: var(--bg-primary); color: var(--text-primary); }
```

Der User kann ein Toggle in der Header-Leiste klicken, um Dark Mode manuell zu aktivieren/deaktivieren. Dies überschreibt die System-Einstellung (prefers-color-scheme). Die Wahl wird in localStorage und optional in der Backend-DB gespeichert.

**prefers-color-scheme** ist eine CSS Media Query, die erkennt, ob der Benutzer sein OS (macOS, Windows, Android) auf Dark Mode eingestellt hat. Wenn ja, wird automatisch Dark Mode geladen. Dies ist benutzerfreundlich: Bei Bedarf müssen nicht viele Einstellungen angepasst werden.

**Color-Palette**:
- Dark BG (#1a1a1a): Fast schwarz, aber nicht pur #000000 (das kann zu hart wirken und Flimmern auf old CRT-Monitoren verursachen).
- Dark Surface (#2d2d2d): Für cards/panels, leicht heller als BG für Tiefenwahrnehmung (Elevation).
- Text (#e0e0e0): Nicht pur Weiß (#ffffff), um Glare zu reduzieren. Grau ist augen-schonender.
- Accent (#4a9eff): Nicht zu hart/Blau, etwas gedimmt für dark mode.

Diese Palette basiert auf Material Design 3 Dark Theming Guidelines.

---

#### A4.2 Accessibility Improvements (WCAG 2.1 AA)
| Feature | Details |
|---------|---------|
| Color Contrast | Minimum 4.5:1 for text (check all text colors) |
| Keyboard Navigation | Tab order, skip links, focus indicators |
| ARIA Labels | Add to interactive elements, tables, forms |
| Alt Text | All icons and images get descriptive alt text |
| Screen Reader | Test with NVDA/JAWS, fix semantic HTML |
| Font Sizing | Allow user font size adjustment (min 16px base) |
| Motion | Respect prefers-reduced-motion for animations |
| Focus States | Visible focus ring on all interactive elements |

**Detaillierte Erklärung - WCAG 2.1 AA Conformance:**

WCAG (Web Content Accessibility Guidelines) 2.1 Level AA ist der Branchenstandard und in vielen Ländern rechtlich gefordert (z.B. in EU/Deutschland bei öffentlichen Diensten). MyRMS sollte mindestens AA anstreben.

**Color Contrast (4.5:1 minimum)**: Der Kontrast zwischen Textfarbe und Hintergrundfarbe muss ausreichend sein. Grauer Text (#999999) auf Weiß (#ffffff) könnte nur 3.5:1 Kontrast haben - zu niedrig. Test-Tool: WebAIM Contrast Checker, oder integrier in CI/CD (axe DevTools, Pa11y).

**Keyboard Navigation**: Alle Interaktionen müssen via Keyboard möglich sein. Tab-Taste durchläuft alle Buttons/Links in logischer Reihenfolge. Enter/Space aktiviert sie. Escape schließt Modals. Dies ist essentiell für Benutzer ohne Mouse (Bewegungsbeeinträchtigungen, Voice Control, etc.). Der **tab-order** (welcher Button wird bei jedem Tab angesteuert) muss logisch sein, meist von oben-links nach unten-rechts.

**Skip Links**: Ein unsichtbarer Link oben auf der Seite: „Skip to main content". Keyboard-Nutzer können damit direkt zum Haupt-Inhalt springen, ohne alle Nav-Items durchzutabben.

**ARIA Labels**: ARIA = Accessible Rich Internet Applications. ARIA-Labels addieren beschreibenden Text zu HTML-Elementen, die Screenreader lesen können:
```html
<button aria-label="Close menu">×</button>
<div role="status" aria-live="polite">1 new message</div>
```

**Alt Text**: Alle Bilder und aussagekräftigen Icons brauchen alt-Text. `<img src="logo.png" alt="MyRMS logo">`. Dekorative Icons können `alt=""` haben.

**Screen Reader Testing**: NVDA (für Windows, kostenlos) oder JAWS (kostenpflichtig, aber Standard) sollten getestet werden. Ein Screenreader liest den gesamten Seiten-Inhalt vor, basierend auf HTML-Struktur. Fehlerhafte HTML (z.B. `<div>` statt `<button>`) wird nicht korrekt interpretiert.

**Semantic HTML**: Statt `<div onclick="...">Klick mich</div>` sollte es `<button>Klick mich</button>` sein. Das ist Screenreader-freundlicher und nutzt natürliche Browser-Funktionalität.

**Font Sizing**: Mindestens 16px Base-Font (Mobile Standard). User sollten in den Browser-Einstellungen die Schriftgröße anpassen können (+50%, +200%, etc.). Responsives Design sollte das nicht überirtteln.

**prefers-reduced-motion**: Manche Benutzer (z.B. mit Vestibular-Störungen) werden von Animationen unwohl. Die CSS Media Query `@media (prefers-reduced-motion: reduce)` deaktiviert Animationen:

```css
@media (prefers-reduced-motion: reduce) {
  * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
}
```

**Focus Indicators**: Wenn ein Benutzer mit Tab navigiert, muss sichtbar sein, welches Element gerade fokussiert ist. Modernes Design nutzt einen Farbring oder Outline:
```css
:focus { outline: 3px solid #4a9eff; outline-offset: 2px; }
```

Der Kontrast dieses Focus-Ringes muss mindestens 3:1 betragen (WCAG 2.2 neuer Standard).

---

### A5. Real-Time Collaboration Features
**Effort:** L (4-5 weeks)
**Priority:** MEDIUM
**Impact:** Team coordination

#### A5.1 Live Project Updates
| Feature | Details |
|---------|---------|
| Real-time Dispatch Board | WebSocket updates for asset status changes |
| Comments & Mentions | @mention crew/managers, email notifications |
| Activity Feed | Who did what, when (per project) |
| Live Presence | Avatars showing who's viewing current page |
| Change Notifications | Toast: "John updated equipment list 30s ago" |

**Detaillierte Erklärung - Real-Time Collaboration:**

Real-Time Updates sind das Rückgrat moderner Team-Tools (wie Figma, Google Docs, Slack). Ohne Echtzeit-Sync muss ein Benutzer die Seite neuladen, um zu sehen, dass jemand anderes etwas geändert hat.

**Real-time Dispatch Board**: Das Dispatch-Kanban (not picked → picked → in-use → returned → checked) muss Echtzeit-Updates haben. Wenn John die Status eines Assets auf „in-use" ändert, sehen alle anderen Projektmanager sofort die Änderung, ohne die Seite neuladen zu müssen.

Implementierung nutzt **WebSockets**, nicht HTTP-Polling. WebSocket ist eine bidirektionale Kommunikations-Kanal, die persistent offen bleibt. Server kann aktiv Nachrichten an Clients pushen, statt dass Clients ständig fragen müssen.

Für PHP kann **Ratchet** verwendet werden, eine WebSocket-Bibliothek, die auf **ReactPHP** läuft (non-blocking I/O). Ratchet-Server läuft als Daemon-Prozess (z.B. port 8080), separate vom HTTP-Server:

```bash
# Start WebSocket server
nohup php bin/server.php &

# In Production: use Supervisor for process management
[program:myRMS-ws]
command=php /var/www/myRMS/bin/server.php
autostart=true
autorestart=true
```

Die WebSocket-URLs sollten **wss://** (encrypted) sein, nicht ws:// (plain text), um Sicherheit zu garantieren.

**Comments & Mentions**: Auf jedem Projekt können Benutzer Kommentare hinterlassen. Mit @ können andere Benutzer erwähnt werden: „@John - bitte Equipment-Status prüfen". Ein Notification wird an John gesendet, und eine Email wird versendet. Dies ist ähnlich wie GitHub Issues oder Slack.

**Activity Feed**: Ein Protokoll aller Änderungen auf dem Projekt: „Sarah updated 'Equipment Allocation' 30 seconds ago", „John marked 'Asset #123' as returned 5 minutes ago", etc. Dies ist wertvoll für Audit und um zu sehen, wer was tat.

**Live Presence Avatars**: Wenn Sarah die Projekt-Seite offen hat, wird ihr Avatar (kleiner Kreis mit Initialen) oben-rechts gezeigt: „Sarah is viewing this page". Wenn mehrere Benutzer gleichzeitig die gleiche Seite ansehen, werden ihre Avatare alle gezeigt. Dies reduziert die Chance von Konflikten (zwei Benutzer bearbeiten gleichzeitig das gleiche Asset).

**Change Notifications (Toast)**: Wenn eine Änderung passiert (z.B. jemand einen Invoce markiert als bezahlt), erscheint oben-rechts ein Toast-Popup: „Tom marked Invoice INV-001 as paid". Nach 5 Sekunden verschwindet es. Dies ist nicht-invasiv, aber informativ.

---

#### A5.2 Implementation
- Use WebSocket library (Socket.io or native WS)
- Message queue for offline-first (localStorage buffer)
- Optimistic UI updates + server sync
- Conflict resolution (last-write-wins with merge hints)

**Detaillierte Erklärung - Technische Implementierung Echtzeit:**

**WebSocket vs. Socket.io**: Native WebSockets sind schneller, aber Socket.io bietet Fallbacks (Polling, etc.) wenn WebSocket nicht verfügbar ist. Für MyRMS: Native WebSocket ist ausreichend, einfacher zu debuggen.

**Message Queue / Offline-first**: Wenn der Benutzer offline wird, werden Änderungen (z.B. „Status Change") im localStorage gepuffert. Wenn Netzwerk zurück ist, werden sie an den Server gesendet. Dies ist essentiell für mobile/Feldarbeit.

```javascript
// Client-side
if (navigator.onLine) {
  // Send to server via WebSocket
  ws.send(JSON.stringify({ action: 'updateAssetStatus', assetId: 123, status: 'in-use' }));
} else {
  // Queue in localStorage
  let queue = JSON.parse(localStorage.getItem('pending') || '[]');
  queue.push({ action: 'updateAssetStatus', assetId: 123, status: 'in-use' });
  localStorage.setItem('pending', JSON.stringify(queue));
}

// When online again, flush queue
window.addEventListener('online', () => {
  let queue = JSON.parse(localStorage.getItem('pending') || '[]');
  queue.forEach(msg => ws.send(JSON.stringify(msg)));
  localStorage.setItem('pending', '[]');
});
```

**Optimistic UI Updates**: Wenn ein Benutzer einen Button klickt, zeigt die UI sofort das Ergebnis (z.B. Status wechselt von „picked" zu „in-use"), bevor der Server antwortet. Wenn der Server dann bestätigt (oder ablehnt), wird die UI korrigiert. Dies macht die App schneller, aber erfordert Rollback-Logic.

```javascript
// Optimistic update
currentAsset.status = 'in-use'; // Update UI instantly
renderUI();

// Then send to server
ws.send(JSON.stringify({ action: 'updateStatus', assetId: 123, status: 'in-use' }));
ws.onmessage = (event) => {
  let response = JSON.parse(event.data);
  if (!response.success) {
    // Rollback
    currentAsset.status = 'picked';
    renderUI();
    showError('Failed to update status, please retry');
  }
};
```

**Conflict Resolution**: Wenn zwei Benutzer gleichzeitig das gleiche Asset editieren, wer gewinnt? Standard-Strategie ist „Last-Write-Wins" (letzte Änderung überschreibt frühere). Allerdings kann dies zu Datenverlust führen. Besser: **Merge Hints** zeigen: „John changed Status to 'returned', you changed Status to 'in-repair'. Which should win?" Ein Konflikt-Dialog lässt den Benutzer entscheiden.

---

### A6. Search & Filter Improvements
**Effort:** M (3 weeks)
**Priority:** HIGH
**Impact:** Faster data discovery

#### A6.1 Advanced Filtering (All List Views)
```
Example: Assets list
┌─────────────────────────────────────┐
│ Search: [Lamp]                      │
└─────────────────────────────────────┘

Filters (collapsible):
  ☐ Category:    [Lighting ▼]
  ☐ Manufacturer: [ETC ▼] [LED-Lenser ▼]
  ☐ Status:      [In Stock ▼] [In Use ▼] [Maintenance ▼]
  ☐ Price Range: [€ 0 – 10000 ▬]
  ☐ Last Used:   [Last 30 days ▼]
  ☐ Condition:   [Good ▼] [Fair ▼]

[🔗 Save as View] [🗑 Clear All]
```

**Detaillierte Erklärung - Erweiterte Filter:**

Fortgeschrittene Filterung ist essentiell für Listen mit hunderten oder tausenden Items. Benutzer müssen schnell Subsets finden (z.B. „alle Lampen, Status = In Stock, Preis < €500"). Die Implementierung nutzt ein **Collapsible Filters Panel** (auf Mobile kann es ein Drawer sein, auf Desktop eine Sidebar).

Jeder Filter ist interaktiv:
- **Category Dropdown**: Multiple Selection möglich (z.B. „Lighting" UND „Support Equipment"). Mit Checkboxes statt Dropdown ist es intuitiver.
- **Price Range**: Ein Range-Slider mit Min/Max. Benutzer kann von 0 bis 10.000 Euro ziehen.
- **Date Range**: Datum-Picker oder Shortcuts („Last 7 days", „Last 30 days", „Last Quarter").
- **Condition Radio Buttons**: Single-Choice (nur einer kann aktiv sein).

Der **Filter-Status** muss persistent sein: Wenn ein Benutzer Filter setzt und dann zur Projekt-Seite navigiert, beim Zurück zur Asset-Seite sollten die Filter noch aktiv sein. Dies speichert man in:
- URL Query Parameters: `/assets?category=Lighting&status=in-stock` (gut für Sharing)
- localStorage: Temporär für Session
- Backend User Preferences: Permanent über Account-Einstellungen

Die **"Save as View"** Button speichert den aktuellen Filter-State als benannte View (z.B. „My High-Value Assets"). Diese wird wie ein Lesezeichen behandelt und kann später schnell geladen werden.

Der **"Clear All"** Button setzt alle Filter zurück auf Default.

---

#### A6.2 Saved Views/Filters
- **Personal Filters:** "My Overdue Invoices", "High-Value Assets"
- **Team Filters:** Shared by role (PMs see "My Projects")
- **Smart Filters:** Auto-generated based on usage patterns
- **Quick Presets:** Top 3 filters as buttons (1-click)

**Detaillierte Erklärung - Gespeicherte Views:**

Gespeicherte Views sind Lesezeichen für häufig verwendete Filter-Kombinationen. Beispiel:

**Personal Filters** speichert pro Benutzer:
- „My Overdue Invoices": Alle Invoices mit Status „Unpaid" und Due Date < Today
- „High-Value Assets": Assets mit Anschaffungspreis > €10.000
- „My Projects": Nur Projekte, bei denen ich zugeordnet bin

Diese erscheinen in einer Dropdown oder Links-Leiste zum schnellen Zugriff.

**Team Filters** sind Shared Views pro Rolle:
- Project Managers sehen automatisch ein Filter „My Projects" und „My Team's Availability"
- Finance sehen „Overdue Invoices", „Revenue This Month"
- Inventory Manager sehen „Low Stock Items", „Equipment Needing Service"

Diese werden vom Admin konfiguriert und sind read-only für Team-Mitglieder.

**Smart Filters** sind ML-basiert: Wenn ein Benutzer über mehrere Wochen hinweg immer wieder „Assets in Maintenance" + „Status = Warranty Claim" filtert, könnte das System einen Smart Filter vorschlagen: „Warranty Claims in Maintenance". Dies ist proaktiv und erleichtert Workflows.

**Quick Presets** sind die Top-3 Filter für die aktuelle Rolle, als große Buttons oben in der Liste:
```
[🔴 Overdue Invoices (5)]  [🟡 In Maintenance (12)]  [🟢 Available Assets (234)]
```

Dieser Button zeigt auch die Anzahl der Ergebnisse, sodass Benutzer wissen, ob es was zu bearbeiten gibt.

---

#### A6.3 Full-Text Search Engine Upgrade
- Current: MySQL LIKE (slow on large tables)
- Proposed: Elasticsearch or Meilisearch
- Benefit: Typo-tolerance, relevance ranking, faceted search
- Fallback: MySQL fulltext index (interim)

**Detaillierte Erklärung - Such-Engine Upgrade:**

Aktuelle MySQL LIKE-Suche wird langsam mit großen Tabellen:
```sql
SELECT * FROM assets WHERE name LIKE '%lamp%' -- Linear Scan, langsam!
```

Bei 100.000+ Assets dauert eine Suche mehrere Sekunden. Eine spezialisierte Such-Engine ist notwendig.

**Meilisearch** (empfohlen):
- **Geschwindigkeit**: <50ms auch auf großen Datenmengen
- **Typo-Tolerant**: Suche nach „Lampe" findet auch „Lamp"
- **Relevance Ranking**: Bessere Matches erscheinen zuerst
- **Faceted Search**: Nach Kategorie, Hersteller, Status filtern
- **Phrase Search**: Exakte Phrase mit Anführungszeichen
- **Setup**: Einfach selbst-hosted (Docker) oder Cloud (€25-100/Monat)
- **PHP Integration**: Offizielle SDK vorhanden

Meilisearch ist ideal für MyRMS, da es einfach zu bedienen ist und nicht viel DevOps-Wissen braucht.

**Elasticsearch** (Alternative):
- Mächtig, aber komplex
- Für sehr große Systeme (100M+ Dokumente)
- Höhere Infrastructure-Kosten
- Größere Learning Curve

**Fallback - MySQL Fulltext**: Ein FULLTEXT Index in MySQL ist besser als LIKE, aber nicht perfekt:
```sql
CREATE FULLTEXT INDEX idx_assets_ft ON assets(name, description);

SELECT * FROM assets WHERE MATCH(name, description) AGAINST ('lamp' IN NATURAL LANGUAGE MODE);
```

Dies ist schneller als LIKE, aber keine Typo-Toleranz und Relevance-Ranking ist begrenzt. Gut als interim Lösung vor Meilisearch-Implementierung.

---

### A7. Data Visualization & Charts
**Effort:** M (2-3 weeks)
**Priority:** MEDIUM
**Impact:** Better financial insights

#### A7.1 Chart Library Migration
```
Current: Inline canvas/SVG generation
Proposed:
- Main: Chart.js (lightweight, widely supported)
- Complex: Recharts (React alternative) OR ECharts (comprehensive)
- Maps: Leaflet (for location-based assets)
```

**Detaillierte Erklärung - Chart-Bibliothek Wahl:**

Die aktuelle Inline-Canvas-Generierung ist Error-Prone (Code ist über mehrere Controllers verteilt) und nicht wartbar. Eine dedizierte Chart-Bibliothek zentralisiert die Logik.

**Chart.js** (hauptsächlich):
- 60 KB, leicht
- Großes Ecosystem (Plugins)
- Browser-Support: IE 11+ (oder >95% moderne Browser)
- Dokumentation: Ausgezeichnet
- Typen: Line, Bar, Pie, Doughnut, Radar, Bubble, Scatter

**Recharts** (wenn React verfügbar):
- React-Component-basiert
- Wenn Frontend in React rewritten, dann natürlich

**ECharts** (für sehr komplexe Visualisierungen):
- 500+ KB, aber mächtig
- Sunburst, Sankey, Treemap Charts
- Nur wenn Recharts/Chart.js nicht reichen

Empfehlug: Chart.js als Haupt-Option, mit ECharts für spezielle Reports.

---

#### A7.2 Chart Implementations
| Chart | Data | Use Case |
|-------|------|----------|
| Revenue Trend | Daily/Weekly/Monthly | Executive dashboard, forecasting |
| Asset Utilization | % per asset type | Identify slow-moving inventory |
| Customer Revenue | Top 10 customers | A/B customer profitability |
| Profitability | By category, project, time | Financial analysis |
| Aging A/R | Days overdue buckets | Collection prioritization |
| Seasonal Demand | Month-over-month | Forecasting, inventory planning |
| Equipment ROI | Cost vs. cumulative rental revenue | Asset lifecycle decisions |

**Detaillierte Erklärung - Spezifische Chart-Implementierungen:**

**Revenue Trend Line Chart**: Die X-Achse ist Zeit (täglich, wöchentlich, monatlich). Y-Achse ist Umsatz in Euro. Eine Linie zeigt tatsächliche Revenue, eine gestrichelte Linie zeigt Forecast (mit ML-Modell). Dies ist das zentrale Financial-Dashboard-Chart.

```javascript
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: ['Jan', 'Feb', 'Mar', ...],
    datasets: [
      { label: 'Actual Revenue', data: [50000, 52000, ...], borderColor: 'green' },
      { label: 'Forecast', data: [52000, 54000, ...], borderColor: 'blue', borderDash: [5, 5] }
    ]
  }
});
```

**Asset Utilization Bar Chart**: Horizontal Bar Chart, zeigt Prozentsatz-Auslastung für jede Asset-Kategorie (Kameras: 85%, Beleuchtung: 72%, Support: 45%). Langsamverkäufliche Categories haben niedrige Prozentsätze und sind leicht erkannt.

**Customer Revenue Pie/Doughnut Chart**: Top-10 Kunden als Segmente. Größtes Segment = Umsatz-Führer. Hilfreich um zu sehen, ob der Umsatz zu konzentriert ist auf wenige Kunden (Risiko).

**Profitability Stacked Bar Chart**: Mehrere Kategorien (z.B. Kamera, Beleuchtung, Support) als vertikale Bars, gestapelt. Jedes Segment zeigt die Marge (Profit) dieser Kategorie. Längere Bars = höhere Profitabilität.

**Aging A/R Pyramid Chart**: Ein spezialisierter Chart, zeigt Pyramid-Form mit vier Segmenten:
- 0-30 days: Oben, grün, schmal
- 30-60 days: Gelb, mittel
- 60-90 days: Orange, breiter
- 90+ days: Rot, unten, breit (oder hoffentlich schmal!)

Chart.js hat keinen built-in Pyramid, aber mit SVG/Custom kann man ihn zeichnen oder ein Bar-Chart mit custom styling nutzen.

**Seasonal Demand Line/Column Chart**: Zeigt Nachfrage-Muster über Monate/Jahreszeiten. Wenn z.B. im Dezember Nachfrage für Event-Equipment steigt, wird die Linie spitz nach oben gehen. Hilfreich für Inventaris-Planung.

**Equipment ROI Scatter Plot**: X-Achse = Anschaffungskosten, Y-Achse = Kumulativer Rental Revenue seit Kauf. Jeder Punkt ist ein Asset. Assets oben-links sind ROI-erfolgreich (hohe Revenue, niedrig Kosten). Assets unten-rechts sind problematisch (hohe Kosten, niedrig Revenue) und sollten ausgemustert werden.

---

**Gesamt-Zusammenfassung Part A:**

Das erweiterte Part A konzentriert sich auf benutzerfreundliche Verbesserungen, die Effizienz und Zufriedenheit steigern. Die Dashboards bieten prägnante KPI-Übersichten für verschiedene Rollen (Executive, Project Manager, Finance, Inventory). Navigation wird flacher und intuitiver mit besserer Struktur und Breadcrumbs. Mobile-Optimierung ist nicht optional, sondern essentiell, da über 60% der Nutzer mobil zugreifen. Dark Mode und Accessibility (WCAG 2.1 AA) sorgen für inklusive User Experience. Echtzeit-Collaboration via WebSocket reduziert Redundanz und Konflikte. Erweiterte Suche und Filterung ermöglichen schnelle Daten-Discovery. Und moderne Chart-Visualisierungen machen Daten verständlich.

Alle Implementierungen folgen aktuellen Best-Practices (2025-2026) und sind technologisch fundiert.
## Part B: Feature Extensions – Erweiterte Funktionalität für MyRMS

---

## B1. Reporting & Analytics Module
**Aufwand:** L (5-6 Wochen)
**Priorität:** HOCH
**Geschäftlicher Nutzen:** Bessere Entscheidungsgrundlagen durch datengesteuerte Analysen

### Aktueller Status
Das System verfügt bereits über grundlegende Berichtsfunktionen wie Gewinn-und-Verlust-Übersichten, Auslastungsanalysen und Exporte für die Elsterung Umsatzsteuer (EUER). Allerdings sind diese Funktionen statischer Natur und bieten keine Möglichkeit zur automatisierten Berichterstellung, zum zeitgesteuerten Versand oder zur dynamischen Darstellung von Kennzahlen. Die Branche der Ausrüstungsvermietung wandelt sich zunehmend zu datengetriebenen Geschäftsmodellen, in denen IoT-Sensoren, Vorhersageanalysen und Live-Bestandsverfolgung zu Wettbewerbsvorteil führen. Organisationen, die diese Technologien implementieren, sehen typischerweise 20–30 % Effizienzsteigerungen und bessere Ressourcenallokation. MyRMS muss daher eine umfassende Reporting-Plattform bereitstellen, um mit modernen Anforderungen Schritt zu halten.

#### B1.1 Reporting-Bibliothek mit vordefinierten Templates

Die Basis eines leistungsstarken Analytics-Systems ist eine umfangreiche Bibliothek vordefinierter Berichte, die geschäftliche Anforderungen abdecken. Das System wird drei Kategorien von Standardberichten anbieten:

**Finanzielle Berichte** sollen Führungskräften vierteljährliche und jährliche Geschäftsleistungen visualisieren. Der Gewinn-und-Verlust-Bericht erfasst Einnahmen, direkte Kosten (Personal, Wartung) und indirekte Kosten (Verwaltung, Vertrieb), gebrochen nach Monaten oder Quartalen. Die Cash-Flow-Prognose nutzt historische Zahlungsmuster und Vertragslaufzeiten, um 30-, 60- und 90-Tage-Prognosen zu erstellen – entscheidend für Liquiditätsplanung. Das Aging-A/R-Report (Accounts Receivable) segmentiert offene Rechnungen nach Überfälligkeitsstufen (30, 60, 90+ Tage) und identifiziert Kunden mit chronischen Zahlungsproblemen. Die Kundenrentabilitätsanalyse zeigt, welche Top-20-Kunden (nach Gewinnmarge) das Geschäft treiben, und offenbart unprofitable Accounts, die möglicherweise die Strategie nicht rechtfertigen. Steuerberichte (EÜR/EUER) werden vollständig formatiert und können direkt in Steuererklärungen übernommen werden. Das Dunning-Status-Report visualisiert den Inkassoverlauf nach Stufen (Mahnung 1–3, Inkassoservice), um zu priorisieren, welche Konten sofort Aufmerksamkeit brauchen.

**Operationale Berichte** richten sich an Flottenmanager und Operationsleiter. Das Asset-Auslastungs-Report zeigt für jede Ausrüstung die prozentuale Auslastung, durchschnittliche Mietdauer, Ausfallzeiten und Return on Investment (ROI) pro Artikel – kritisch zur Identifikation von Investitionsfehlentscheidungen. Das Projekt-Pipeline-Report segmentiert laufende Projekte nach Phasen (Anfrage, Angebot, Vertrag, Aktiv, Abgeschlossen) und schätzt Umsatzpotenziale, um Sales-Ziele zu verfeinern. Das Crew-Auslastungs-Report dokumentiert, wie viel Zeit Techniker und Fahrer tatsächlich produktive Stunden leisten versus Leerlauf, und offenbart Überbesetzung oder Engpässe. Das Instandhaltungs-Schedule-Report bilanziert geplante, überfällige und abgeschlossene Wartungen, um proaktive Flottengesundheit zu gewährleisten. Das Bestands-Aging-Report kategorisiert Assets nach Umschlaggeschwindigkeit (Fast Movers, Slow Movers, End-of-Life), um Lagerverwertungs- und Desinvestitionsentscheidungen zu treffen. Das Transporteffizienz-Report berechnet Kosten pro Lieferung, optimale Routen und fahrerbezogene Leistung.

**Compliance-Berichte** adressieren regulatorische Anforderungen, insbesondere für das deutschsprachige Markt. Der DSGVO-Jahresbericht dokumentiert Datenspeicherung, Verarbeitungsgrundsätze und Betroffenenrechte, um für Audits bereit zu sein. Der GoBD-Dokumentationsbericht zeigt Revisionssicherheit (unveränderliche Audit-Trails, Aufbewahrungszeiträume, Systemkonsistenz), essentiell für deutsche Finanzamtsprüfungen. Das SEPA-Mandats-Report listet alle Lastschrift-Mandate (aktiv, widerrufen, ablaufend) auf, um Compliance mit SEPA-Verordnungen zu beweisen. Das Finanzausweisberichts-Export ermöglicht es Rechnungsprüfern, detaillierte Journal-Einträge und Kontenabstimmungen zu prüfen.

#### B1.2 Berichts-Customization und interaktive Analyse

Die bloße Bereitstellung vordefinierter Berichte reicht nicht aus; Geschäftsnutzer müssen Berichte ihren spezifischen Anforderungen anpassen können. Ein Drag-and-Drop-Report-Builder (ähnlich wie in Power BI oder Tableau) ermöglicht es Administratoren, Kennzahlen (Umsatz, Margen, Auslastung), Dimensionen (nach Kunde, Projekt, Datum) und Filter (Datumsbereich, Asset-Typ) ohne Programmieraufwand zu kombinieren. Die Drill-Down-Funktion erlaubt es Benutzern, auf eine Zahl zu klicken (z. B. Gesamtumsatz 50.000 €) und sofort die zugrundeliegenden Daten zu sehen (Umsatz pro Kunde, pro Projekt, pro Monat). Vergleichsfunktionen (Jahr-über-Jahr, Monat-über-Monat, Budget-versus-Ist) ermöglichen es, Abweichungen schnell zu identifizieren und Trends zu erkennen. Anmerkungen auf spezifischen Datenpunkten erlauben es Managern, kontextuelle Notizen zu erfassen (z. B. „Umsatzrückgang in Q2 wegen Winterpause Großkunde X"), was Geschäftslogik dokumentiert. Die Sharing- und Export-Funktionen (PDF mit Branding, interaktive HTML-Dashboards, Excel mit Pivot-Tabellen) ermöglichen es, Berichte mit Stakeholdern zu verteilen und in andere Tools zu integrieren.

Die Implementierung erfordert ein ausgefeiltes Frontend-UI (React oder Vue mit einer Charting-Bibliothek wie Chart.js oder D3.js) und ein flexibles Backend-Datenmodell, das schnelle Abfragen auf großen Datensätzen unterstützt. Eine tabellarische Vorausberechnung von häufigen Aggregationen (z. B. tägliche Umsätze nach Kunde) über ein ETL-System (z. B. nächtliche Batch-Jobs) kann die Query-Performance auf akzeptable Niveaus halten, ohne dass SQL-Queries für jeden Benutzereintrag neu kompiliert werden müssen.

#### B1.3 Berichts-Automatisierung und zeitgesteuerte Verteilung

Ein kritischer Anwendungsfall für Führungskräfte ist die automatisierte Berichterstellung nach einem Zeitplan. Das System wird es ermöglichen, tägliche Flash-Reports (3 wichtigste KPIs: Tagesumsatz, offene Rechnungen, offene Aufgaben) zu versenden, wöchentliche Zusammenfassungen (Wocheneinnahmen, Neu-Kunden, Top-Projekte), monatliche Tiefanalysen (kompletter P&L, Projekt-Pipeline, Auslastungstrends) und benutzerdefinierte Berichte nach beliebigen Intervallen. Jeder Bericht kann in mehreren Formaten exportiert werden: PDF (druckbar, mit Firmenlogo und -farben gebrandetet), Excel (mit Pivot-Tables und Makros für Finanzanalyse), HTML (interaktive Dashboards mit Filter), oder CSV (für Import in externe Accounting-Systeme wie Lexware oder SevDesk).

Die technische Implementierung verwendet ein CRON-basiertes Scheduler-System (ähnlich wie in Laravel oder Symfony). Der Workflow ist: (1) Administratoren konfigurieren Report-Vorlage, Zeitplan und Empfängerliste im UI, (2) Ein Background-Job (via PHP CLI oder async queue wie RabbitMQ) wird zu festgelegter Zeit ausgelöst, (3) Der Job liest aktuelle Daten aus der Datenbank, generiert Berichte mittels des Reporting-Moduls, exportiert in das konfigurierte Format, und (4) verschickt PDF/Excel per E-Mail oder lädt in S3 für Portale hoch. Kritisch ist eine Fehlerbehandlung – falls ein Report-Job fehlschlägt (z. B. Datenbankverbindungsproblem), sollte das System benachrichtigen und einen Wiederversuch mit exponentieller Backoff-Verzögerung durchführen.

#### B1.4 Vorhersageanalysen mit KI-Unterstützung

Die oberste Ebene der Reporting-Modernisierung ist die Integrierung von Machine-Learning-Modellen zur Vorhersage zukünftiger Trends. Diese Prädiktiven-Analityik-Features bauen auf historischen Daten auf und helfen Managern, proaktiv zu planen:

**Umsatzprognose:** Ein zeitreihen-basiertes ML-Modell (z. B. Prophet von Facebook oder ARIMA) analysiert historische Umsätze, saisonale Muster (höherer Umsatz im Sommer/Weihnachten), Wochentags-Effekte und Trend-Komponenten. Das Modell erstellt 3-Monats-Prognosen mit Konfidenzintervallen, um Managern zu helfen, Personal- und Lagerbestände zu planen. **Kundenabwanderungsrisiko:** Ein Klassifikationsmodell (Logistic Regression, Random Forest) identifiziert Kunden mit hohem Abwanderungsrisiko durch Analyse von Merkmalen wie Rentabilität, Buchungshäufigkeit, NPS-Score und Zahlungshistorie. Das System kann dann automatisch Kampagnen auslösen (z. B. Rabatt-Angebot) zur Bindung dieser Kunden. **Optimale Preisvorschläge:** Ein Regressions- oder Neuronales-Netz-Modell analysiert Nachfrage-Elastizität (wie Preisänderungen die Buchungen beeinflussen), Wettbewerbspreise und Lagerverfügbarkeit, um optimale Mietsätze zu suggerieren, die Gewinn maximieren. Dieses Feature ist besonders wertvoll in Märkten mit hohem Wettbewerb. **Ausrüstungs-Auslastungsprognose:** Das System kann vorhersagen, welche Assets in zukünftigen Wochen hohe Nachfrage sehen werden, sodass Wartung außerhalb dieser Fenster geplant werden kann. **Wartungs-Vorhersage:** Ein Anomalie-Erkennungs-Modell (z. B. Isolation Forest) analysiert IoT-Sensordaten (Temperatur, Betriebsstunden, Vibration) und historische Wartungsakten, um Assets zu identifizieren, die wahrscheinlich zeitnah ausfallen werden. Proaktive Wartung reduziert ungeplante Ausfallzeiten und Reparaturkosten.

Die Implementierung dieser ML-Features erfordert Ingenieur-Ressourcen für Datenaufbereitung (Feature Engineering, Datenbereinigung) und Modelltraining (möglicherweise externe Data-Science-Expertise oder ein Tool wie Azure ML oder AWS SageMaker). Nach dem initialen Setup sind die Lauf- und Hosting-Kosten moderat (unter 100 € monatlich für kleinere Datensätze). Das System muss Modelle regelmäßig retrainieren (z. B. monatlich), um sich an neue Daten anzupassen.

---

## B2. Erweiterte Berichterstattung: Export & Vertrieb
**Aufwand:** M (2-3 Wochen)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** Automatisierte Workflows und nahtlose Systemintegration

### B2.1 Multi-Format Export und technische Anforderungen

Ein robustes Reporting-System muss Berichte in einer Vielzahl von Formaten exportieren können, die sich für unterschiedliche Anwendungsfälle eignen. Das System wird folgende Export-Kanäle unterstützen:

**PDF** ist das Standardformat für Kundenkommunikation, Rechnungsversand und Archivierung. MyRMS wird die bereits integrierte dompdf-Bibliothek nutzen, um dynamisch gestaltete PDF-Berichte zu generieren. Kritisch ist, dass die PDF mit Unternehmenslogo, Farben und strukturiertem Layout (Headers, Footers mit Seitenzahlen, Tabellen mit durchlaufender Formatierung) gebrandetet sein muss. PDFs sollten auch durchsuchbar und kopierbar sein, nicht nur gescannte Bilder.

**Excel-Export** über die PhpSpreadsheet-Bibliothek (Nachfolger von PHPExcel) wird Finanzanalysten die Möglichkeit geben, Daten in Pivot-Tabellen, Formeln und Diagrammen weiterzuverarbeiten. Das System kann Multi-Sheet-Workbooks generieren (z. B. Sheet 1 = Transaktionen, Sheet 2 = Zusammenfassung, Sheet 3 = Trends mit Charts). Conditional Formatting (Rot für negative Werte, Grün für positive) macht Trends auf den ersten Blick erkennbar.

**CSV-Export** in UTF-8-Codierung ist erforderlich, um Daten in externe Accounting-Systeme (DATEV, Lexware, SevDesk) zu importieren. Das Format muss präzise Spalten-Mappings einhalten (z. B. Konto, Datum, Betrag für GL-Exporte) und keine Excel-spezifischen Datentypen enthalten, die zu Parsing-Fehlern führen.

**JSON-Export** ist für API-Konsumenten und programmatische Integrationen notwendig. Das Schema sollte dokumentiert sein (z. B. OpenAPI-Definition in B5), damit Partner-Systeme den Export verarbeiten können.

**XML-Export** für das deutsche DATEV-Format und das ZUGFeRD-Standard ist entscheidend für Buchhaltungskonformität. Das System nutzt bereits ein ZugferdService-Modell; diese Erweiterung würde Berichte (nicht nur Rechnungen) in ZUGFeRD exportierbar machen.

**Power BI Integration** ermöglicht es, MyRMS-Daten direkt in Microsoft Power BI zu visualisieren. Dies ist besonders wertvoll für größere Unternehmen, die bereits in Power BI investiert haben. Der Mechanismus ist einfach: Berichte als CSV exportieren, per API in ein Power BI-Dataset laden (über Power BI REST API), und visuelle Dashboards werden automatisch aktualisiert.

Die Implementierung erfordert abstracte Export-Handler (einen für jedes Format), die ein gemeinsames Interface implementieren. Ein Builder-Pattern erlaubt es, verschiedene Berichte in verschiedene Formate zu serialisieren, ohne Code-Duplikation.

### B2.2 E-Mail-Verteilung mit Scheduling und bedingtem Versand

Automatisierter E-Mail-Versand ist kritisch für Geschäftseffizienz. Das System wird einem Administrator erlauben, einen Bericht zu konfigurieren, ein Versandszenario (täglich, wöchentlich, monatlich, ad-hoc) festzulegen, Empfänger (Einzelpersonen, Verteillisten) auszuwählen, und einen Zeitpunkt anzugeben. Ein CRON-basiertes Scheduler-System (z. B. via Laravel Jobs oder Symfony Messenger) wird den Job zur geplanten Zeit ausführen.

Ein fortgeschrittenes Feature ist der **bedingte Versand**: Der Administrator kann Bedingungen definieren (z. B. „Versende Report nur, wenn offene A/R > 5.000 €"), um Spam zu vermeiden und sicherzustellen, dass Berichte nur relevant sind. Das System sollte auch **Vorschau-Funktionen** bieten: Der Nutzer sieht genau, wie der Bericht aussehen wird, bevor er versendet wird.

**Branding und Template-Anpassung** sind essentiell: Das System sollte es erlauben, E-Mail-Body (Text oder HTML) zu customizen (z. B. Grußformeln, Geschäftslogik-Kontext hinzufügen), Logo und Farben einzubinden, und Footer mit Kontaktinformationen zu versehen. Eine Bibliothek von E-Mail-Templates (z. B. Handlebars oder Twig) ermöglicht es, E-Mails dynamisch zu generieren, mit Platzhaltern wie {{reportDate}}, {{companyName}}, {{topMetric}}.

Die Implementierung nutzt einen E-Mail-Service wie Mailgun, SendGrid oder Amazon SES, der hohen Versandvolumen und Deliverability-Tracking (Bounces, Opens, Clicks) verwaltet. Eine Audit-Tabelle (history der versendeten Reports) dokumentiert, wann Reports versendet wurden, an wen, und ob erfolgreich.

### B2.3 Report-Archiv und Audit-Trail mit DSGVO-Konformität

Regulatorisch ist es erforderlich, dass Berichte archiviert und nachvollziehbar gemacht werden können. Das System wird einen zentralen **Report-Archiv** in S3 (oder äquivalent: Minio für On-Premise) implementieren, wo alle generierten Berichte mit Metadaten (Generator-Benutzer, Generierungszeit, Parameter) gespeichert werden.

Ein **Audit-Trail** dokumentiert: (1) Wer einen Bericht generiert hat, (2) Zu welcher Zeit, (3) Mit welchen Parametern (z. B. Datumsbereich, Filter), (4) Welches Format, (5) An wen versendet. Diese Informationen sind entscheidend für interne Governance (wer hat welche Daten gesehen?) und externe Prüfungen.

**DSGVO-Konformität** erfordert, dass personenbezogene Daten in Berichten nicht unbegrenzt archiviert werden. Das System wird ein Löschungsrichtlinien-System implementieren: (1) Berichte älter als 7 Jahre werden automatisch gelöscht (Aufbewahrungsfristen für Geschäftsunterlagen in Deutschland). (2) Wenn ein Kunde ein Recht auf Löschung einfordert, wird das System alle Berichte, die ihre Daten enthalten, anonymisieren oder löschen. (3) Ein Compliance-Report dokumentiert alle Löschungsoperationen für Datenschutzbehörden.

Ein fortgeschrittenes Feature ist die **digitale Signatur** von PDF-Berichten: Das System kann Berichte mit einem Zertifikat (z. B. DigiCert oder eSign) signieren, um Authentizität und Manipulationssicherheit zu beweisen. Dies ist für Finanzberichte, die in Prüfungen verwendet werden, entscheidend.

---

## B3. Echtzeit-Features auf Basis von WebSocket
**Aufwand:** XL (6-8 Wochen)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** Moderne UX, verbesserte Team-Koordination

### B3.1 WebSocket-Server-Architektur und Messaging

Moderne Webanwendungen erfordern Echtzeit-Updates ohne konstantes Page-Refresh. Das System wird eine WebSocket-basierte Architektur implementieren, um Nachrichten zwischen Server und Clients bidirektional zu übertragen. Die Technologie-Auswahl:

**Backend:** Ratchet (eine PHP WebSocket-Bibliothek) ist die PHP-native Option und erfordert weniger Overhead als ein separater Node.js-Prozess. Alternativ kann Socket.io (Node.js) eingesetzt werden, wenn hohe Parallelität erforderlich ist (>10.000 gleichzeitige Verbindungen). Für ein System wie MyRMS (typisch 50–500 gleichzeitige Nutzer) ist Ratchet ausreichend.

**Message Queue:** Redis wird als Nachrichtenpuffer genutzt, um sicherzustellen, dass Nachrichten auch bei kurzzeitigen Verbindungsverlusten nicht verloren gehen. Der Workflow ist: (1) Ein Event tritt auf (z. B. Asset-Status ändert sich), (2) Der Backend-Prozess publiziert die Nachricht zu Redis, (3) Der WebSocket-Server abonniert den Redis-Channel und leitet die Nachricht an verbundene Clients weiter.

**Client-Side:** Das System nutzt die native WebSocket-API (kein Socket.io-Library-Overhead), um Nachrichten vom Server zu empfangen und zu verarbeiten. Das Frontend kann Nachrichten mit Retry-Logik senden.

**Message-Format:** JSON mit Versionierung (`{ "version": "1.0", "type": "asset_update", "data": {...} }`), um zukünftige Rückwärts-Kompatibilität zu gewährleisten.

Die wichtigsten **Nachrichtentypen** sind:

- **presence:** User loggt sich an/ab → Update in der Online-User-Liste
- **asset_update:** Asset-Status ändert sich (In Use → Returned) → Live-Dispatch-Board wird aktualisiert
- **project_update:** Projektphase oder Completion-% ändert sich → Dashboard wird aktualisiert
- **comment:** Neuer Kommentar auf Projekt → Team wird benachrichtigt
- **notification:** System-Alert (z. B. Überfällige Rechnung) → Toast-Benachrichtigung
- **typing:** User tippt in ein Feld → „User ist am Schreiben..." Indikator
- **sync:** Vollständiger State-Sync nach Wiederverbindung (falls Client-Cache veraltet ist)

### B3.2 Anwendungsfälle und Geschäftswert

Die WebSocket-Infrastruktur ermöglicht mehrere hochwertige Anwendungsfälle:

**Live-Dispatch-Board:** Einsatzleiter sehen, wenn Assets zurück sind, ohne die Seite zu aktualisieren. Dies reduziert Bearbeitungszeit um 10–20 % und verbessert die Ressourcenauslastung.

**Echtzeit-Kommentare:** Teams können auf Projekte kommentieren, und andere Team-Mitglieder sehen neue Kommentare sofort – ohne Chat-App (Slack) zu verlassen. Das reduziert Kontext-Wechsel und verbessert die Zusammenarbeit.

**Benachrichtigungen:** Das System sendet Toast-Benachrichtigungen (z. B. „Invoice XYZ überfällig in 2 Tagen"), um Manager proaktiv zu warnen. Dies verbessert Zahlungsmoralität und Forderungsmanagement.

**Präsenz-Bewusstsein:** Teams sehen, wer online ist, und können gezielt Fragen stellen oder Meetings planen, ohne E-Mail-Ping-Pong.

**Kollaboratives Editieren:** Zwei Administratoren bearbeiten ein Angebot gleichzeitig → Sie sehen gegenseitig Cursor-Positionen und können Bearbeitungen Echtzeit-synchron sehen (ähnlich wie Google Docs).

**Live-Chat:** Support-Team kann mit Kunden chatten (oder mit Feldtechnikern), mit Lese-Quittungen (Read Receipts) und Typing-Indikatoren.

Diese Features erhöhen die User Experience deutlich und machen das System konkurrenzfähig mit modernen SaaS-Lösungen.

### B3.3 Fallback-Strategie und Robustheit

WebSockets können fehlschlagen (Browser-Inkompatibilität, Firewall-Blocking, Proxies, die WebSockets nicht unterstützen). Das System implementiert gestaffelte Fallbacks:

1. **WebSocket erfolgreich:** Verwendung der Full-Duplex-Kommunikation
2. **WebSocket fehlgeschlagen, nutze Polling:** Der Client sendet alle 60 Sekunden eine GET-Request, um neue Nachrichten abzurufen. Polling ist weniger effizient, aber funktioniert überall.
3. **Offline-Modus:** Mit Service Workers können GET-Requests gecacht werden. Nutzer können im Browser arbeiten, auch wenn offline, und Daten werden synchronisiert, wenn Verbindung zurückkommt.
4. **Kein JavaScript:** Das System bietet Graceful Degradation – Seiten funktionieren ohne WebSockets mit traditionellem Page-Refresh-Flow.

Die Implementierung nutzt ein JavaScript-Library wie Reconnecting-WebSocket oder Sockette, das automatisch Verbindungs-Neuvorschieb mit exponentieller Backoff-Verzögerung handhabt.

---

## B4. Multi-Sprachunterstützung (i18n-System-Erweiterung)
**Aufwand:** M (3 Wochen)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** Enterprise-Ready, Expansion auf europäische Märkte

### Aktueller Status
Das System hat eine grundlegende i18n-Infrastruktur (Translator-Klasse) mit ~200 Übersetzungs-Keys für Deutsch und Englisch. Allerdings sind viele Seiten hardcodiert auf Deutsch, und es fehlen Übersetzungen für Validierungen, E-Mail-Templates und Dokumente. Eine Expansion in weitere Märkte (Frankreich, Spanien, Italien, Osteuropa) erfordert ein systematisches i18n-System, das früh integriert ist, um Kosten zu minimieren.

### B4.1 Ausweitung des Übersetzungs-Umfangs

Das initiale Ziel von ~2.000 Übersetzungs-Keys wird folgende Kategorien abdecken:

- **UI-Labels:** ~800 Keys (Buttons wie „Speichern", „Löschen", Feldnamen wie „Asset-Name", Menüpunkte)
- **Validierungs- & Fehlermeldungen:** ~400 Keys (z. B. „Feld erforderlich", „Ungültige E-Mail", „Dieses Asset ist nicht verfügbar")
- **E-Mail-Templates:** ~200 Keys (Rechnungs-Ankündigungen, Zahlungs-Reminders, Willkommens-E-Mails)
- **Dokument-Templates:** ~300 Keys (Rechnungen, Mietverträge, Lieferscheine)
- **Benachrichtigungen & Alerts:** ~200 Keys (Toast-Nachrichten, System-Meldungen)
- **Hilfetext & Tooltips:** ~200 Keys (Kontextualisierte Hilfe für komplexe Felder)
- **Dynamischer Inhalt:** ~300 Keys (Status-Namen wie „In Gebrauch", Rollen wie „Vermietungsmanager", enum-Werte)

Die Implementierung nutzt die **gettext-Konvention**, die robusteste Lösung für PHP. Strings werden mit `_('deutscher Text')` oder `gettext('Text')` markiert, und ein Build-Tool (z. B. `xgettext`) extrahiert sie in `.pot`-Dateien (Portable Object Templates). Übersetzer bearbeiten `.po`-Dateien (z. B. `de.po`, `en.po`, `fr.po`), und ein Compiler generiert Binary `.mo`-Dateien, die schnell sind.

Eine kritische Best Practice ist, **ganze Sätze als Übersetzungs-Einheiten** zu verwenden, nicht einzelne Wörter. Z. B. nicht `_('Sie haben') . ' ' . count($invoices) . ' ' . _('Rechnungen')`, sondern `sprintf(_('Sie haben %d Rechnungen'), count($invoices))`. Dies ermöglicht grammatikalisch korrekte Übersetzungen in Sprachen mit unterschiedlicher Grammatik (z. B. Deutsche Pluralregeln vs. Englische).

### B4.2 Unterstützung für zusätzliche Sprachen

Die Rollout-Planung ist gestaffelt:

- **Phase 1:** Deutsch (vollständig) + Englisch (umfassend) – diese Sprachen sind kritisch für den Kernmarkt Deutschland und internationale SaaS-Partner.
- **Phase 2:** Französisch, Spanisch, Italienisch – für Westeuropäische Expansion. Diese Sprachen haben ähnliche Grammatik zu Deutsch, sodass Übersetzungen reif sind.
- **Phase 3:** Niederländisch, Polnisch, Tschechisch – für Osteuropäische Expansion. Diese Märkte haben hohe Ausrüstungsvermietungs-Dichte und sind preissensitiv, aber zahlungsfähig.

Die Verwaltung von Übersetzungen wird durch ein professionelles Tool wie **Lokalise** oder **Phrase** gehandhabt (beide bieten kostenlose Pläne für kleine Teams). Diese Tools ermöglichen es, dass externe Übersetzer direkt im Web arbeiten, ohne Git-Repos oder `.po`-Dateien anfassen zu müssen. Sie bieten auch Kontexth-Hilfe (Screenshots des UIs, wo die Übersetzung angezeigt wird), um Qualität zu verbessern.

### B4.3 Lokalisierung von Datum, Nummer und Währung

Eine naive Lokalisierung übersetzt nur Text; echte Lokalisierung adressiert auch Format. Das System wird ein **IntlFormatter-Framework** (PHP Intl-Extension) integrieren, das alle CLDR-Locales unterstützt:

- **Zahlenformat:** Deutschland: €1.234,56 vs. USA: $1,234.56 vs. Frankreich: 1 234,56 €
- **Datumsformat:** Deutschland: 01.03.2026 vs. Großbritannien: 1/3/2026 vs. USA: 3/1/2026
- **Wochenstart:** Die meisten EU-Länder starten Kalender am Montag, USA/UK am Sonntag
- **Zeitzone:** Das System sollte Zeitzone pro Benutzer/Instance konfigurierbar sein, nicht global

Die Implementierung nutzt existierende Filters (`dateDe`, `numberDe`, `moneyDe`), refaktoriert sie zu einem generischen `date($locale)`, `number($locale)`, `money($locale)` System. Ein Utility-Service registriert die lokale Zeitzone pro Request-Context, sodass alle Datumsoperationen konsistent sind.

### B4.4 RTL-Sprachunterstützung (Future)

Falls das System in arabische Märkte expandiert, wird RTL (Right-to-Left) Unterstützung nötig. Dies ist komplex und betrifft CSS, Layout und JavaScript:

- HTML-Root mit `<html dir="rtl" lang="ar">` annotieren
- CSS-Flexbox/Grid mirroring (z. B. `justify-content: flex-end` statt `flex-start`)
- Sidebar nach rechts verschieben, Action-Buttons rechts-ausrichten
- JavaScript-Logik für Cursor-Bewegung, Text-Selection anpassen

Dies sollte später angegangen werden, nachdem arabische Sprachunterstützung bewiesen erfolgreiche Geschäftsmetrik ist. Für jetzt: Design mit RTL im Hinterkopf (keine hardcodierten Farben oder Positionen), damit späte Umsetzung einfacher ist.

---

## B5. API-Modernisierung (RESTful-Architektur)
**Aufwand:** XL (8-10 Wochen)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** Bessere Integrationen, Developer Experience, Wettbewerbsfähigkeit

### Aktuelle Probleme
Das aktuelle API nutzt konvention wie `POST /api/assets/list.php` (semantisch POST für Daten-Abruf, falsch), `POST /api/assets/editAsset.php` (keine konsistente Struktur), und Fehler in API-Versionierung, Paginierung, Fehlerbehandlung und Dokumentation. Dies macht Integrationen mühsam und external Partner-Integrationen schwierig. Eine moderne API ist ein Wettbewerbs-Feature – Partner und Entwickler bevorzugen Systeme mit gut-dokumentierten, vorhersehbaren APIs.

### B5.1 RESTful-Design-Muster mit konsistenter Struktur

Das neue API wird semantische HTTP-Verben verwenden und eine flache URL-Struktur folgen:

```
GET    /api/v2/assets                          # Assets auflisten (mit ?page, ?limit, ?filter)
GET    /api/v2/assets/:id                      # Ein Asset abrufen
POST   /api/v2/assets                          # Neues Asset erstellen
PUT    /api/v2/assets/:id                      # Asset vollständig aktualisieren
PATCH  /api/v2/assets/:id                      # Asset teilweise aktualisieren
DELETE /api/v2/assets/:id                      # Asset löschen

# Verschachtelte Ressourcen (Assets innerhalb von Projekten)
GET    /api/v2/projects/:id/assets             # Assets in Projekt
POST   /api/v2/projects/:id/assets             # Asset zu Projekt hinzufügen
DELETE /api/v2/projects/:id/assets/:assetId    # Asset aus Projekt entfernen
```

Diese Struktur folgt REST-Konventionen, die Entwickler von anderen APIs (GitHub, Stripe, AWS) kennen, und reduziert Lernkurve.

**Versionierung:** Das System nutzt URL-Path-Versionierung (`/api/v2/`, `/api/v3/`) wie Stripe und GitHub, nicht Header-Versionierung. Dies ist developer-freundlicher und ermöglicht es, mehrere Versionen gleichzeitig zu unterstützen. Das Roadmap wird v2 für 6+ Monate unterstützen, dann deprecation-Warnung versenden, dann v1 abschalten.

**Pagination:** Große Datensätze nutzen **Cursor-basierte Pagination** (empfohlen für instabile Datasätze, wo Inserts/Deletes während Paginierung passieren können):
```
GET /api/v2/assets?limit=25&cursor=abc123def
Response: { data: [...], next_cursor: "xyz789" }
```

Für kleinere/statischere Datensätze nutze **Offset-Limit**:
```
GET /api/v2/assets?offset=0&limit=25
Response: { data: [...], total: 456 }
```

**Default:** Limit=25, Offset=0 (nicht 100 oder 1000, um Server-Last zu reduzieren).

### B5.2 Standardisierte Response-Format

Alle Responses folgen einem konsistenten Format:

```json
// Erfolg (200)
{
  "success": true,
  "data": {
    "id": 123,
    "name": "LED Light 1000W",
    "status": "available"
  },
  "meta": {
    "timestamp": "2026-03-15T14:30:00Z",
    "request_id": "req_abc123"
  }
}

// Paginierte Response (200)
{
  "success": true,
  "data": [
    { "id": 1, "name": "Projector" },
    { "id": 2, "name": "Screen" }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 456,
    "pages": 23
  }
}

// Fehler (4xx/5xx)
{
  "success": false,
  "error": {
    "code": "ASSET_NOT_FOUND",
    "message": "Asset mit ID 999 existiert nicht",
    "details": {
      "field": "asset_id",
      "reason": "Keine Datensatz mit dieser ID"
    },
    "request_id": "req_abc123"
  }
}
```

Das `request_id`-Feld ist kritisch für Support: Kunden können diesem ID an Support senden, und Support kann Logs durchsuchen um zu verstehen, was fehlschlag. Das `timestamp`-Feld hilft, Uhr-Skew zwischen Client und Server zu debuggen.

### B5.3 Dokumentation und Developer Experience

Eine gut-dokumentierte API ist genauso wichtig wie die API selbst. Das System wird nutzen:

**OpenAPI 3.1-Spezifikation:** Ein maschinenlesbares Format, das beschreibt, alle Endpoints, Parameter, Response-Schemas, Authentifizierung. Diese wird mit **Swagger-PHP** (bereits in composer.json) auto-generiert aus Code-Annotations:

```php
#[OpenApi\Get(path: '/api/v2/assets/{id}', summary: 'Get asset')]
#[OpenApi\Parameter(name: 'id', description: 'Asset ID')]
#[OpenApi\Response(response: 200, content: new JsonContent(ref: Asset::class))]
public function getAsset($id) { ... }
```

**Interactive Dokumentation:** Swagger UI oder ReDoc wird an `/api/v2/docs` deployed, sodass Entwickler sofort die API erkunden können, ohne externe Dokumentation zu lesen. Sie können auch Test-Requests direkt aus der Web-UI senden.

**Code-Beispiele:** Für jeden Endpoint werden Beispiele in cURL, JavaScript, Python bereitgestellt:
```bash
# cURL
curl -X GET https://api.myrms.de/api/v2/assets/123 \
  -H "Authorization: Bearer YOUR_TOKEN"

// JavaScript
const asset = await fetch('https://api.myrms.de/api/v2/assets/123', {
  headers: { 'Authorization': 'Bearer YOUR_TOKEN' }
}).then(r => r.json());

# Python
import requests
asset = requests.get(
  'https://api.myrms.de/api/v2/assets/123',
  headers={'Authorization': 'Bearer YOUR_TOKEN'}
).json()
```

**Deprecation-Hinweise:** Veraltete Endpoints werden mit einem `Sunset`-Header markiert:
```
HTTP/1.1 200 OK
Sunset: Sun, 31 Dec 2026 23:59:59 GMT
Deprecation: true
Link: </api/v3/assets>; rel="successor-version"
```

Dies warnt Clients rechtzeitig, dass eine Migrationen erforderlich ist, bevor der Endpoint abgeschaltet wird.

### B5.4 Rate-Limiting und Throttling

Das System wird verschiedenen Klassen unterschiedliche Rate-Limits zuweisen, um Missbrauch zu verhindern und faire Ressourcenverteilung zu gewährleisten:

```
Nicht authentifiziert:    100 Anfragen/Stunde
Authentifizierter User:   5.000 Anfragen/Stunde (pro User)
API-Key (Partner):        50.000 Anfragen/Tag
Webhooks:                 Unbegrenzt (intern)
```

Response-Header zeigen den Limit-Status:
```
X-RateLimit-Limit:       5000
X-RateLimit-Remaining:   4998
X-RateLimit-Reset:       1647360000 (Unix-Timestamp, wenn Limit zurückgesetzt wird)
```

Wenn Limit erreicht, gibt das System `429 Too Many Requests` zurück mit `Retry-After`-Header, um dem Client zu sagen, wie lange zu warten ist.

---

## B6. Webhook & Integration-Framework
**Aufwand:** M (2-3 Wochen)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** 3rd-Party-Erweiterungen, Ökosystem-Wachstum

### B6.1 Webhook-Unterstützung mit robusten Retry-Mechanismen

Webhooks ermöglichen es externen Systemen, auf Ereignisse in MyRMS zu reagieren. Das System wird folgende Events triggern:

```
Asset Events:
  asset:created       # Neues Asset hinzugefügt
  asset:updated       # Asset-Details oder Status ändern
  asset:deleted       # Asset gelöscht

Project Events:
  project:created     # Neues Projekt
  project:updated     # Projektdetails/Status ändern
  project:completed   # Projekt abgeschlossen
  project:cancelled   # Projekt abgebrochen

Invoice Events:
  invoice:created     # Neue Rechnung generiert
  invoice:paid        # Rechnung bezahlt
  invoice:overdue     # Rechnung überfällig

Payment Events:
  payment:received    # Zahlung eingegangen
  payment:reversed    # Zahlung storniert

Customer Events:
  customer:created    # Neuer Kunde hinzugefügt
  customer:updated    # Kundendaten ändern
  customer:archived   # Kunde archiviert

Maintenance Events:
  maintenance:scheduled   # Wartung geplant
  maintenance:completed   # Wartung abgeschlossen
```

Ein Administrator registriert einen Webhook über eine UI:

```
Event:    [project:completed] ▼
URL:      [https://partner.com/webhooks/myrms]
Active:   ☑
Retry:    [5 times ▼]
Secret:   [auto-generated token for verification]
[Save] [Delete] [Test]
```

Das Webhook-Payload ist JSON:

```json
{
  "event": "project:completed",
  "timestamp": "2026-03-15T14:30:00Z",
  "id": "evt_abc123def",
  "data": {
    "project_id": 456,
    "project_name": "Equipment rental for Concert XYZ",
    "status": "completed",
    "total_revenue": 15000,
    "completed_at": "2026-03-15T14:30:00Z"
  }
}
```

Das `id`-Feld ist kritisch: Der Partner kann diese ID tracken, um Duplikat-Verarbeitung zu vermeiden (Idempotency). Der `secret`-Token wird zum HMAC-Signing des Payloads genutzt, um Authentizität zu verifizieren:

```
X-Webhook-Signature: sha256=base64(hmac_sha256(body, secret))
```

Der Partner validiert die Signatur bevor Processing:

```javascript
const crypto = require('crypto');
const signature = req.headers['x-webhook-signature'].split('=')[1];
const computed = crypto.createHmac('sha256', secret).update(body).digest('base64');
if (signature !== computed) return res.status(401).send('Invalid signature');
```

### B6.2 Retry-Mechanik und Fehlerbehandlung

Webhooks können fehlschlagen (Partner-Endpoint offline, Timeout, 5xx-Fehler). Das System implementiert **exponentielle Backoff mit Jitter**:

```
Versuch 1:  sofort
Versuch 2:  nach 5 Sekunden + random jitter (0-5s)
Versuch 3:  nach 25 Sekunden + jitter
Versuch 4:  nach 125 Sekunden + jitter
Versuch 5:  nach 625 Sekunden (10+ Minuten) + jitter
```

Diese Strategie verhindert **Thundering Herd**, wo alle fehlgeschlagenen Webhooks gleichzeitig erneut versuchen.

**Response-Code-Handling:**
- **2xx:** Erfolgreich, nicht mehr versuchen
- **3xx:** Redirect – dem Redirect folgen (max. 5 Hops), dann neu versuchen
- **4xx (außer 429):** Permanenter Fehler (z. B. 400 Bad Request, 401 Unauthorized) – nicht mehr versuchen, aber in Audit-Log speichern
- **429:** Rate-Limit – `Retry-After`-Header beachten, exponentielles Backoff nutzen
- **5xx:** Temporärer Fehler – exponentielles Backoff nutzen

**Multi-Stage Retry Lifecycle:**
- **Immediate Retries (Phase 1):** Innerhalb von 5 Minuten nach initialer Fehlgeschlag, 2-3 Versuche
- **Short-Term Retries (Phase 2):** Nächste 1 Stunde, 1-2 Versuche
- **Long-Term Retries (Phase 3):** Nächste 24 Stunden, 1 Versuch (um Netzwerk-Ausfälle zu adressieren)

Nach allen Versuchen wird das Event in eine **Dead Letter Queue** verschoben, wo ein Administrator es sehen und manuell verarbeiten kann (oder den Webhook neu konfigurieren).

Das System muss eine **Webhook-Event-Log** führen, die zeigt: Event-ID, Event-Typ, Webhook-URL, Versuch #, Response-Code, Fehler-Message, Zeitstempel. Dies ermöglicht Debugging und Partner-Support.

**Ziel-Retry-Rate:** <5 % der Webhooks sollten letztendlich fehlschlagen. Höhere Raten zeigen, dass Webhook-Konfiguration oder Partner-Integrationen kaputt sind.

### B6.3 Webhook-Management-UI und Sicherheit

Ein Administrator sieht ein Webhook-Dashboard mit:

- **Liste aller registrierten Webhooks:** Event-Typ, Ziel-URL, aktiv/inaktiv Status, letzte Aktivierung
- **Event-Log-Viewer:** Kann alle Deliveries (erfolg/fehl) mit Retry-Versuche, Response-Codes, Timestamps sehen
- **Test-Funktion:** Kann einen Beispiel-Payload an den Webhook senden, um zu testen, ob der Partner ihn verarbeiten kann
- **Transformation & Filtering:** Optional kann ein Admin ein Filter definieren (z. B. „Sende Webhooks nur für Projekte mit Budget >€10.000"), um Noise zu reduzieren
- **Deactivation:** Ein Admin kann einen Webhook deaktivieren, um zu verhindern, dass weitere Nachrichtenversendet werden (z. B. wenn der Partner-Endpoint permanently offline ist)

**Sicherheit:**
- Webhooks sind nur für authentifizierte Administratoren konfigurierbar
- Der Webhook-Secret wird hash-gespeichert (nicht plaintext)
- HTTPS ist erzwungen (nicht HTTP)
- Partner-URL muss durch ein DNS-Lookup validiert sein (nicht IP-Liste whitelisten)
- Rate-Limit auf Webhook-Registrierungen (nicht >100 pro Instance) um Missbrauch zu verhindern

---

## B7. Mobile App (Native iOS/Android)
**Aufwand:** XL (10-12 Wochen)
**Priorität:** NIEDRIG
**Geschäftlicher Nutzen:** Feldteams, mobile Workforce

### B7.1 MVP-Scope und React Native

Mobile Teams (Techniker, Fahrer) sind feldbasiert und benötigen eine Mobile-App, um Asset-Status offline zu aktualisieren und Bilder zu erfassen. Das MVP-Scope:

- **QR-Code-Scanner:** Asset-Status Check-in/Check-out via QR-Codes (schneller als Eingabe von Asset-IDs)
- **Projektdetails:** Siehe zugewiesene Projekte, aktuellen Status, Termine
- **Equipment-Fotos:** Fotos von Ausrüstung vor/nach Gebrauch, um Schäden zu dokumentieren
- **Offline-Sync:** Arbeite offline, synchronisiere wenn Netzwerk zurück ist (nicht alle Feldgebiete haben Connectivity)
- **Push-Notifications:** Server kann Techniker benachrichtigen (z. B. „Neues Projekt für dich zugewiesen")
- **GPS-Tracking:** Optional für Lieferungen – Server kann Fahrtrouten tracken zur Optimierung

**Technology:** React Native ermöglicht es, 80–90 % Code zwischen iOS und Android zu teilen, was Entwicklungs- und Maintenance-Kosten senkt. Alternatives (Swift + Kotlin) würde 2x Entwickler-Teams erfordern.

### B7.2 Backend-Anforderungen und API-Design

Die Mobile-App benötigt eine robus API (B5 Modernisierung aktiviert dies). Der typische Workflow:

```
1. App sendet POST /api/v2/projects/:id/asset-checkin mit { asset_id, timestamp, photos }
2. Server validiert, speichert Checkin, triggert WebSocket-Update zu anderen Clients
3. Server sendet HTTP 200 + Status-Daten zurück
4. App zeigt Bestätigung
```

**Offline-Sync:**
- App speichert alle Requests lokal (SQLite oder native DB) mit Timestamp
- Wenn online, sendet App gepufferte Requests in Reihenfolge
- Bei Konflikten (z. B. ein Asset wurde bei anderer App geändert): App zeigt Konflikt-Auflösungs-UI

**File-Uploads:**
- Photos als Base64 oder Multipart-Upload über `/api/v2/uploads` POST
- Server speichert in S3, gibt URL zurück
- App speichert URL lokal, verknüpft mit Checkin

**Push-Notifications:**
- App registriert sich mit Firebase Cloud Messaging (FCM) oder Apple Push Notification service (APNs)
- Server kann Nachrichten senden via FCM/APNs APIs
- App zeigt lokale Notification, weckt App auf wenn nötig

Diese Features erfordern ein gut-designtes REST-API oder GraphQL-API, das Batch-Operationen unterstützt und effizient offline-Sync handhabt.

---

## B8. KI-Gesteuerte Funktionen (Erweiterte Implementierung)
**Aufwand:** M-L (3-5 Wochen pro Funktion)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** Automatisierung, proaktive Insights, Wettbewerbsvorteil

### Aktuelle Implementierungen
Das System nutzt bereits Claude-API für E-Mail-Entwurf und Schadensberichte-Zusammenfassungen, mit einer Action-Queue für Approvals. Diese Phase erweitert KI auf weitere hochwertige Anwendungsfälle.

### B8.1 Vorgeschlagene KI-Features

**Smart Pricing Suggestions (Intelligente Preisvorschläge):**
Ein Regressionsmodell (oder Neurales Netz) lernt auf historischen Mietdaten: Wie hängt Nachfrage von Preis, Saisonalität, Wettbewerb und Lagerverfügbarkeit ab? Das Modell suggeriert optimale Tages- oder Stundensätze, die Gewinn maximieren unter Berücksichtigung der Nachfrage-Elastizität. Dies ist besonders wertvoll in Märkten mit hohem Wettbewerb. Implementierung nutzt Open-Source-ML-Libs wie scikit-learn oder TensorFlow, trainiert auf MiniBatches historischer Daten. Das Modell wird monatlich retrainiert.

**Demand Forecasting (Nachfrageprognose):**
Ein Time-Series-Modell (Prophet, ARIMA, oder LSTM) prognostiziert Nachfrage nach spezifischen Assets für zukünftige Wochen. Dies hilft bei Lager-Planung: "Wir brauchen 3 zusätzliche Beamer für die nächsten 2 Wochen wegen hoher Konferenz-Nachfrage". Das Modell berücksichtigt Saisonalität (Sommerhochbetrieb, Winterpause), historische Booking-Trends und externe Signale (z. B. Wirtschafts-Indizes). Implementierung nutzt ein Prophet-Lib, das PHP über REST-API aufrufen kann oder via Python-Wrapper.

**Duplicate Detection (Duplikat-Erkennung):**
Fuzzy Matching (z. B. Levenshtein-Distanz) + E-Mail-Ähnlichkeit wird genutzt, um wahrscheinliche Duplikat-Kunden zu identifizieren. Ein Admin kann dann manuell diese zusammenführen. Dies reduziert Rechnungs-Duplikate und verbessert Kundendatenqualität.

**Anomaly Detection (Anomalie-Erkennung):**
Ein Statistical Model (Isolation Forest, One-Class SVM) identifiziert Anomalien in Transaktionen (z. B. "Diese Rechnung ist 10x höher als normal für diesen Kunden" oder "Diese Zahlung kommt 60 Tage zu spät"). Admin wird alarmiert um zu untersuchen (Betrug? System-Fehler?).

**Auto-Summarize (Auto-Zusammenfassung):**
LLMs wie Claude können Projekt-Notizen, Photos und Timestamps zu lesbaren Projekt-Reports zusammenfassen. Z. B. eine techniker-notiz "Lampen defekt, neue bestellt, kommt Mittwoch" wird zu "Equipment-Schaden festgestellt am 15.03.2026. Reparatur-Teil bestellt, erwartete Reparatur am 17.03.2026". Dies spart Administratoren Zeit bei Dokumentation.

**Crew Scheduling (Personaleinsatz-Planung):**
Ein Constraint-Solver (OR-Tools, Gurobi) optimiert Techniker-Zuweisungen zu Projekten unter Berücksichtigung: Skill-Anforderungen, Verfügbarkeit, Fahrtzeiten, Lernkurven (neuer Techniker braucht mehr Zeit). Dies kann Effizienz um 15-20% verbessern.

**Document OCR (Dokument-Texterkennung):**
Tesseract + Claude Vision können gescannte Rechnungen, Verträge, Lieferscheine auslesen und Daten (Betrag, Datum, Kundenname) ins System extrahieren. Dies automatisiert manuelle Dateneingabe.

### B8.2 Kosten und Implementierungs-Strategie

**Claude API:** ~0,003 € pro 1K Tokens für Text-Modelle. Schätzen Sie 100-1000 Tokens pro Summarization. Bei 100 Auto-Summarizations monatlich sind Kosten <5 €/Monat.

**Custom ML Models:** Werden hohe ML-Features benötigt (Smart Pricing, Forecasting), können Sie entweder: (A) Cloud-Dienste (AWS SageMaker, Azure ML, Google Vertex) nutzen – Setup kostet 5-10k €, dann ~200-500 €/Monat Hosting. (B) Open-Source-Libs (scikit-learn, Prophet) selbst hosten – einfacher zu kontrollieren, aber braucht Data Science-Expertise.

**Empfehlung:** Starten Sie mit API-basierten Features (Claude Summarization). Wenn ROI gezeigt wird, dann investieren Sie in Custom Models.

---

## B9. Customer Portal (B2C Self-Service)
**Aufwand:** M (3-4 Wochen)
**Priorität:** NIEDRIG-MITTEL
**Geschäftlicher Nutzen:** Support-Tickets reduzieren, Self-Service-Erwartungen erfüllen

### Markt-Context
McKinsey 2024-Daten zeigen, dass 73% der B2B-Käufer bereit sind, >50k € online zu verbringen. TrustRadius berichtet, dass ~100% der B2B-Käufer Self-Service erwarten. Ein Customer Portal ist nicht mehr optional – es ist ein Erwartungs-Feature. Organisationen, die ein Portal mit Real-Time-ERP-Integration bieten, sehen 20-30% Reduktion in Support-Tickets.

### B9.1 Portal-Features unter Zugriffskontrolle

Das Portal ist ein Login-geschützter Bereich, den Kunden über eine separate URL (`portal.myrms.de`) oder Subdomain (`/customer-portal`) zugreifen:

```
Dashboard:
├── Aktuelle Projekte/Mietals (mit Status: "Equipment-Vorbereitung", "Lieferung 15.03", "Aktiv", "Rückholung geplant")
├── Kommende Lieferungen/Rückholungen (mit Daten, Adressen, Kontaktperson)
└── Ausstehende Rechnungen (mit Fälligkeitsdatum, Zahlungs-Links)

Meine Mietals:
├── Aktuelle Ausrüstungsliste (mit Seriencummern, Versicherungsstatus)
├── Mietbedingungen & Versicherung (PDFs, Tage-verbleibend, Renewal-Hinweise)
├── Zustandsberichte (Fotos vor/nach, Schaden-Dokumentation)
└── Support-Kontakt (direkter Link zu Support-Formulare)

Rechnungen & Zahlungen:
├── Rechnungshistorie (alle Rechnungen, download als PDF)
├── Zahlungsstatus (welche bezahlt, welche überfällig, Next-Action-Items)
├── Zahlungsmethoden (gespeicherte Kreditkarten, Bankkonten, oder Link zu Pay-Provider)
└── Zahlungs-Statements (Jahresübersicht für Accounting)

Equipment-Katalog:
├── Browse verfügbare Assets (Fotos, Spezifikationen, Verfügbarkeits-Kalender)
├── Pricing & Verfügbarkeit prüfen (Check: "LED Walls 10m² verfügbar 20.03-25.03?")
├── Quote anfordern (Self-Service-Preisgestaltung oder Anfrage an Sales)
└── Equipment-Reservierungen (Optional: Kunde kann Asset reservieren für zukünftiges Datum)

Account:
├── Kontaktinformationen updaten (Anschrift, Telefon, E-Mail)
├── Team-Mitglieder verwalten (wer hat Portal-Zugang)
├── Dokument-Upload (Versicherungs-Zertifikat, ID, Kreditkartenform)
└── Kommunikations-Voreinstellungen (Welche Notifications? Email, SMS, Push?)
```

### B9.2 Zugriffskontrolle und Sicherheit

Das Portal begrenzt Kunden-Zugang auf ihre eigenen Daten. Ein Customer-Portal-User hat:

- **Rollenbasierte Zugriffskontrolle:** Kontakt (sieht alles), Abrechnungskontakt (sieht nur Rechnungen/Zahlungen), Beschränkter Zugang (sieht nur aktuelle Projekte)
- **Projekt-basierte Limits:** Kunde ABC sieht nur Projekte, die Kunde ABC zugewiesen sind – nicht andere Kunden' Daten
- **IP-Whitelist (Optional):** Für hochsicherheits-Kunden kann Admin eine IP-Whitelist definieren (z. B. nur aus Kunde-Büro zugreifen)
- **Zwei-Faktor-Authentifizierung:** 2FA wird empfohlen (optional verpflichtend für Kunden mit hohhem Ausgabevolumen)
- **Session-Timeout:** Inactivity-Timeout nach 30 Minuten, um Sicherheit zu verbessern

---

## B10. Accounting-Software-Integrationen (Erweitert)
**Aufwand:** M pro Integration (2-3 Wochen)
**Priorität:** MITTEL
**Geschäftlicher Nutzen:** Reduziert manuelle Eingabe, Audit-Trail, System-Integration

### Aktuelle Integrationen
Das System unterstützt bereits DATEV-Export (deutsches Accounting-Standard), EÜR-Export, und generischen Cloud-Accounting-Export. Diese Phase erweitert das mit bidirektionalen Syncs zu populären deutschen und internationalen Accounting-Softwares.

### B10.1 Vorgeschlagene bidirektionale Syncs

| Software | Richtung | Aufwand | Synchronisierte Daten |
|----------|----------|---------|----------------------|
| **SevDesk** | Bidirektional | M | Rechnungen ↔ Zahlungen ↔ Kunden |
| **Lexoffice** | Bidirektional | M | Rechnungen ↔ Zahlungen ↔ Kunden ↔ Kontakte |
| **DATEV** | One-Way Export | S | GL-Einträge (bereits vorhanden) |
| **Xero** | Bidirektional | M | Rechnungen, Kunden, GL-Einträge, Banktransaktionen |
| **Wave** | One-Way Export | M | Rechnungen, Kunden, GL-Daten |
| **QuickBooks Online** | Bidirektional | L | Kompletter Accounting-Sync (Rechnungen, Kunden, Konten, GL) |

Diese Integrations-Priorität adressiert den deutschsprachigen Markt zuerst (SevDesk, Lexoffice), dann Europäisch/Englisch (Xero), dann USA-orientiert (QuickBooks).

### B10.2 Sync-Strategie und Conflict Resolution

Der generische Sync-Workflow:

```
1. User konfiguriert Credentials (OAuth2 oder API-Key)
   ↓
2. System testet Verbindung vor dem Speichern (Validiert Auth, Konten-Berechtigung)
   ↓
3. Dry-run Preview: "Folgende Rechnungen werden synchronisiert..."
   ↓
4. Auto-Sync Zeitplan (z. B. täglich um 2 AM, wenn am wenigsten API-Last)
   ↓
5. Manueller Sync-Button für Ad-Hoc-Bedarf
```

**Sync-Direktions-Kontrolle:** Admin kann definieren, welche Daten in welche Richtung gehen:

```
Rechnungen:     MyRMS → SevDesk nur (keine Rücksync)
Kunden:         MyRMS ↔ SevDesk bidirektional
GL-Einträge:    MyRMS → SevDesk nur (Accounting nur von MyRMS)
Zahlungen:      SevDesk → MyRMS (wenn in SevDesk bezahlt, importiere zu MyRMS)
```

**Conflict Resolution:** Wenn ein Record in beiden Systemen geändert wurde (z. B. Rechnungsdetails in MyRMS UND in SevDesk geändert), gewinnt **MyRMS** (Source of Truth), und die SevDesk-Änderung wird überschrieben. Ein Audit-Log dokumentiert alle Syncs, um Datenherkunft nachzuvollziehen.

### B10.3 Bank- und Payment-Gateway-Integrationen

Das System wird bereits mit FinTS (Deutsche Bank-Import), SEPA-Lastschriften, und Zahlungs-Tracking unterstützt. Diese Phase erweitert:

| Service | Zweck | Status |
|---------|-------|--------|
| **FinTS (Deutsche Banken)** | Automatischer Bank-Import (Kontoauszug) | Bereits integriert (BankImportService) |
| **Stripe Connect** | Auto-Sync Zahlungen von Stripe-Rechnungen | Needs Integration (API: `GET /v1/charges?customer_id=...`) |
| **PayPal** | Rechnungs-Zahlungs-Tracking | Needs Integration (Webhooks für completed_payments) |
| **SEPA Lastschrift** | Mandat + Zahlungs-Verarbeitung | Bereits unterstützt |

**Stripe Connect Integration:** Wenn ein Kunde eine MyRMS-Rechnung via Stripe (Stripe-Link in E-Mail) bezahlt, synct das System automatisch: (1) Payment wird zu MyRMS importiert, (2) Rechnung markiert als Paid, (3) Kundenaccount aktiviert. Dies reduziert manuellen Reconciliation.

**PayPal Integration:** MyRMS kann PayPal-Zahlungs-Webhooks empfangen (`PAYMENT.CAPTURE.COMPLETED`), um zu tracken, wenn Kunde via PayPal bezahlt hat.

**Ziel:** Minimale manuelle Dateneingabe bei Zahlungsreconciliation. Ideally 95%+ der Zahlungen werden auto-imported.

---

## Summary: Implementierungs-Roadmap für Part B

Diese Erweiterungsphase adressiert Enterprise-Anforderungen und Competitive Differentiation. Die Priorisierung ist:

1. **Phase 1 (Woche 1-6):** B1 (Reporting), B2 (Export/Distribution) – High-Value für Business Intelligence
2. **Phase 2 (Woche 7-12):** B5 (API-Modernisierung), B6 (Webhooks) – Enabler für weitere Integrationen
3. **Phase 3 (Woche 13-18):** B4 (i18n), B3 (WebSockets), B8 (KI-Features) – UX/Automation
4. **Phase 4 (Woche 19+):** B9 (Customer Portal), B10 (Accounting-Sync), B7 (Mobile) – Optional, basierend auf Geschäftspriorität

Jede Phase sollte agil iterativ sein: MVP → User Feedback → Iteration, nicht waterfall-style Komplette-Implementierungen vor Launch.
# Part C: Architecture & Infrastructure Improvements

## C1. Routing & MVC Foundation (Long-term)

**Effort:** XL (12-15 weeks)
**Priority:** MEDIUM
**Impact:** Maintainability, testing

### Current Issues

Das aktuelle Routing-System basiert auf dateibasierten Endpunkten wie `asset.php`, `assets.php` und `newAsset.php`. Diese Struktur führt zu einer Vermischung von Concerns: API-Logik und HTML-Rendering sind oft im gleichen Skript vermengt, was die Testbarkeit erheblich beeinträchtigt. Ohne ein dediziertes MVC-Framework ist es äußerst schwierig, Komponenten isoliert zu testen, da globale Variablen und Abhängigkeiten überall vorhanden sind. Dies macht automatisierte Tests zu einem komplexen Unterfangen und erhöht die Gefahr von Regressionsfehler bei Änderungen.

### C1.1 Proposed Routing Architecture

Die empfohlene Strategie ist die Einführung eines leichtgewichtigen Frameworks wie **Slim 4**, das minimale Overhead mit maximaler PHP-Freundlichkeit bietet. Slim 4 ist speziell dafür ausgelegt, schrittweise neben bestehendem Code eingeführt zu werden, was eine inkrementelle Migration über einen Zeitraum von etwa drei Monaten ermöglicht. Dies bedeutet, dass neue Features in Slim 4 implementiert werden können, während älterer Code weiterhin funktioniert, bis dieser graduell refaktoriert wird.

Ein Routing-Beispiel mit Slim 4 sieht wie folgt aus:

```php
$app->get('/api/v2/assets', AssetController::class.'listAssets');
$app->post('/api/v2/assets', AssetController::class.'createAsset');
$app->put('/api/v2/assets/{id}', AssetController::class.'updateAsset');
```

Diese explizite Routendefinition ersetzt die implizite dateibasierte Routing und macht die API-Struktur unmittelbar verständlich. Jede Route ist an einen Controller gebunden, der die entsprechende Aktion ausführt.

Alternative zu Slim 4 wäre ein vollständiges Framework wie Laravel oder Symfony. Während diese Optionen ein reiches Ökosystem mit umfangreicher Dokumentation bieten, bedeuten sie auch etwa 500 Stunden Migrations- und Schulungsaufwand. Für MyRMS wird diese Komplexität nicht empfohlen, da das System deutlich leichtgewichtiger ist als ein typisches Enterprise-Application, für das Laravel oder Symfony optimiert sind.

### C1.2 Dependency Injection Container

Ein Dependency Injection Container (DIC) ist das Fundament für ein testbares und wartbares System. Mit **PHP-DI** oder **Pimple** können alle Abhängigkeiten zentral definiert und verwaltet werden, statt dass sie über globale Variablen verteilt sind. Der Container erlaubt es, Abhängigkeiten automatisch in Controller und Services einzuspritzen und diese zur Laufzeit zu überschreiben.

Ein typisches Setup sieht so aus:

```php
$container->set('db', function() {
    return new MysqliDb([...] );
});

$container->set('auth', function($c) {
    return new Auth($c->get('db'));
});
```

Der Container verwaltet nicht nur primitive Abhängigkeiten wie die Datenbankverbindung, sondern auch komplexe Service-Objekte, die ihrerseits Abhängigkeiten haben. Dieser Ansatz ermöglicht es, Services zur Testzeit durch Mocks zu ersetzen, ohne dass der Code selbst geändert werden muss.

### C1.3 Service Layer Consistency

MyRMS verfügt bereits über 120+ Services im Verzeichnis `/src/services/`, aber diese werden nicht konsistent verwendet. Die Zielarchitektur sieht vor, dass **all business logic** in Services implementiert wird, während Controller ausschließlich die HTTP-Schicht (Request/Response) handhaben. Diese strikte Trennung der Concerns macht Services wiederverwendbar, testbar und entkoppelt sie von der HTTP-Implementierung.

Jeder Service sollte eine klare, einzige Verantwortlichkeit haben. Zum Beispiel sollte `AssetService` nur Asset-bezogene Operationen handhaben, nicht auch Validierung oder Authentifizierung. Diese Separation ermöglicht es, die gleiche Business-Logik über mehrere Zugriffspunkte (REST-API, Webhooks, Cron-Jobs) zu teilen, ohne Code zu duplizieren.

### C1.4 Testing Infrastructure

Derzeit existieren nur 6 Unit-Tests im Projekt. Das Ziel ist eine Abdeckung von mindestens 80% des kritischen Codes mit einer Testpyramide:

- **Unit-Tests** (~200+ Tests): Testen isolierte Services und Validatoren ohne Datenbankzugriff. Diese sollten in Millisekunden laufen.
- **Integration-Tests** (~50+ Tests): Testen API-Endpunkte mit echten oder In-Memory-Datenbanken. Diese validieren, dass Services und Controller korrekt zusammenwirken.
- **Funktional-Tests** (~20+ Tests): Testen End-to-End-Workflows wie "Benutzer erstellt Vermögensgegenstand, System erstellt Audit-Log, Email wird versendet". Diese sind am langsamsten, aber am wertvollsten.

Das **PHPUnit**-Framework ist bereits im Projekt vorhanden und gut etabliert. Die CI/CD-Pipeline wird mit **GitHub Actions** implementiert und führt nach jedem Push folgende Schritte aus:

1. **Linting**: PHP-CS-Fixer für Code-Style, PHPStan für statische Analyse
2. **Test-Suite**: PHPUnit mit automatisiertem Coverage-Report
3. **Security-Scan**: Dependabot für veraltete Pakete, symfony/security-checker für bekannte Lücken
4. **Deployment**: Automatischer Deploy auf main-Branch nach Bestehen aller Checks

---

## C2. Dependency Injection & Service Container

**Effort:** M (2-3 weeks)
**Priority:** MEDIUM-HIGH
**Impact:** Reduced globals, easier testing

### C2.1 Container Setup

Das Container-Setup ist die zentrale Konfigurationsdatei für die gesamte Anwendung. Hier werden alle Abhängigkeiten in einem strukturierten Format definiert:

```php
// config/container.php
use function DI\get;

return [
    'db' => fn() => new MysqliDb([...]),
    'auth' => fn($c) => new Auth($c->get('db')),
    'config' => fn() => Config::getInstance(),
    'cache' => fn() => new RedisCache(),
    // Alle Services werden hier registriert
];
```

Slim 4 unterstützt PSR-11, einen standardisierten Container-Interface. Dies bedeutet, dass der Container direkt vor der App-Initialisierung registriert wird:

```php
$container = require 'config/container.php';
$app = AppFactory::createFromContainer($container);
```

Alternativ kann `AppFactory::setContainer()` vor `create()` aufgerufen werden, um explizit den Container zu definieren, bevor Slim die Anwendung initialisiert.

Die Verwendung in einem Controller ist dann trivial:

```php
public function listAssets(AssetService $assets, Auth $auth) {
    return $assets->getList($auth->getCurrentInstance());
}
```

Slim und PHP-DI erkennen automatisch die Typhinweise und spritzen die entsprechenden Abhängigkeiten ein (Autowiring). Dies wird als Konstruktor-Injection implementiert oder über Setter-Methoden, je nach Anforderung.

### C2.2 Service Registration

Mit PHP-DI ist es möglich, alle 120+ bestehenden Services in die Container-Konfiguration zu migrieren. Das Ziel ist, dass keine Service je manuell mit `new` instanziiert wird, sondern immer über den Container bezogen wird. Dies ermöglicht es, Konfigurationen zentral zu verwalten und zur Testzeit zu überschreiben.

Die Auto-Wiring-Funktionalität von PHP-DI reduziert die notwendige Boilerplate-Konfiguration erheblich. Wenn ein Service Abhängigkeiten hat, die selbst im Container definiert sind, werden diese automatisch injiziert. Allerdings sollten explizite Definitionen bereitgestellt werden, um Mehrdeutigkeiten zu vermeiden (z.B. wenn mehrere Implementierungen einer Schnittstelle existieren).

Zusätzlich sollte es möglich sein, Konfigurationsoverrides zu Laufzeit vorzunehmen. Dies ist besonders nützlich für Tests oder Umgebungs-spezifische Einstellungen:

```php
// Test-Umgebung
$container->set('cache', fn() => new InMemoryCache());

// Production
$container->set('cache', fn() => new RedisCache());
```

---

## C3. Caching Strategy (Performance)

**Effort:** M (2-3 weeks)
**Priority:** MEDIUM
**Impact:** 50%+ faster queries

### Current State

Die Anwendung verfügt über eine `ProjectFinanceCache`-Tabelle, die als einfacher Cache fungiert. Jedoch gibt es kein strukturiertes Abfrage-Caching, Seiten-Caching oder Multi-Layer-Caching-System. Dies führt dazu, dass häufig die gleichen Datenbankabfragen wiederholt werden, selbst wenn sich die Daten nicht geändert haben.

### C3.1 Multi-Layer Caching Architecture

Ein professionelles Caching-System hat mehrere Schichten, jede mit unterschiedlichen Charakteristiken und TTL-Werten:

**Layer 1: Application Cache (Redis)**

Die erste Schicht ist ein In-Memory-Cache mit Redis. Der Cache-aside-Strategie folgt: Bevor die Datenbank abgefragt wird, wird zuerst Redis überprüft. Typische Keys sind `asset_list`, `customer_{id}`, `project_dashboard`. Mit einer TTL von 5 Minuten wird sichergestellt, dass relativ frische Daten gecacht werden, ohne sie zu lange zu speichern.

Cache-Invalidation ist kritisch: Wenn ein Vermögensgegenstand erstellt, aktualisiert oder gelöscht wird, müssen verwandte Cache-Keys gelöscht werden. Dies könnte `asset_list`, `project_dashboard_{project_id}`, `asset_{id}` und `utilization_report` umfassen.

Die praktische Auswirkung ist dramatisch: Ein Direct-DB-Query dauert 100-500ms. Ein Redis-Treffer dauert 1-5ms. Dies ergibt eine **10-100x Leistungsverbesserung** für gecachte Anfragen und eine **60-80% Reduktion der Datenbankauslastung** in typischen Szenarien.

**Layer 2: Database Query Cache**

Die zweite Schicht ist eine Query-Result-Caching im Redis. Dies ähnelt Layer 1, ist aber auf Datenbankabfragen fokussiert. Während Layer 1 Business-Logik zwischenspeichert, zwischenspeichert diese Schicht rohe Datenbankresultate. Dies ist besonders für SELECT-Queries mit niedriger Volatilität sinnvoll.

Das ORM (MeekroDB) sollte transparent TTL-basierte Query-Caching unterstützen:

```php
// Mit Caching
$assets = $db->queryCached(
    "SELECT * FROM assets WHERE instances_id = ?",
    [1],
    'assets_instance_1',
    300 // 5 Minuten TTL
);
```

Mit Redis MONITOR und INFO Befehlen kann die Cache-Effizienz überwacht werden: Hit-Rate, Memory-Auslastung, und Hot-Keys sollten regelmäßig analysiert werden.

**Layer 3: HTTP Cache (CDN/Client)**

Die dritte Schicht nutzt HTTP-Caching-Header. Für statische Dashboards oder Reporting-Seiten können `Cache-Control`, `ETag` und `Last-Modified`-Header gesetzt werden:

```
Cache-Control: public, max-age=3600
ETag: "abc123def456"
```

Dies ermöglicht es, dass Browser und CDNs (wie Cloudflare oder Akamai) Responses cachen, ohne dass die Anwendung erneut kontaktiert werden muss. Dies ist besonders für global verteilte Instanzen wertvoll.

**Layer 4: Service Worker (Browser Cache)**

Die vierte Schicht ist ein Service Worker im Browser, der Offline-First-Funktion ermöglicht. GET-Anfragen werden im Cache gespeichert und offline bereitgestellt. POST/PUT-Anfragen werden in einer Warteschlange eingefügt und synchronisiert, sobald die Verbindung wiederhergestellt ist. IndexedDB wird verwendet, um größere Datasets lokal zu speichern.

Diese vier Schichten zusammen schaffen ein resilientes, schnelles System, bei dem jede Schicht die nächste bei Misserfolg überbrückt.

### C3.2 Cache Invalidation Strategy

Cache-Invalidation ist eines der schwierigsten Probleme in der Informatik. Die Strategie für MyRMS ist eine **explizite, ereignisgesteuerte Invalidation**: Wenn eine Ressource geändert wird, werden zugehörige Cache-Keys gelöscht.

Beispiel: Wenn ein Vermögensgegenstand aktualisiert wird, müssen folgende Keys invalidiert werden:

```
asset_list          (Liste wird ungültig)
project_dashboard_{project_id}  (Dashboard zeigt alte Daten)
asset_{id}          (Der Gegenstand selbst)
utilization_report  (Bericht ist ungültig)
asset_search_index  (Suchindex muss aktualisiert werden)
```

Dies wird durch eine Konfigurationsdatei definiert:

```php
// config/cache_invalidation.php
return [
    'assets' => [
        'create' => ['asset_list', 'project_dashboard', 'asset_search'],
        'update' => ['asset_{id}', 'asset_list', 'project_dashboard'],
        'delete' => ['asset_list', 'project_dashboard', 'utilization_report'],
    ],
];
```

Ein Event-System oder Hooks könnte verwendet werden, um Invalidation automatisch auszulösen, ohne dass jeder Service sich dieser Details bewusst sein muss.

### C3.3 Implementation (Phased)

Die Implementierung wird in vier Phasen durchgeführt:

**Phase 1: Redis Setup & Basic App Cache** (2 Wochen)
- Redis-Instanz wird bereitgestellt (lokal oder Cloud)
- Basis-Client-Bibliothek ist integriert
- Dashboard-Daten werden gecacht
- Cache-Hit/Miss-Metriken werden verfolgt

**Phase 2: Query Result Caching** (2-3 Wochen)
- MeekroDB ist mit Caching-Support erweitert
- Häufig abgerufene Tabellen (assets, customers, projects) sind gecacht
- Cache-Invalidation ist implementiert
- Performance-Tests zeigen 50%+ Verbesserung

**Phase 3: HTTP Cache & CDN** (2-3 Wochen)
- HTTP-Header sind konfiguriert
- CDN-Integration wird getestet
- Static Assets werden aggressiv gecacht
- Cloudflare oder ähnliche werden konfiguriert

**Phase 4: Service Worker & Offline Sync** (3-4 Wochen)
- Service Worker wird entwickelt
- Offline-Funktionalität wird implementiert
- Sync-Warteschlange funktioniert
- Progressive Web App Features sind vorhanden

---

## C4. Queue System for Async Tasks

**Effort:** M (2-3 weeks)
**Priority:** MEDIUM
**Impact:** Better performance, no blocking requests

### Current Issues

Schwere Operationen wie PDF-Generierung, Bild-Verarbeitung und Email-Versand werden synchron in der Anfrage verarbeitet. Dies bedeutet, dass der Benutzer wartet, während diese Operationen abgeschlossen werden – oft 2-30 Sekunden. Dies führt zu Timeouts, schlechter User Experience und Ressourcen-Engpässen. Darüber hinaus gibt es keinen Retry-Mechanismus für fehlgeschlagene Jobs, was Datenverlust bedeutet.

### C4.1 Job Queue Architecture

Die empfohlene Technologie ist **Redis Queues** für schnelle, in-Memory-Verarbeitung, oder **RabbitMQ** für AMQP-basierte Fehlertoleranz. Redis Queues sind äußerst schnell – eine Job-Eintragung dauert < 1ms. RabbitMQ bietet persistente Delivery-Garantien auf Kosten von etwas höherer Latenz.

Typische Job-Typen in MyRMS sind:

- **Email-Versand** (2-3 Sekunden pro Email): Rechnungen, Benachrichtigungen, Bestätigungen
- **PDF-Generierung** (5-10 Sekunden): Rechnungen, Verträge, Reports
- **Bild-Verarbeitung** (2-5 Sekunden): Resize, Komprimierung, Thumbnail-Generierung
- **Report-Generierung** (5-30 Sekunden): Finanzberichte, Auslastungsanalysen
- **Daten-Import** (variable): Bulk-Import von Kunden, Vermögensgegenständen
- **Webhook-Lieferung** (2-5 Sekunden, mit Retry): Benachrichtigungen an externe Systeme
- **Cron-Jobs** (variable): Periodische Aufräumarbeiten, Datenberechnungen

Ein typischer Workflow würde so aussehen:

1. Benutzer klickt "Rechnung generieren"
2. Ein Job wird sofort in die Warteschlange eingefügt (< 100ms Antwort an Benutzer)
3. Ein Worker-Prozess verarbeitet den Job asynchron
4. Wenn abgeschlossen, erhält der Benutzer eine Email mit Download-Link
5. Benutzer kann die Rechnung herunterladen, wenn bereit

Dies führt zu sofortiger Response für den Benutzer und optimaler Ressourcentnutzung auf dem Server.

### C4.2 Queue Workers

Worker-Prozesse sind spezialisierte PHP-Prozesse, die Warteschlangen überwachen und Jobs verarbeiten. Sie sollten als systemd-Service oder mit Supervisor verwaltet werden:

```
Deployment:
├── Dedicated queue worker process (systemd service)
├── Monitor with Supervisor (restart bei Crash)
├── Scale horizontally: 2-4 workers für hohe Auslastung
├── Graceful shutdown: Aktuellen Job beenden, dann stoppen
```

Konfiguration für Worker-Prozesse:

```
Concurrency: 1-5 Jobs pro Worker
Retry: 3 Versuche mit exponential backoff
Timeout: 5 Minuten pro Job
Logging: Alle Job-Events in Datenbank/Monolog
```

Bei Produktions-Deployments sollten folgende Flags verwendet werden:

```bash
php artisan queue:work --max-jobs=1000 --max-time=3600
```

Dies verhindert Memory-Leaks (Worker wird nach 1000 Jobs neu gestartet) und begrenzt die Laufzeit auf 1 Stunde pro Worker-Prozess.

### C4.3 Job Monitoring Dashboard

Ein Admin-Dashboard sollte Echtzeitüberwachung für die Job-Warteschlange ermöglichen:

```
UI: Admin → System Health → Job Queue

Features:
├── Queue-Statistiken: Ausstehend, Verarbeitung, Abgeschlossen, Fehlgeschlagen
├── Failed Job Log: Detaillierte Error-Meldungen, Replay-Button
├── Job-Verlauf: Chart von Jobs/Stunde, Durchschnittliche Verarbeitungszeit
├── Retry-Mechanismus: Manuelles Triggern fehlgeschlagener Jobs
├── Rate Limiting: Maximale Jobs pro Stunde, um Warteschlangen-Überlauf zu verhindern
```

Mit diesen Tools können Operatoren schnell erkennen, wenn Jobs fehlschlagen, und sie manuell erneut auslösen oder debuggen.

---

## C5. Improved Error Handling & Logging

**Effort:** M (2-3 weeks)
**Priority:** MEDIUM
**Impact:** Faster debugging, better reliability

### Current State

Sentry-Integration existiert bereits für Fehler-Tracking, aber strukturiertes Logging ist minimal. Stack-Traces könnten sensible Daten aussetzen. Es gibt keine konsistente Fehlerbehandlung über verschiedene Komponenten.

### C5.1 Structured Logging

**Monolog** ist der Industrie-Standard für PHP-Logging. Es bietet mehrere Handler (Datei, Syslog, Email, Sentry) und Formatters (JSON, Einzeilen-Text). Die Implementierung sollte strukturiertes JSON-Logging sein:

```json
{
  "timestamp": "2026-03-15T14:30:00Z",
  "level": "info",
  "message": "Invoice created",
  "user_id": 123,
  "instance_id": 1,
  "invoice_id": "RE-2026-0001",
  "amount": "€5,000.00",
  "trace_id": "abc123"
}
```

Die `trace_id` ist kritisch für Anfrage-Korrelation. Wenn ein Request von Service A zu Service B zu Service C fließt, haben alle generierten Logs die gleiche `trace_id`, was es einfach macht, den gesamten Request-Pfad zu folgen.

Log-Level sollten konsistent verwendet werden:

- **DEBUG**: Entwickler-Informationen (SQL-Queries, API-Aufrufe, Variable-Werte)
- **INFO**: Benutzeraktionen (Rechnung erstellt, Vermögensgegenstand aktualisiert, Login)
- **WARNING**: Verdächtige Aktivität (fehlgeschlagener Login, Rate-Limiting, Ungültige Eingabe)
- **ERROR**: Anwendungsfehler (Job-Crash, Fehlende Datei, Datenbank-Fehler)
- **CRITICAL**: Systemausfälle (Datenbank offline, Out of Memory, Dienst nicht verfügbar)

### C5.2 Error Recovery Strategies

Für verschiedene Fehlerszenarien sollten explizite Wiederherstellungsstrategien implementiert werden:

| Scenario | Current | Proposed |
|----------|---------|----------|
| PDF generation fails | HTTP 500 | Queue job, retry, email user with error details |
| Email bounces | Lost | Log bounce, mark address invalid, prompt user to confirm |
| Payment webhook fails | Ignored | Retry with exponential backoff, alert admin |
| Database connection lost | HTTP 500 | Fall back to read-only cache, alert ops team |
| File upload fails | Error | Retry upload, provide alternative storage option |
| API rate limit hit | Block | Exponential backoff, queue for later, notify user |

Zum Beispiel, wenn ein Email-Versand fehlschlägt:

1. Log der Fehlermeldung mit vollständigem Kontext
2. Job wird in eine "Dead Letter Queue" eingefügt
3. Nach 1 Minute wird es wiederholt (exponential backoff: 1m, 5m, 30m, 2h)
4. Nach 3 Versuchen wird ein Admin benachrichtigt
5. Benutzer erhält eine Benachrichtigung, dass die Email verzögert wird

### C5.3 Sensitive Data Redaction

Bevor Log-Daten gespeichert oder an Sentry gesendet werden, müssen sensible Informationen redigiert werden. Dies schließt Kreditkartennummern, Bankkontodaten, API-Keys und Passwörter ein.

Beispiel:

Vorher:
```json
{
  "cc_number": "4111111111111111",
  "bank_account": "DE89370400440532013000",
  "api_key": "sk_prod_abc123xyz"
}
```

Nachher:
```json
{
  "cc_number": "****1111",
  "bank_account": "****3000",
  "api_key": "sk_prod_***"
}
```

Dies wird durch Redigierungs-Patterns konfiguriert, die auf Daten angewandt werden, bevor sie geloggt werden:

```php
$logger->addProcessor(function($record) {
    // Redigiere cc_number
    $record['extra']['cc_number'] = preg_replace(
        '/\d{12}(\d{4})/',
        '****$1',
        $record['extra']['cc_number'] ?? ''
    );
    return $record;
});
```

---

## C6. Database Optimization & Migrations

**Effort:** M (3-4 weeks)
**Priority:** MEDIUM
**Impact:** 20-30% query performance improvement

### Current Issues

Die Datenbank verwendet derzeit `latin1_swedish_ci` Charset, was German umlauts (ä, ö, ü) problematisch macht. Häufig abgerufene Spalten fehlen Indizes, was zu langsamen Queries führt. Große Tabellen wie `payments` und `auditLog` sind nicht partitioniert, was Full-Table-Scans verursacht.

### C6.1 Charset Migration

Das Ziel ist eine Migration von `latin1_swedish_ci` zu `utf8mb4_unicode_ci`. Dies ist eine kritische Änderung für ein System mit deutschem Fokus, da `utf8mb4` alle Unicode-Zeichen richtig darstellt, einschließlich Umlauten und Sonderzeichen.

Migrationsprozess:

```sql
-- Schritt 1: Backup der aktuellen Datenbank
mysqldump --all-databases > backup.sql

-- Schritt 2: Neue Datenbank mit utf8mb4 erstellen
CREATE DATABASE rms_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Schritt 3: Daten mit --default-character-set dumpen
mysqldump --all-databases --default-character-set=utf8mb4 | mysql -u root rms_new

-- Schritt 4: Datenkonsistenz verifizieren
SELECT COUNT(*) FROM rms_new.assets WHERE assets_name LIKE '%ä%';
SELECT COUNT(*) FROM rms_new.assets WHERE assets_name LIKE '%ö%';

-- Schritt 5: Anwendung testen gegen rms_new

-- Schritt 6: Promote zu Production
RENAME TABLE rms TO rms_old;
RENAME TABLE rms_new TO rms;
```

Nach der Migration sind alle Queries "Ä" und "Ö" korrekt behandelt.

### C6.2 Index Optimization

Slow-Query-Log sollte aktiviert werden, um häufig langsame Queries zu identifizieren:

```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;
```

Basierend auf typischen Abfrage-Mustern fehlen diese kritischen Indizes:

```sql
-- Index für Projekt-Filterung nach Datum
ALTER TABLE projects ADD INDEX idx_instance_date
  (instances_id, projects_endDate DESC);

-- Index für Zahlungs-Filterung nach Kunde und Datum
ALTER TABLE payments ADD INDEX idx_client_date
  (clients_id, payments_timestamp DESC);

-- Index für Audit-Log-Queries
ALTER TABLE auditLog ADD INDEX idx_timestamp
  (auditLog_timestamp DESC);

-- Index für Such-Queries
ALTER TABLE assets ADD INDEX idx_name_search
  (assets_name);

-- Composite Index für gemeinsame Filter
ALTER TABLE projects ADD INDEX idx_filter
  (instances_id, projects_status, projects_endDate);
```

Nach Index-Hinzufügen sollte die EXPLAIN-Ausgabe verifiziert werden:

```sql
EXPLAIN SELECT * FROM projects WHERE instances_id = 1 ORDER BY projects_endDate DESC;
```

Eine funktionierende Abfrage mit Index zeigt "Using index" in der Ausgabe. Die Verbesserung ist typischerweise **10-100x schneller** für betroffene Queries.

### C6.3 Partitioning Large Tables

Für Tabellen > 50 GB ist Partitionierung eine Überlegung. `auditLog` mit 10+ Jahren Daten ist ein Kandidat:

```sql
-- auditLog nach Monat partitionieren
ALTER TABLE auditLog
PARTITION BY RANGE (YEAR(auditLog_timestamp) * 100 + MONTH(auditLog_timestamp)) (
    PARTITION p202601 VALUES LESS THAN (202602),
    PARTITION p202602 VALUES LESS THAN (202603),
    PARTITION p202603 VALUES LESS THAN (202604),
    -- ... weitere Partitionen
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

Mit Partitionierung nutzt eine Query-Bedingung wie `WHERE auditLog_timestamp > NOW() - INTERVAL 1 MONTH` nur die relevante Partition (z.B. p202603), nicht die gesamte Tabelle. Dies führt zu massiven Speed-Gains für große Tabellen.

**Tradeoff**: Write-Performance sinkt leicht, da Partitionen überwacht werden müssen. Aber Read-Performance ist sehr viel besser.

**Timeline**:
- Für 0-10 GB Tabellen: Später machen
- Für 50GB+ Tabellen: Kritische Optimierung

### C6.4 Phinx Migrations

Datenbank-Änderungen sollten über Phinx-Migrationen verwaltet werden, nicht über manuelle SQL:

```php
// db/migrations/20260315_charset_migration.php

use Phinx\Migration\AbstractMigration;

class CharsetMigration extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
        );

        $this->execute(
            "ALTER TABLE assets CONVERT TO CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
        );

        $this->execute(
            "ALTER TABLE customers CONVERT TO CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
        );
    }

    public function down()
    {
        // Rollback: Zurück zu latin1
        $this->execute(
            "ALTER TABLE users CONVERT TO CHARACTER SET latin1
             COLLATE latin1_swedish_ci"
        );
    }
}
```

Migrationen können so versioniert und getestet werden:

```bash
# Migrate to production
vendor/bin/phinx migrate -e production

# Rollback bei Fehler
vendor/bin/phinx rollback -e production
```

---

## C7. Security Hardening

**Effort:** M (3-4 weeks)
**Priority:** HIGH
**Impact:** Compliance, risk reduction

### Current State

CSRF-Schutz existiert bereits, Input-Sanitization ist implementiert (InputSanitizer-Service), und ein SQL-Injection-Audit hat gezeigt, dass das System größtenteils sauber ist. Aber es gibt mehrere Verbesserungen basierend auf OWASP Top 10:2025.

### Current Security Assessment

Die OWASP Top 10:2025 wurde basierend auf 175.000+ Sicherheitslücken aktualisiert und enthält neue Kategorien wie "Software Supply Chain Failures" und "Mishandling Exceptional Conditions". Die folgenden Verbesserungen sollten priorisiert werden:

| Item | Current | Target | Effort |
|------|---------|--------|--------|
| HTTPS Only | Configured | Enforce HSTS (Strict-Transport-Security) | S |
| CSRF Tokens | ✓ | Refresh on every request | S |
| Password Policy | Basic | NIST 800-63B (no complexity requirements) | S |
| 2FA | TOTP service exists | UI/UX integration, WebAuthn support | M |
| API Key Rotation | Manual | Auto-rotate every 90 days | S |
| Session Management | Timeout: none | Idle timeout 30 min | S |
| SQL Injection | Mostly safe | 100% audit + parameterized queries | M |
| XSS Prevention | Twig auto-escape | Content-Security-Policy header | S |
| Rate Limiting | Basic | Per-user, per-IP, per-endpoint | M |
| File Upload Security | Validation | Scan with ClamAV antivirus | M |
| Secrets Management | .env file | Vault (HashiCorp Vault or AWS Secrets Manager) | M |
| Container Deployment | Not used | Hardened Docker image | M |
| Penetration Testing | Never done | Annual third-party assessment | L |

**HTTPS & HSTS**: Der Server sollte HTTP zu HTTPS umleiten und einen HSTS-Header setzen:

```php
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

Dies erzwingt Browser, immer HTTPS zu verwenden, auch wenn Benutzer HTTP eingeben.

**CSRF Token Refresh**: Token sollten nach jeder Verwendung aktualisiert werden, um Session-Fixation zu verhindern.

**Password Policy**: Statt Komplexitätsanforderungen (Großbuchstaben, Symbole, etc.) sollte NIST 800-63B befolgt werden: Mindestens 8 Zeichen, keine obligatorischen Symbole, Überprüfung gegen Common-Password-Listen.

**2FA (Two-Factor Authentication)**: MyRMS hat ein TOTP-Service, aber es ist nicht in die UI integriert. WebAuthn (FIDO2) wird der Standard für 2026. Dies sollte unterstützt werden.

**API Key Rotation**: API-Keys sollten alle 90 Tage automatisch rotiert werden. Alte Keys sollten schrittweise deaktiviert werden.

**Session Timeout**: Eine 30-Minuten-Idle-Timeout verhindert, dass offene Browser unbegrenzt Zugriff haben.

**SQL Injection**: Obwohl ein Audit "mostly clean" ergab, sollte ein 100% Audit durchgeführt werden. Alle Abfragen sollten parameterisiert sein:

```php
// Sicher
$db->where('user_id', $user_id)->get('users');

// Auch sicher
$db->rawQuery('SELECT * FROM users WHERE user_id = ?', [$user_id]);

// Unsicher (zu vermeiden)
$db->rawQuery("SELECT * FROM users WHERE user_id = $user_id");
```

**XSS Prevention**: Twig hat Auto-Escaping, aber Content-Security-Policy (CSP) sollte hinzugefügt werden:

```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://trusted.cdn.com");
```

**Rate Limiting**: Pro-Benutzer, pro-IP und pro-Endpunkt Rate-Limits verhindern Brute-Force und DoS-Angriffe:

```php
// 100 Requests pro Minute pro IP
middleware(RateLimit::perIP(100));

// 10 Login-Versuche pro Minute pro Benutzer
middleware(RateLimit::perUser(10, endpoint: '/login'));
```

**File Upload Security**: Hochgeladene Dateien sollten mit ClamAV gescannt werden, einem Open-Source-Antivirus-Engine:

```bash
# Installation
apt-get install clamav clamav-daemon

# PHP Integration
$scanner = new ClamAVScanner();
if (!$scanner->scan($file_path)) {
    throw new VirusDetected("Infected file detected");
}
```

**Secrets Management**: `.env`-Dateien sollten nicht in Code-Repositories gespeichert werden. Stattdessen sollte HashiCorp Vault oder AWS Secrets Manager verwendet werden:

```php
// Anstelle von: $db_password = $_ENV['DB_PASSWORD']
$vault = new VaultClient('https://vault.internal');
$db_password = $vault->getSecret('database/prod/password');
```

**Container Deployment**: Anwendung sollte in einem gehärteten Docker-Image laufen, das mit einem minimalen Base-Image (Alpine Linux) beginnt:

```dockerfile
FROM php:8.3-cli-alpine
RUN apk add --no-cache redis mysql-client
COPY . /app
WORKDIR /app
RUN composer install --no-dev --optimize-autoloader
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

Container sollten mit read-only Filesystems laufen und minimalen Berechtigungen.

### C7.2 Audit & Compliance

Ein strukturiertes Audit-Programm sollte implementiert werden:

**Monthly**:
- PHPStan Static Analysis auführen (Level 8)
- Dependabot-Check für veraltete/verwundbare Pakete
- SSL-Zertifikat-Erneuerung überprüfen
- Password-Policy-Audit (schwache Passwörter flaggen)

**Quarterly**:
- Security Code Review (zufällig 5% der Änderungen)
- Penetration Testing (Basic: OWASP Top 10)
- Access Control Audit (Wer hat Admin-Zugriff?)
- Dependency Update Review

**Annually**:
- Third-Party Security Assessment (von externem Sicherheitsunternehmen)
- Compliance Audit (GDPR, SOC 2 bei Bedarf)
- Disaster Recovery Drill (Backup-Wiederherstellung testen)
- Security-Training für Entwickler

Diese Cadence stellt sicher, dass Sicherheit nicht einmal durchgeführt wird, sondern ein kontinuierlicher Prozess ist.

---

## Summary

Part C konzentriert sich auf die Modernisierung der Architektur und Infrastruktur von MyRMS. Mit dem Slim 4-Framework, Dependency Injection, Multi-Layer-Caching, Job-Queues, strukturiertem Logging und Datenbank-Optimierung wird MyRMS ein robustes, skalierbares System. Die Sicherheitshärtung basierend auf OWASP 2025 stellt sicher, dass das System den aktuellen Best Practices genügt. Die Implementierung sollte phasenweise durchgeführt werden, mit Fokus auf die höchstpriorisierten Punkte zuerst.
# MyRMS Roadmap: Parts D-G (Expanded)

## Part D: Integration Opportunities

### D1. Calendar & Scheduling Integrations

**Effort:** M pro Integration (2-3 Wochen)
**Priorität:** MEDIUM
**Impact:** Reduzierte manuelle Dateneingabe, verbesserte Zeitplanung, automatisierte Terminabstimmung

#### Ausgangssituation

Das aktuelle System verfügt bereits über eine ICS-Export-Funktionalität mittels der eluceo/ical-Bibliothek. Diese ermöglicht es, Projekte und Termine als iCalendar-Format zu exportieren. Allerdings existiert derzeit keine bidirektionale Synchronisation mit gängigen Kalenderanwendungen wie Google Calendar oder Microsoft Outlook. Das bedeutet, dass Änderungen in MyRMS nicht automatisch in den Kundenkalendern erscheinen und umgekehrt. Dies führt zu doppelter Datenpflege und erhöhtem Fehlerpotenzial.

#### D1.1 Google Calendar Integration

Die Integration mit Google Calendar erfolgt auf Basis des bewährten OAuth2-Flows, der eine sichere, benutzerfreundliche Authentifizierung ohne Speicherung von Passwörtern ermöglicht. Der Authentifizierungsprozess folgt diesen Schritten: Der Benutzer initiiert die Verbindung durch Klick auf den Button „Google Calendar verbinden", worauf die Anwendung zur Google-Zustimmungsseite (Google Consent Screen) weitergeleitet wird. Der Benutzer autorisiert der MyRMS-Anwendung die Berechtigung, auf seinen Google Calendar zuzugreifen. Nach erfolgreicher Autorisierung speichert das System einen Refresh-Token, der eine langfristige Synchronisation ohne erneute Benutzer-Interaktion ermöglicht. Dadurch werden Projekte automatisch bidirektional mit Kalendereinträgen synchronisiert.

Die Synchronisationsstrategie definiert klar, welches System die Quelle der Wahrheit darstellt und wie Konflikte gelöst werden. Wenn in MyRMS ein neues Projekt erstellt wird, wird automatisch ein entsprechender Termin im Google Calendar erzeugt. Änderungen an Projektdaten (insbesondere Datumswechsel) aktualisieren den zugehörigen Kalendereintrag in Echtzeit. Sollte ein Projekt gelöscht werden, wird auch der entsprechende Termin aus dem Google Calendar entfernt. Um Datenkonsistenz zu wahren, werden Einträge, die direkt in Google Calendar außerhalb von MyRMS erstellt werden, als neue Projekte in das System importiert. Die Grundregel zur Konfliktauflösung besagt: MyRMS ist die Quelle der Wahrheit. Sollte es zu Abweichungen kommen (beispielsweise, wenn ein Termin in MyRMS und gleichzeitig im Google Calendar geändert wird), wird der Benutzer durch einen klaren Dialog informiert, dass MyRMS die führende Quelle darstellt.

Die Kalenderstruktur ist dabei optimiert für Rental-Management: Der Kalendername lautet „MyRMS - [Instance Name]", was eine klare Trennung zu persönlichen oder anderen geschäftlichen Kalendern gewährleistet. Der Ereignistitel folgt dem Schema „[PROJEKTTYP] - [KUNDENNAME] - [BESCHREIBUNG]", z.B. „Vermietung - Acme Corp - LED-Panels für Konferenz". Der Ort wird entweder mit der Assetlocation oder der Lieferadresse gefüllt, da diese Information für die Planung logistischer Ressourcen relevant ist. Die Dauer des Termins erstreckt sich vom Abholtag bis zum Rückgabetag, wobei diese Zeitspanne die gesamte Mietperiode abbildet. Die Ereignisbeschreibung enthält strukturierte Informationen: eine Auflistung aller vermieteten Assets, Crew-Zuweisungen sowie relevante Notizen. Zur Erinnerungsverwaltung werden automatisch zwei Reminder konfiguriert: eine Erinnerung einen Tag vor der Lieferung und eine zweite eine Stunde vor Lieferbeginn, um die operativen Teams rechtzeitig zu benachrichtigen.

#### D1.2 Outlook/Microsoft 365 Integration

Die Integration mit Microsoft Outlook und dem Microsoft 365-Ökosystem folgt einem ähnlichen Muster wie die Google Calendar-Integration, nutzt jedoch die Microsoft Graph API. Die Graph API bietet umfangreiche Funktionen für die Verwaltung von Kalentern, Ereignissen und sogar geteilten Kalendern in Enterprise-Umgebungen. Ein besonderer Vorteil ist die Unterstützung von sowohl persönlichen als auch freigegebenen Kalendern, was in Organisationen mit Teamkalendern oder Ressourcenkalendern entscheidend ist. Die Implementierung nutzt OAuth2 für die Authentifizierung, wobei Microsoft umfangreiche Berechtigungsscopes anbietet (Calendars.ReadWrite, Calendars.ReadWrite.Shared), um granulare Kontrolle über die Zugriffsrechte zu ermöglichen. Der Synchronisationsmechanismus entspricht dem Google Calendar-Modell: bidirektionale Aktualisierung mit MyRMS als Quelle der Wahrheit, intelligente Konfliktauflösung und Import externer Einträge als neue Projekte.

#### D1.3 Apple Calendar (iCal)

Für Apple Calendar und andere iCal-kompatible Systeme wird eine One-Way-Integration mittels iCal-Feed realisiert. Dies ist ein leichtgewichtiger Ansatz: MyRMS stellt eine eindeutige iCal-Feed-URL bereit, die der Benutzer in Apple Calendar, Thunderbird oder anderen kompatiblen Anwendungen abonnieren kann. Das System aktualisiert diesen Feed automatisch alle sechs Stunden mit den neuesten Terminänderungen. Der Vorteil dieser Lösung ist, dass keine Authentifizierung erforderlich ist – der Feed kann einfach durch URL-Sharing weitergeleitet werden. Dies macht es ideal für Szenarien, in denen externe Partner (wie Kunden oder Lieferdienste) die Termine sehen sollen, ohne Zugriff auf das vollständige MyRMS-System zu haben.

---

### D2. Email & Communication Integrations

**Effort:** M pro Integration (2-3 Wochen)
**Priorität:** MEDIUM
**Impact:** Vereinheitlichter Kommunikations-Hub, verbesserte Kollaboration, nahtlose Benachrichtigungen

#### Ausgangssituation

Das aktuelle MyRMS-System verfügt über eine funktionsfähige IMAP-Integration (ImapMailService), die das Empfangen und Beantworten von Emails ermöglicht. Allerdings gibt es Limitationen: Es fehlt eine native Gmail API-Integration, die erweiterte Funktionen wie Thread-Management, Label-Synchronisation und besseres Attachment-Handling ermöglicht. Darüber hinaus existiert keine Slack- oder Teams-Integration, die kritische Benachrichtigungen direkt an die Kommunikationsplattformen der Benutzer leitet.

#### D2.1 Gmail API Integration

Die Implementierung der Gmail API bietet erhebliche Verbesserungen gegenüber reinem IMAP. Während IMAP ein Text-Protokoll mit begrenzten Funktionen ist, stellt die Gmail API eine moderne RESTful-Schnittstelle bereit, die speziell für die Gmail-Infrastruktur optimiert ist. Das Thread-Management ist in Gmail API nativer und zuverlässiger: Zusammenhängende Emails werden korrekt als Conversation erkannt, was für Miet- und Kundenkommunikation entscheidend ist. Label-Synchronisation ermöglicht es, MyRMS-Projekte als Gmail-Labels abzubilden – eine Email für Projekt „Konferenz 2026" könnte automatisch mit dem Label „project/conference-2026" versehen werden, was die Organisation im Gmail-Interface verbessert. Das Read-Receipt-Tracking und Attachment-Handling funktioniert zuverlässiger, da die API optimiert ist: Anhänge werden gestreamt statt gepuffert, was bei großen Dateien Speicher spart.

Die Implementierung beginnt mit OAuth2-Authentifizierung mit den erforderlichen Scopes (gmail.readonly, gmail.modify). Optional können MyRMS-Projekte bidirektional mit Gmail-Labels synchronisiert werden – ein benutzeroptionales Feature, das manche Organisationen als Workflow-Verbesserer sehen. Das Markieren als Gelesen/Ungelesen synchronisiert in beide Richtungen: Liest der Benutzer eine Email in Gmail, wird sie auch in MyRMS als gelesen markiert. Archivierung und Löschung werden ebenfalls synchronisiert, was ein einheitliches Mailbox-Management ermöglicht.

#### D2.2 Slack Integration

Die Slack-Integration transformiert MyRMS von einem isolierten System zu einer Komponente des täglichen Kommunikationsworkflows. Installation erfolgt als Workspace App: Ein Administrator klickt auf „Slack verbinden", was zur Slack-Autorisierungsseite führt. Der Administrator autorisiert die erforderlichen Bot-Berechtigungen (chat:write, channels:read, users:read), und der Bot tritt dem Workspace bei. Von diesem Punkt an kann MyRMS proaktiv Benachrichtigungen an Slack-Kanäle entsenden.

Konkrete Benachrichtigungsszenarien verbessern die Betriebseffizienz erheblich. Ein Beispiel: Sollte eine Rechnung überfällig sein, sendet MyRMS eine Alert-Nachricht wie „⚠️ Unbezahlte Rechnung RE-2026-0001 fällig in 1 Tag" an einen konfigurierten Kanal, beispielsweise #finance oder #urgent-alerts. Wenn ein neues Mietprojekt erstellt wird, informiert eine Nachricht wie „📋 Neue Vermietung von Acme Corp - €5000" den Sales- und Operations-Team. Sollte ein Asset mit Beschädigungen zurückgegeben werden, generiert MyRMS sofort eine Alarm-Nachricht wie „🚨 Schadenmeldung für Lampe #123" an den Asset-Management-Team. Crew-Zuweisungen ändern sich häufig kurzfristig; eine Benachrichtigung wie „👥 Projekt-Team aktualisiert" hält das Team synchron. Eine tägliche Briefing-Nachricht wie „📊 Heute: 3 Lieferungen, 2 Rückgaben, €12k Umsatz" gibt Managern einen Überblick über die tägliche Geschäftsaktivität.

Slack Commands erweitern die Interaktivität: Benutzer können `/myms project [Suchbegriff]` eingeben, um Projekte zu suchen, ohne MyRMS zu öffnen. `/myms invoice [Rechnungsnummer]` zeigt Rechnungsdetails direkt im Slack. `/myms asset [Asset-Name]` überprüft die Verfügbarkeit. `/myms team [Datum]` beantwortet die Frage „Wer arbeitet heute?" Die Implementierung dieser Commands erfolgt über Slack's slash command API, wobei MyRMS einen Webhook registriert, der auf diese Befehle antwortet.

#### D2.3 Microsoft Teams Integration

Microsoft Teams ist in vielen deutschen Unternehmensumgebungen der Standard für Kommunikation und Kollaboration, besonders bei Microsoft-365-Kunden. Die Integration mit Teams folgt dem Slack-Muster mit Anpassungen an Teams-spezifische Features. Die Basis ist eine Teams App Installation, bei der ein Administrator die App zum Teams-Workspace hinzufügt. Im Gegensatz zu Slack kann MyRMS sich in Teams als ein Tab in Kanälen präsentieren – ein „MyRMS Dashboard"-Tab könnte in einem #operations-Kanal eingebettet werden, das Echtzeitdaten anzeigt. Webhooks ermöglichen Benachrichtigungen ähnlich wie in Slack: Adaptive Cards (Teams' Benachrichtigungsformat) ersetzen Slack-Messages. Diese Cards können reichhaltige, interaktive Inhalte darstellen – beispielsweise eine Schadenmeldungs-Card mit Bildern, Kosten und Action-Buttons zum Akzeptieren oder Ablehnen.

---

### D3. Accounting & ERP Integrations

**Effort:** L pro Integration (3-5 Wochen)
**Priorität:** MEDIUM
**Impact:** Eliminiert manuelle Buchhaltung, reduziert Fehlerpotenzial, echtzeitliche Finanzabstimmung

#### Ausgangssituation und Compliance-Kontext

Der deutschsprachige Markt hat spezifische Anforderungen im Buchhaltungsbereich. Derzeit unterstützt MyRMS den Export zu DATEV (Standard für deutsche Steuerberater) und generische CSV-Exporte zu Cloud-Accounting-Systemen. Es fehlt eine echtzeitliche, bidirektionale Synchronisation. Der regulatorische Kontext ist kritisch: Die E-Rechnungs-Compliance-Anforderungen verschärfen sich rapide in Deutschland und der EU.

**E-Rechnungs-Compliance-Zeitplan:**
- Januar 2025: Pflicht zum Empfang von E-Rechnungen
- Januar 2027: Ausstellung von E-Rechnungen für Unternehmen mit >€800k Jahresumsatz wird Pflicht
- Januar 2028: Ausstellung von E-Rechnungen wird für ALLE Unternehmen Pflicht

Die Einhaltung dieser Richtlinien erfordert EN 16931-Compliance und ZUGFeRD 2.0.1+ oder XRechnung-Format bei Lieferungen an öffentliche Auftraggeber.

#### D3.1 Echtzeitliche Cloud-Accounting-Synchronisation

Das deutschsprachige Marktführer im Cloud Accounting sind Sevdesk und Lexoffice (Bundesland-weit anerkannt). Eine bidirektionale Synchronisation mit diesen Systemen bedeutet deutlich reduzierte manuelle Buchhaltungsarbeit.

Der Push-Mechanismus funktioniert wie folgt: Wenn in MyRMS eine Rechnung erstellt wird (beispielsweise eine Mietrechnung über €3500 für eine Event-Vermietung), wird diese automatisch in Echtzeit oder nach konfigurierbarem Timing zu Sevdesk/Lexoffice gepusht. Das externe System erstellt automatisch die entsprechende Rechnung in der Cloud, mit allen Positionen, Steuern und Kundendaten korrekt synchronisiert. Die Herausforderung liegt in der Konfliktauflösung: Was geschieht, wenn Beträge nicht übereinstimmen? Das System muss einen Benutzer-Alert generieren statt blind zu synchronisieren.

Der Pull-Mechanismus ist ebenso wertvoll: Wenn Zahlungen über das Bankkonto eingehen (Kunde bezahlt per SEPA), synchronisiert sich diese Information automatisch von der Bank über das Buchhaltungssystem zurück zu MyRMS. Im optimalen Fall: Zahlung wird von der Bank erfasst → Buchhaltungssystem markiert Rechnung als bezahlt → MyRMS aktualisiert Projektenstatus zu „Zahlungsempfangen". Dies schafft einen schließenden Kreis ohne manuelle Intervention.

Das Synchronisations-Triggering erfolgt auf mehreren Ebenen. Die automatische Synchronisation occurs beim Erstellen oder Ändern von Rechnungen – eine kritische Operation, die sofort gepusht werden sollte. Ein manueller Batch-Sync-Button ermöglicht Benutzer, auf Demand eine Synchronisation zu fordern, falls Probleme vermutet werden. Ein geplanter Daily-Reconciliation-Job läuft beispielsweise um 2:00 Uhr nachts und gleicht alle offenen Posten ab, gibt Bericht über Diskrepanzen.

Die Konfliktauflösungs-Strategie ist robust:
- Betragsdifferenz: Das System erkennt, wenn Rechnungsbetrag in MyRMS vs. Buchhaltung nicht übereinstimmt (z.B. Manualbuchung in Sevdesk). Es generiert einen Alert, statt zu synchronisieren. Benutzer muss die Quelle der Wahrheit aktualisieren.
- Duplikat-Erkennung: Rechnungsnummern-Abgleich stellt sicher, dass nicht dieselbe Rechnung zweimal erfasst wird.
- Kunde nicht gefunden: Falls ein Kunde in MyRMS existiert, aber nicht in der Buchhaltung, erstellt das System den Kunden automatisch mit Standarddaten.
- Audit-Trail: Jede Synchronisationsaktion wird protokolliert – wer hat wann was synchronisiert, Erfolg oder Fehler, Geldbeträge.

#### D3.2 DATEV Integration

DATEV bleibt der Standard für deutsche Steuerberater und Buchhaltungen. Während Sevdesk/Lexoffice für KMUs geeignet sind, verlangen größere Unternehmen oder solche mit Steuerberatung oft DATEV-Kompatibilität. MyRMS muss DATEV-Export-Formate (ASCII-Format, CSV-Varianten) unterstützen, idealerweise bidirektional über APIs, falls verfügbar. Der Export sollte alle relevanten Geschäftsfälle abbilden: Vermietungseinnahmen, Reparaturkosten, Abschreibungen, USt-Vorauszahlungen.

#### D3.3 Xero & QuickBooks Online Integration

Für international tätige Unternehmen oder solche mit anglophonen Märkten ist Xero relevant. QuickBooks Online ist verbreitet im US-amerikanischen und englischsprachigen Markt. Beide Systeme bieten APIs:
- **Xero:** Two-Way Sync für Rechnungen, Kunden, Zahlungen. Automatische Abstimmung. GL-Mapping: Asset-Kategorien → Bilanzkonten.
- **QuickBooks Online:** Umfangreichste Integration. Bank-Feed Import von Transaktionen. Tax-Kategorie-Mapping. Multi-Währungs-Unterstützung für internationale Vermietungen.

---

### D4. Payment Processing Integrations

**Effort:** M pro Integration (2-3 Wochen)
**Priorität:** MEDIUM
**Impact:** Beschleunigte Zahlungseingänge, reduzierte Zahlungsausfallquoten, flexiblere Zahlungsoptionen

#### Ausgangssituation

MyRMS bietet derzeit Stripe Billing für SaaS-Abonnements an (für die Plattform selbst). Es existiert aber kein integriertes Zahlungslink-System für Kundenrechnungen und keine Multi-Payment-Method-Unterstützung wie SEPA-Überweisung oder PayPal.

#### D4.1 Payment Gateway Integration

**Stripe Payment Links:**
Die Implementierung von Stripe Payment Links transformiert jede Rechnung in eine sofort bezahlbar-machen-können Form. Wenn in MyRMS eine Mietrechnung erstellt wird, generiert das System automatisch einen Stripe Payment Link (eine eindeutige URL) und embettet sie in die PDF-Rechnung oder sendet sie per Email. Der Kunde klickt den Link, wird auf eine Stripe-gehostete Checkout-Seite geleitet, kann mit Kreditkarte, Apple Pay, Google Pay, oder anderen Methoden zahlen. Stripe sendet ein Webhook zurück an MyRMS: „Payment received" → MyRMS markiert die Rechnung automatisch als bezahlt. Die Gebührenhandhabung ist konfigurierbar: Optional kann MyRMS die Stripe-Transaktionsgebühr (ca. 1,4% + €0,25) zur Rechnung hinzufügen, oder das Unternehmen trägt die Gebühr selbst.

**PayPal Integration:**
Eine PayPal-Schaltfläche wird in jede Rechnungsanzeige embedded. Kunden, die PayPal-Accounts bevorzugen, klicken die Schaltfläche, werden zu PayPal weitergeleitet, bestätigen die Zahlung. PayPal sendet IPN (Instant Payment Notifications) an MyRMS zurück. Optional kann MyPal recurring payments unterstützen, falls das Unternehmen Abo-Vermietungen anbietet.

**Bank Transfer (SEPA):**
Deutsche Kunden bezahlen häufig per Banküberweisung. MyRMS sollte GiroCode-QR-Codes auf Rechnungen generieren (German standard, basierend auf ISO 20022). Ein GiroCode enthält QR-kodiert: Empfängerkonto (IBAN), Name, Betrag, Referenz (Rechnungsnummer). Kunden mit moderner Banking-App scannen den QR-Code, alle Daten sind vorausgefüllt, sie klicken bestätigen. Für Auto-Reconciliation wird ein Bank-Feed integriert: Die Bank übermittelt täglich elektronisch, welche Überweisungen eingegangen sind (MT940-Format oder Open Banking APIs). Das System matched automatisch: „Zahlung €3500 mit Referenz RE-2026-001" wird mit der entsprechenden Rechnung gematcht und als bezahlt markiert.

**Cash Payment Tracking:**
Für Bargeld-Vermietungen (häufig in Event-Management) muss das System manuelle Kasseneingaben unterstützen. Ein Benutzer kann auf einem Tablet oder POS-Terminal (vor Ort am Delivery-Point) eine Zahlung erfassen: Projekt-ID, Betrag, wer hat das Geld entgegengenommen, Notizen. Das System timestampt diese Transaktionen und ermöglicht Offline-Betrieb: Falls Internetverbindung ausfällt, werden Zahlungen lokal gepuffert und später synchronisiert, wenn Konnektivität zurückkommt. Für Auditing: Alle Cash-Zahlungen sind nachverfolgbar mit Benutzername, Zeitstempel, Betrag.

#### D4.2 Subscription Billing (SaaS)

Für Unternehmen, die monatliche oder jährliche Mietverhältnisse mit Kunden haben (nicht einzelne Events), ist Subscription Billing relevant. Das System unterstützt automatische monatliche/jährliche Rechnungsgenerierung und automatische Zahlungseinzüge. Dunning Management: Falls eine Kartenzahlung fehlschlägt, versucht das System mehrfach mit exponenzieller Backoff-Strategie erneut. Nach konfigurierbaren Versuchen wird der Kunde benachrichtigt.

Usage-based Billing: Falls ein Kunde vertraglich eine Basis-Ausrüstungsmenge hat, aber zusätzliche Assets mietet, berechnet das System automatisch Zusatzgebühren. Tiered Pricing: Großkunden mit Jahresverträgen erhalten Mengenrabatte – automisch konfigurierbar im System.

---

### D5. Document & Signature Services

**Effort:** M pro Integration (2-3 Wochen)
**Priorität:** LOW-MEDIUM
**Impact:** Rechtliche Compliance, schnellere Verträge, digitale Papiervermeidung

#### D5.1 DocuSign Integration

DocuSign ist der Marktführer für elektronische Signaturen in der EU und erfüllt eIDAS-Anforderungen für digital signable Dokumente. Typische Use Cases in MyRMS sind: Mietvereinbarungen (Kundenunterzeichnung), Lieferscheine (Bestätigung durch den Kunden, dass er Assets erhalten hat), Schadensfreiheitsbescheinigungen, Versicherungsdokumentationen.

Der Workflow ist automatisiert: Ein Mitarbeiter generiert aus einer MyRMS-Vorlage ein PDF-Dokument (z.B. Mietvertrag mit ausgefüllten Kundendetails, Asset-Liste, Konditionen). Ein Button „Send to DocuSign" leitet das PDF zu DocuSign weiter. MyRMS konfiguriert automatisch, wo Signaturen erforderlich sind (beispielsweise Kunde unterschreibt unten, Betriebsleiter unterschreibt unten). DocuSign versendet den signierten Link per Email an den Kunden. Der Kunde öffnet den Link, sieht das Dokument, unterschreibt digital (Canvas-basiert oder Pin-Protected). DocuSign sendet ein Webhook an MyRMS: „Document signed by customer". MyRMS speichert das signierte PDF im Dokumenten-Management-System, markiert das Projekt als „contract signed", notifiziert das Finance-Team automatisch.

#### D5.2 Adobe Sign Integration

Adobe Sign ist alternative mit stärkerer PDF-Handling-Kompetenz (Adobe ist Erfinder von PDF). Für Unternehmen mit Adobe-Licensing ist dies eine nahtlose Integration. Enterprise-Features wie komplexe Workflow-Automatisierung (Mehrfach-Unterzeichner, bedingte Felder) sind robuster als bei DocuSign.

#### D5.3 In-App Digital Signature

Eine leichtgewichtige Alternative: MyRMS implementiert Canvas-basierte Unterschrift im App selbst. Ein Benutzer öffnet ein Dokument im Browser, sieht ein Zeichnungsfeld, unterschreibt mit Maus/Touch/Stylus. Das System erfasst:
- Signatur als Bild (PNG mit Transparenz)
- Timestamp der Unterzeichnung
- Benutzer-ID (digitaler Fingerabdruck)
- Digitaler Hash des Dokuments (für Manipulations-Erkennung)

Diese Lösung ist in vielen Jurisdiktionen legal gültig (eIDAS-konform, wenn Hash-Validation vorhanden). Kosten: Null (keine Third-Party-Service). Geeignet für: interne Dokumente (Checklisten, Schaden-Reports, interne Genehmigungen), Kundenquittungen. Nicht geeignet für: hochgradig rechtlich bindende Verträge, da keine qualified signature.

---

### D6. Marketplace & Logistics Integrations

**Effort:** L pro Integration (4-6 Wochen)
**Priorität:** LOW
**Impact:** Omnichannel-Vermietung, Marktgröße-Expansion, neue Revenue-Streams

#### D6.1 Shopify Integration

Ein Eventunternehmen möchte seine Ausrüstung über seinen Shopify-Shop vermieten. Kunde findet in Shopify einen „LED-Panel-Vermietung" Product (3-Tage-Vermietung), legt es in den Warenkorb, kauft. Shopify sendet ein Order Webhook an MyRMS. MyRMS erstellt automatisch ein Projekt: Kunde = Shopify-Kundendaten, Assets = LED-Panels × Menge, Dauer = 3 Tage ab morgen. Das Operationsteam sieht es in der Dispatch-Board, plant Lieferung und Abholung. Payment ist bereits in Shopify verarbeitet. Wenn der Kunde das Equipment zurückgibt, löst dies einen MyRMS-Rückgabeprozess aus; optional generiert Shopify eine Gutschrift oder Rückerstattung.

Inventory-Sync ist kritisch: Shopify muss in Echtzeit wissen, wie viele LED-Panels verfügbar sind. Das System durchläuft tägliche Updates (oder echtzeitlich mit Event-basierten Webhooks): Nach jeder Vermietung reduziert sich die verfügbare Menge in Shopify. Nach Rückkehr und Qualitätsprüfung wird die Menge wieder erhöht. Das Ziel: Shopify zeigt nie einen Stock von 0, wenn tatsächlich noch Panels verfügbar sind, und overbookt nie.

#### D6.2 DHL / FedEx / UPS Integration

Für Equipment, das versandt wird (nicht vor Ort geliefert wird), werden Versand-Integrations benötigt. MyRMS kann in Echtzeit von den Carrier APIs Versand-Gebühren abfragen: „Was kostet es, dieses 30kg-Paket von Frankfurt zu München in 2 Tagen zu versenden?" Das System kalkuliert automatisch Versandkosten und kann diese auf die Mietrechnung aufschlagen. Der Mitarbeiter erstellt im System einen Versand-Job: Assets, Empfänger-Adresse, Versandart. Das System generiert automatisch einen Shipping Label (als PDF oder direkt an DHL), der ausgedruckt und auf das Paket geklebt wird. Das Tracking wird live in der MyRMS-Dispatch-Board angezeigt: „Status: bei DHL, in Zustellung zu Kundenadresse". Der Kunde erhält automatisch einen Tracking-Link per Email.

#### D6.3 Rental Marketplace Integration

Ein innovatives Modell: MyRMS-Anwender können ihre verfügbare Ausrüstung auf Marktplätzen wie Grover (für Consumer-Electronics), Turo (für Fahrzeugvermietung) oder branchespezifischen Plattformen (EventAPI.com, EquipmentShare.io) listen. Der Anwender konfiguriert einmalig: „Diese Asset-Kategorien auf Marketplace X listen, mit automatischen Verfügbarkeitsupdates". Von dann an:
- Kunde mietet Equipment auf Marketplace
- Marketplace sendet Order-Webhook an MyRMS
- MyRMS erstellt Projekt, reserviert Assets
- Operations-Team behandelt wie jede andere Vermietung
- Nach Rückgabe: Asset verfügbar auf Marketplace

Challenge: Channel-Konflikt-Auflösung. Wenn Kunde A auf Marketplace bucht und gleichzeitig direkt MyRMS kontaktiert für dieselben Assets, welche Buchung gewinnt? Das System muss eine erste-Anfrage-gewinnt-Regel implementieren: Der erste Webhook triggert die Reservierung, die zweite Anfrage erhält einen „Nicht verfügbar"-Status.

---

### D7. HR & Payroll Integrations

**Effort:** M pro Integration (2-3 Wochen)
**Priorität:** LOW
**Impact:** Crew-Payroll-Automatisierung, vereinfachte Lohnabrechnung, Kosteneffizienz

#### D7.1 Crew Payroll Integration

Das aktuelle System verwaltet Crew-Zuweisungen zu Projekten, berechnet aber keine Lohnbestandteile. Wenn Crew-Integration implementiert wird: Ein Arbeiter wird einem Projekt zugewiesen, arbeitet von 9:00-18:00 (9 Stunden). Das System erfasst diese Stunden automatisch. Am Monatsende exportiert MyRMS einen Bericht: „Employee X: 162 Stunden regulär, 8 Stunden Wochenende (Zuschlag 50%), 4 Stunden Nacht (Zuschlag 25%)". Ein Payroll-Software-Integration (Paychex, ADP, oder regional ein deutscher Provider wie Lohngestion.de, Cis easyLohn) synchronisiert diese Daten. Das Payroll-System berechnet automatisch: Brutto-Lohn, Lohnsteuern, Krankenversicherung, Rentenversicherung. Optional können Spesensätze erfasst werden (Fahrt, Mahlzeiten) und automatisch hinzugefügt. Das endgültige Gehalt wird berechnet und zur Überweisung bereitgestellt.

#### D7.2 Employee Scheduling Optimization

Ein predictive Feature: MyRMS analysiert die Projekt-Pipeline der nächsten Wochen und prognostiziert den Crew-Bedarf. „Basierend auf den nächsten 10 Projekte brauchen Sie am 5. März 12 Crew-Mitglieder, davon 3 Techniker und 2 Lager-Mitarbeiter". Ein Optimierungs-Algorithmus empfiehlt: Schichten-Anordnung, um Lücken zu minimieren und Burnout zu vermeiden. Skill-Matching: Technische Projekte erfordern Crew mit Elektro-Zertifikation; das System priorisiert die Zuweisung dieser Mitarbeiter zu qualifizierten Jobs.

---

## Part E: Implementation Roadmap

### Timeline & Phasing Strategy

Die Implementierung ist in 6 Phasen über 12 Monate strukturiert. Jede Phase hat klare Deliverables und Go/No-Go-Checkpoints.

#### Phase 1: Foundation (Monate 1-2) — Effort: 6-8 Wochen

**Fokus:** High-Impact UX/UI-Verbesserungen, architektonische Grundlagen

Diese erste Phase adressiert die sichtbarsten Pain-Points und bereitet das technische Fundament vor. Die Dashboard-Überarbeitung ist ein Quick-Win: Das aktuelle Dashboard zeigt Asset-Counts und Projekt-Status, aber nicht die kritischsten Business-Metriken. Ein überarbeitetes Dashboard zeigt prominent: Umsatz month-to-date (fett, oben), Anzahl offener Rechnungen (warnt vor Zahlungsausfallrisiko), Asset-Auslastungs-Prozentsatz (zeigt operative Effizienz). Diese drei KPIs sind sofort visuell erfassbar.

Sidebar-Restrukturierung adressiert Navigation-Chaot: Das aktuelle Menü hat zu viele Ebenen. Die neue Struktur könnte sein: Dashboard, Projects (mit Submenü: New, In Progress, Completed), Assets (Inventory, Maintenance), Customers, Finance (Invoices, Payments), Reports, Settings. Dies reduziert Klicks um ~30%.

Mobile Responsiveness ist derzeit suboptimal. Bootstrap 4 wird zu Bootstrap 5 migriert. Media Queries für xs/sm Breakpoints (Smartphones) werden repariert: Sidebar wird zu Hamburger-Menü, Tabellen werden vertikal gestapelt, Touch-Ziele (Buttons) werden mindestens 44×44px groß. Der Fokus ist auf den top 5 häufigsten Pages: Dashboard, Project List, Asset List, Invoice List, Dispatch Board.

**Deliverables dieser Phase:**
- Neues Dashboard mit Revenue-KPI sichtbar (nicht versteckt in Submenüs)
- Navigation ist 30% flacher und schneller navigierbar
- Smartphones zwischen 320-480px Breite funktionieren korrekt
- DI-Container (Dependency Injection) ist Skeleton in place – noch nicht alle Services verwenden ihn, aber das Framework für Nachmigrationen existiert
- 5 vorgefertigte Report-Templates (z.B. „Monatliche Vermietungsübersicht", „Top 10 Kundenrechnungen", „Equipment-Wartungsbericht")

**Team:** 3 Frontend, 1 Backend
**Effort:** 960 Stunden
**Kosten:** ca. €115k (bei €120/h Rate)

---

#### Phase 2: API & Real-Time (Monate 3-4) — Effort: 8-10 Wochen

**Fokus:** Moderne Architektur, Echtzeit-Features, erweiterte Datenabfragen

Eine RESTful API v2 wird implementiert. Ausgangslage: Das System hat aktuell nur web-based HTML-Interface. Moderne Clients (Mobile Apps, Drittanbieter-Integrationen, externe Dashboards) benötigen APIs. Die API v2 deckt die häufigsten Abfragen ab: Asset-Verfügbarkeit abfragen (GET /api/v2/assets?available=true&category=projectors), Projekt erstellen (POST /api/v2/projects), Rechnung abrufen (GET /api/v2/invoices/{id}), Zahlungen registrieren (POST /api/v2/invoices/{id}/pay).

WebSocket-Server für Echtzeit-Dispatch-Board: Die aktuelle Dispatch-Board aktualisiert sich, wenn der Benutzer die Seite neu lädt. Mit WebSockets: Wenn ein neues Projekt erstellt wird, sehen alle angemeldeten Dispatch-Mitarbeiter augenblicklich die neue Zeile. Wenn der Status eines Projekts von „Planned" zu „In Delivery" ändert, aktualisiert sich das Board live ohne Reload. Dies ist ein Productivity-Multiplier für Operators.

Advanced Filters auf List Views: Benutzer können derzeit Projekte nur nach Kundenname filtern. Phase 2 fügt Multi-Filter hinzu: Projekte filtern nach Datum, Umsatz, Asset-Typ, Status, Crew-Mitglied zugewiesen. Dies ist ein großer UX-Improvement.

Report Scheduling & Automation: Reports können geplant werden („Jeden Montag 6:00 Uhr die Wochenübersicht per Email senden"). Das System nutzt den bestehenden Cron-Job-Daemon, fügt aber eine UI hinzu.

Redis Caching Layer: Die Datenbankabfragen werden gecacht. z.B.: „Alle Projekte im März" ist eine häufige Abfrage; das Ergebnis wird 1 Stunde lang in Redis gepuffert. Nächste Abfrage ist 100× schneller. Dies verbessert Responsiveness bei vielen gleichzeitigen Benutzern.

**Deliverables dieser Phase:**
- RESTful API v2 mit Authentifizierung (Bearer Token), ca. 20 Endpoints live
- Live Dispatch Board mit WebSocket-Updates
- Filter-Panel auf Asset List, Project List, Customer List, Invoice List
- Automated Report Delivery funktioniert (E-Mails werden reliable versendet)
- Caching ist konfiguriert, P95 Latency der Abfragen < 300ms

**Team:** 4 Backend, 1 Frontend
**Effort:** 1280 Stunden
**Kosten:** ca. €154k

---

#### Phase 3: Quality & Scale (Monate 5-6) — Effort: 6-8 Wochen

**Fokus:** Performance, Reliability, umfassende Test-Coverage, Security-Audit

Job Queue System: Schwere Operationen (PDF-Generierung für 100 Rechnungen, Bild-Optimierung bei Upload, Daten-Export) blockieren derzeit den Web-Request. Mit einem Queue-System (z.B. Redis Queue) werden diese Jobs asynchron ausgeführt. Benutzer klickt „Bulk-Export", erhält sofort eine Bestätigung „Export wird bearbeitet, Sie erhalten eine Email in 2 Minuten", statt 30 Sekunden zu warten.

Logging & Error Handling: Structured Logging (alle Log-Einträge als JSON) wird implementiert. Monolog ist die Standard-Library. Statt Log-Text „User tried to create invoice", ist es JSON: `{timestamp: "2026-03-18T10:25:00Z", level: "ERROR", user_id: 123, action: "create_invoice", error_code: "INSUFFICIENT_PERMISSIONS", detail: "User 123 has no role 'finance'"}`. Dies macht Debugging und Monitoring möglich.

Database Optimization: Mit wachsender Datamenge werden Abfragen langsamer. Ein DBA optimiert: Indizes werden hinzugefügt (z.B. Index auf (project_id, status) statt nur project_id). Query-Pläne werden analysiert. Partitionierung wird ggf. für sehr große Tabellen implementiert.

Security Hardening: Ein externer Security-Consultant führt einen Penetrations-Test durch (OWASP 2025 Checkliste). Common vulnerabilities: SQL Injection, XSS, CSRF. Das System wird gehärtet. Neue Sicherheits-Kategorien 2025: „Insecure AI Integration" und „Generat. AI-basing Threats" werden adressiert, falls AI-Features geplant.

Analytics Expansion: Das bisherige Reporting ist statisch (Tabellen). Phase 3 fügt Analytics hinzu: Revenue-Trend (Graphik), seasonality-Analyse, ROI pro Asset-Typ, Customer Lifetime Value, churn-Analyse.

**Deliverables dieser Phase:**
- Queue System operational (Bulk-Operationen sind jetzt async)
- Structured Logging im Production
- Database Queries optimiert (p99 < 200ms)
- Security Audit passed (kein kritischer Findings)
- Analytics Dashboard mit 8+ Visualisierungen

**Team:** 3 Backend, 1 Frontend, 1 DevOps
**Effort:** 960 Stunden
**Kosten:** ca. €115k

---

#### Phase 4: Integrations & Languages (Monate 7-8) — Effort: 6-8 Wochen

**Fokus:** Third-Party-Integrationen, Internationalisierung

Multi-Language Expansion: Das System wird zu 100% auf Deutsch übersetzt (derzeit ~70%). Englisch wird als zweite Sprache vollständig unterstützt. Dies erfordert ca. 2000 Übersetzungs-Keys. Ein Translator wird engagiert oder ein in-house Speaker-Developer macht es. Die i18n-Architektur wird implementiert (typischerweise Laravel Localization oder Symfony Translation). Frontend und Backend beide mit Sprachschalter.

Google Calendar + Outlook Sync: (Siehe Part D1)

Sevdesk/Lexoffice Bidirectional Sync: (Siehe Part D3.1)

Webhook Framework: Eine Infrastruktur, damit MyRMS externe Apps notifizieren kann („Projekt erstellt" → webhook senden an externe System). Dies ist critical für Marketplace-Integrations später.

Dark Mode Implementation: CSS wird umstrukturiert mit CSS Custom Properties (--primary-color, --background-color, etc.). Im Browser wird ein Theme-Switcher in der User-Pref angeboten. Das System sichert die Pref in localStorage. Dies ist ein modem Expect-Feature.

**Deliverables dieser Phase:**
- Deutsche + English vollständig lokalisiert (2000+ Keys)
- Google Calendar bidirektional sync live
- Outlook/Microsoft 365 Calendar sync live
- Sevdesk bidirektional Sync (Invoices, Payments) live, tested
- Webhook Framework ready for 3rd-party apps
- Dark Mode toggle in Header

**Team:** 2 Backend, 1 Frontend, 1 Translator
**Effort:** 960 Stunden
**Kosten:** ca. €115k

---

#### Phase 5: Mobile & AI (Monate 9-10) — Effort: 8-10 Wochen

**Fokus:** Mobile-first Features, AI-gestützte Automatisierung, Customer Self-Service

Native Mobile App (React Native MVP): Ein iOS + Android App wird entwickelt. MVP-Scope: Dispatch-Board (Live-Updates via WebSocket), Project Details ansehen, Assets checken/zurückgeben mit QR-Code Scanner (Camera API), Notes hinzufügen. Offline-Funktionalität: Der Scanner funktioniert auch ohne Internetverbindung, QR-Daten werden lokal gepuffert und später synchronisiert. Dies ist ein großer Produktivitätsschub für Field-Worker.

AI Features:
- Demand Forecast: Basierend auf historischen Daten (letzte 24 Monate) prognostiziert ML-Modell Nachfrage für die nächsten 8 Wochen. „Märzüblich brauchen Sie 40% mehr LED-Panels", warnt vor Engpässen.
- Dynamic Pricing: Ein Algorithmus analysiert Nachfrage + Verfügbarkeit. In der High-Season kann das System automatisch Preise um 20% erhöhen (Benutzer kann Grenzen setzen). Dies maximiert Revenue ohne Overbooking.

Customer Self-Service Portal: Ein Login-Geschützter Bereich für Kunden, wo sie ihre Mietprojekte sehen, Rechnungen downloaden, Zahlungen machen, Support-Tickets erstellen. Dies reduziert Support-Tickets um ~25%.

Multi-Format Export: PDFs, Excel, CSV für alle Reports.

**Deliverables dieser Phase:**
- Native iOS + Android App mit QR-Scanner
- AI Demand Forecasting operational
- Dynamic Pricing Engine live (optional für Benutzer)
- Customer Portal launch
- Multi-format Exports

**Team:** 2 Frontend (React Native), 1 Backend (AI/ML), 1 DevOps
**Effort:** 1280 Stunden
**Kosten:** ca. €154k

---

#### Phase 6: Polish & Hardening (Monate 11-12) — Effort: 4-6 Wochen

**Fokus:** Bug-Fixes, Dokumentation, finale Performance-Tuning

Zusätzliche KPIs für Dashboard: A1.2-A1.4 (Profit Margin, Top Customer, Upcoming Returns) werden hinzugefügt.

Chart Library Migration: Ersatz einer älteren Chart-Lib (falls vorhanden) durch Chart.js oder D3.js. Alle Visualisierungen sind professionell.

Full Routing Migration: Die gesamte Anwendung wird auf die neue Routing-Infrastruktur migriert (falls noch nicht 100% in Phase 1-3 getan).

Unit Tests: 80% Code-Coverage ist das Ziel. Kritische Pfade (Payment, Invoice, Dispatch) haben 100% Coverage.

API Documentation: Swagger/OpenAPI Spec ist vollständig dokumentiert, interaktiv testbar über Swagger UI.

User Guides: Aktualisierte Bedienungsanleitungen für Endbenutzer und Administratoren.

**Deliverables dieser Phase:**
- Complete Dashboard (alle KPIs)
- Professional Charts throughout app
- 80%+ Test Coverage
- Full API documentation (Swagger)
- Updated user/admin guides

**Team:** 2 Backend, 1 Frontend, 1 QA
**Effort:** 320 Stunden
**Kosten:** ca. €38k

---

### Resource Requirements

#### Development Team Composition

Die empfohlene Teamgröße ist 3-5 Personen, abhängig von Phase:

**Frontend Developer (1-2):**
- HTML5, CSS3, JavaScript (modern, nicht nur jQuery)
- Twig Templating mastery
- Bootstrap 5+ responsive design
- Optional: React Native für Mobile App
- Erfahrung mit Accessibility (WCAG 2.1)
- WebSocket Client-Libraries (socket.io oder native WebSocket API)

**Backend Developer (1-2):**
- PHP 8.3+ mit modernem Code-Style
- MySQL/MariaDB Abfrage-Optimierung
- Microservices-Architektur, Service-Layer Pattern
- RESTful API-Design
- Docker + Containerisierung
- Queue-Systems (Redis, RabbitMQ)
- OAuth2 / OpenID Connect für Integrationen
- Sicherheit: OWASP 2025

**DevOps/Infra Engineer (0-1):**
- Docker, Docker Compose, evtl. Kubernetes
- CI/CD Pipelines (GitHub Actions)
- Monitoring & Alerting (Prometheus, Grafana, Sentry)
- Database Administration und Backup-Strategie
- Skalierung und Performance-Tuning

**QA/Tester (0-1):**
- Manual Testing mit Checklists
- Automated Testing (Selenium WebDriver, Cypress für E2E)
- Load Testing (Apache JMeter, k6)
- Performance Profiling
- Security Testing

**Specialist Support (as-needed):**
- Security Consultant (quarterly Security Audits)
- Database Tuning Expert (one-time optimization)
- Accessibility Auditor (WCAG 2.1 compliance)
- UX/Design Reviewer (monthly design reviews)

#### Effort Estimation Summary

| Phase | Duration | Team Size | Total Hours | Cost (€120/h) |
|-------|----------|-----------|------------|---------------|
| Phase 1 | 2 months | 3 people | 960h | €115,200 |
| Phase 2 | 2 months | 4 people | 1,280h | €153,600 |
| Phase 3 | 2 months | 3 people | 960h | €115,200 |
| Phase 4 | 2 months | 3 people | 960h | €115,200 |
| Phase 5 | 2 months | 4 people | 1,280h | €153,600 |
| Phase 6 | 1 month | 2 people | 320h | €38,400 |
| **Total** | **12 months** | **3-4 avg** | **5,760h** | **€691,200** |

*Anmerkung: €120/h ist eine typische deutsche Senior-Developer-Rate. In Ballungszentren (München, Berlin) €140-160/h; in Randregionen €90-110/h. Remote-Teams können günstiger sein.*

---

### Success Metrics & KPIs

#### Technical KPIs

- **API Performance:** p99 Latency < 200ms (99% der Requests sind schneller)
- **Dashboard Load Time:** < 2 Sekunden auf 3G-Netzwerk (simuliert mobile)
- **Test Coverage:** 80%+ Unit Tests, 60%+ Integration Tests
- **Uptime:** 99.9% SLA (max. 43 Min Ausfallzeit pro Monat)
- **Page Load Time:** Median < 1.5s, gemessen via Lighthouse oder WebPageTest

#### User Experience KPIs

- **Task Completion Time:** 30% Reduktion (gemessen: Vorher/Nachher)
  - Beispiel: "Rechnungen für März ausstellen" aktuell 8 Min, Ziel 5.6 Min
- **Error Rate:** < 0.1% (trck 404s, Validierungsfehler in Logs)
- **Mobile Usage:** % Sessions auf Mobile tracken (Ziel: 15-25% der Total Sessions)
- **Feature Adoption:** GA4 oder Plausible trackt: Wie % der Benutzer nutzen neue Features in erste Woche?
  - Ziel: Dark Mode: 30%, Advanced Filters: 70%, API Integrations: 50%
- **NPS (Net Promoter Score):** Quarterly Survey. Target: +50 (Ziel ist "Promoters" > "Detractors")

#### Business KPIs

- **User Retention:** Monthly Churn < 5% (ideal: 2-3%)
- **Feature Release Frequency:** Alle 2 Wochen Deployment (CI/CD)
- **Support Ticket Volume:** 20% Reduktion durch Automation + besseres UI
- **Revenue Impact:** Upsell Premium Features (Advanced Reports, Mobile App, Integrations)
  - Ziel: 10-15% der Benutzer upgraded zu Paid Plan
- **Competitive Position:** Feature Parity mit Competitors + neue Differentiators
  - Ziel: 3-5 Features, die Wettbewerber nicht haben

---

## Part F: Risk Assessment & Mitigation

### Technical Risks

| Risk | Impact | Probability | Severity | Mitigation Strategy |
|------|--------|-------------|----------|---------------------|
| **Database Migrate Issues** | Data loss, Downtime, Rollback required | Medium (40%) | Critical | Test migrations on staging clone, complete backup strategy, documented rollback procedure, rollback testing quarterly |
| **API Breaking Changes** | 3rd-party integrations break, clients unhappy | Medium (35%) | High | Strict API versioning (v1 still works, v2 is new), 6-month deprecation notice, deprecation warnings in responses |
| **WebSocket Scaling Issues** | Prod crashes under 500+ concurrent users | Low (15%) | Critical | Load testing with 1000+ concurrent users before deploy, auto-scale queue workers, circuit breaker pattern |
| **Timezone & Date Bugs** | Incorrect billing, scheduling conflicts, audit fails | Medium (45%) | High | Comprehensive test suite for all timezones, IANA timezone database updates quarterly, sample data with edge cases (DST transitions) |
| **Large File Uploads** | Memory exhaustion, OOM kills PHP, upload fails | Low (20%) | High | Streaming upload (chunked), 100MB max per file configurable, S3 multipart upload for files >50MB |
| **Third-Party API Outages** | Stripe down → can't accept payments, Slack down → alerts fail | Low (25%) | High | Graceful degradation (alerts queued offline), fallback email if Slack fails, SLA monitoring per provider with dashboards |
| **Race Conditions in Concurrency** | Double-booking assets, double-charging invoices | Low (10%) | Critical | Pessimistic locking on assets during reservation, transaction isolation level tested, load tests with concurrent requests |

### Business Risks

| Risk | Impact | Probability | Severity | Mitigation Strategy |
|------|--------|-------------|----------|---------------------|
| **Timeline Overrun** | Delayed revenue, team frustration, competitive pressure | High (60%) | High | Strict 2-week sprint discipline, Feature Freeze at 80% of roadmap completion, ruthlessly cut low-priority items, weekly burn-down charts |
| **Scope Creep** | Budget +30%, missed deadlines, team burnout | High (65%) | High | Prioritization framework (MoSCoW: Must/Should/Could/Won't), stakeholder sign-off for each phase, defer to Phase 2 anything not in Phase 1 scope |
| **Staffing Constraints** | Slow progress, single-point-of-failure if key dev leaves | Medium (50%) | High | Hire early (2-3 weeks before Phase 1 start), pair programming for knowledge transfer, detailed documentation of architecture, cross-training |
| **Dependency on External APIs** | Stripe API changes, Google Calendar schema changes | Medium (40%) | Medium | Active monitoring of API changelogs, subscribe to vendor newsletters, test environments with vendor sandboxes, fallback mechanisms |
| **Market Competition** | New competitor launches similar features, users leave | Medium (35%) | Medium | Continuous market research (monthly competitive analysis), user feedback loops (bi-weekly), rapid iteration (2-week releases) |
| **Vendor Lock-in** | Switch from Stripe → hard, switch from Sevdesk → complex data migration | Low (25%) | Medium | Design payment abstraction layer (multiple providers supported), data export functionality for accounting sync, documented migration procedures |

### Security Risks

| Risk | Impact | Probability | Severity | Mitigation Strategy |
|------|--------|-------------|----------|---------------------|
| **SQL Injection in New Code** | Data breach (customer data, invoices exposed) | Low (10%) | Critical | Code review mandatory for all SQL, parameterized queries ONLY (no string concat), SQLi testing in CI pipeline, OWASP SQLi tests |
| **CSRF/XSS in New UI** | Account takeover, session hijack | Low (15%) | High | Auto-escaping Twig templates, Content Security Policy (CSP) headers (CSP3), CSRF token on all state-changing forms, XSS testing in Cypress E2E tests |
| **API Authentication Bypass** | Unauthorized access to invoices, customer data | Low (10%) | Critical | Bearer token validation on every request, token expiry (15 min), refresh tokens (7 days), rate limiting per user (100 req/min), scope validation per endpoint |
| **Sensitive Data in Logs** | PII exposure (credit card last 4, IBAN, customer email in log files) | Medium (40%) | High | Redaction rules (regex patterns) on sensitive fields, log access controls (only ops/security can read), retention policy (30 days then delete) |
| **Third-Party Package Vulnerability** | npm/Composer package has XSS, used in MyRMS | Medium (35%) | High | Dependabot enabled (auto-scan for vulns), regular updates (monthly), software composition analysis tool (Snyk), security audit of top 10 dependencies |
| **Insider Threat** | Dev employee steals customer data or inserts backdoor | Low (5%) | Critical | Code review on all changes, SSH key rotation quarterly, VPN audit logs, database access logs, background checks for new hires |
| **Unencrypted Data at Rest** | Database breached, customer data readable | Low (10%) | Critical | TLS 1.3 for all network traffic, database encryption at rest (if cloud), API secrets never logged, .env file never committed to git |

### Mitigation Priorities

Die höchsten Prioritäten sind:
1. **Timeline Overrun** (Probability: 60%) — Schlimmster Fall: Projekt wird 6 Monate zu spät. Mitigation: Strict Sprint-Discipline, Feature Freeze.
2. **Scope Creep** (Probability: 65%) — Schlimmster Fall: Budget verdoppelt sich. Mitigation: MoSCoW-Framework, klare Phasen-Grenzen.
3. **Database & API Issues** (Probability: 35-40%) — Schlimmster Fall: Data Loss. Mitigation: Umfangreiche Testing, Backup-Strategie, Rollback-Pläne.

---

## Part G: Decision Points & Next Steps

### Critical Decisions Required

Diese Entscheidungen müssen innerhalb der nächsten 2 Wochen getroffen werden:

#### 1. Architecture Choice (C1)

**Frage:** Modernisieren wir die Code-Architektur zu einem strukturiertem Framework, oder halten wir das aktuelle procedural System?

**Option A: Lightweight Framework (Slim) + Incremental Migration**
- Implementiere Slim 4 (Micro-Framework) parallel zum bestehenden Code
- Neue Features werden in Slim/modern pattern entwickelt
- Alte Features werden schrittweise refaktoriert
- Vorteile: Testierbar, wartbar, moderne Development-Practices, Frameworks-Standardkonventionen
- Nachteile: Paralleles System für gewisse Zeit, Migrationsaufwand

**Option B: Stay Procedural (Defer Modernization)**
- Kein Framework, continue mit bestehenden procedural Code
- Schneller anfangs, aber technische Schuld wird riesig
- Nach 12 Monaten neuer Code wird schwer wartbar

**Empfehlung:** **Option A** (Framework). Begründung:
- Bessere Testierbarkeit (critical für Phase 3 Test Coverage Ziel)
- Langfristig ROI durch reduced maintenance costs
- Attraktiver für neue Developers (Recruitment leichter)
- API v2 und Integrationen sind leichter mit Framework

**Entscheidung bis:** 20. März 2026

---

#### 2. Real-Time Technology (B3)

**Frage:** Wie implementieren wir Live-Dispatch-Board Echtzeit-Updates?

**Option A: Native WebSocket (Ratchet/Workerman) + Redis**
- Ratchet ist eine PHP WebSocket Library
- Clients verbinden sich mit WebSocket Server
- Events (Projekt-Change) werden via Redis Pub/Sub gepropagiert
- Vorteile: Einfach, wenig Dependencies (nur Redis + Ratchet), native PHP
- Nachteile: Ratchet ist nicht super aktiv maintained

**Option B: Socket.io Library (Node.js Fallback)**
- Socket.io hat Auto-Fallback zu Polling wenn WebSocket nicht available
- Größere Library, aber robuster
- Vorteile: Robust, große Community
- Nachteile: Node.js Server zusätzlich, komplexer

**Option C: Server-Sent Events (SSE)**
- Einfacher als WebSocket, One-way vom Server
- Browser öffnet persistent HTTP connection
- Server schickt Updates als JSON
- Vorteile: Einfach, keine spezielle Library nötig
- Nachteile: One-way only, nicht ideal für interactive updates

**Empfehlung:** **Option A** (Native WebSocket + Ratchet). Begründung:
- Einfachheit: Nur PHP, keine Node.js-Dependency
- Performance: Direct connection, keine Fallback-Overhead
- Cost: Ein Service statt zwei

**Entscheidung bis:** 20. März 2026

---

#### 3. Mobile Strategy (B7)

**Frage:** Native Apps oder Progressive Web App?

**Option A: React Native (Shared Codebase)**
- Ein Codebase für iOS + Android
- Installable App im App Store
- Offline functionality
- Vorteile: Native Feel, beste Perf, aktuell trendy
- Nachteile: Höchste Cost (€150k+), braucht React-Expertise, Build-Pipeline komplex

**Option B: Progressive Web App (PWA) Only**
- Web App mit Service Workers, offline mode, installable
- Eine Codebase (Web)
- Vorteile: Niedrigste Cost, schneller zu develop, einfach zu update
- Nachteile: Kamera/Barcode-Scanner limitiert, offline sync komplexer

**Option C: Native Apps (Swift + Kotlin)**
- iOS in Swift, Android in Kotlin
- Beste Performance, beste Native Integration
- Vorteile: Best-in-class Perf
- Nachteile: Höchste Cost (€200k+), duplikat codebase

**Empfehlung:** **Option B First (PWA sufficient), Option A in Phase 5 if user demand high**. Begründung:
- PWA ist schnell zu marktreife
- React Native für Barcode-Scanner ist overkill in Phase 1
- Feedback sammeln first, dann investieren in expensive React Native

**Entscheidung bis:** 20. März 2026

---

#### 4. Cloud Accounting Sync (D3)

**Frage:** Bidirektionale Sync (komplex) oder One-Way Export (einfach)?

**Option A: Bidirectional (Full Sync)**
- MyRMS ↔ Sevdesk/Lexoffice
- Zahlungen von Bank → Buchhaltung → MyRMS
- Rechnungen von MyRMS → Buchhaltung
- Konflikterkennung und -auflösung
- Vorteile: Fully automated, no manual reconciliation
- Nachteile: Komplex, debugging Nightmare bei Konflikten, 4-5 Wochen Effort

**Option B: One-Way Export (Simpler)**
- MyRMS exportiert Rechnungen als CSV/XRechnung
- Benutzer importiert manuell oder via Sevdesk import feature
- Zahlungen müssen manuell oder per Bank-Feed in Sevdesk erfasst werden
- Vorteile: Simple, low risk, schnell zu implementieren (2 Wochen)
- Nachteile: Still requires manual steps

**Option C: Hybrid (Smart Export)**
- One-way export + optional bidirectional für große Kunden
- Zahlungen automatisch via Bank-Feed
- Rechnungen per Auto-Export

**Empfehlung:** **Option B First (Phase 4), Option A in Phase 5**. Begründung:
- Minimizes risk in Phase 4
- Feedback von Benutzern informiert Phase 5 design
- Wenn Bidirectional nur 5 Benutzer nutzen würden, nicht wert der Komplexität

**Entscheidung bis:** 22. März 2026

---

#### 5. Localization Scope (B4)

**Frage:** Nur Deutsch + English oder mehr Sprachen?

**Option A: Deutsch + English (Focus)**
- Komplette Lokalisierung dieser 2 Sprachen
- Realistic für Phase 4 Timeline
- Vorteile: Qualität hoch, alle UI-Ecken übersetzt
- Nachteile: Restliche Märkte (Frankreich, Spanien) nicht adressiert

**Option B: +3 Sprachen (Europa)**
- Deutsch, English, Französisch, Spanisch
- Doppelter Effort (ca. 2000 Keys × 4 Sprachen = 8000 strings)
- Vorteile: Paneropa Potential
- Nachteile: Resource-Intensive, Qualität leidet wenn gehetzt

**Option C: Hybrid (English as fallback)**
- Deutsch vollständig, English fallback für alles andere
- Später Französisch + Spanisch hinzufügen

**Empfehlung:** **Option A** (Deutsch + English complete), **Option B in Phase 6+**. Begründung:
- Kernmarkt ist Deutschland (80% der Zielkunden)
- English ist International fallback
- Französisch + Spanisch später, wenn revenue rechtfertigt

**Entscheidung bis:** 22. März 2026

---

### Go/No-Go Checkpoints

Jede Phase hat einen Checkpoint. Fehlgeschlagene Checkpoint bedeutet: Pause, Analyse, Plan-Adjustment, oder Cancel.

**End of Phase 1 (Month 2 / Mitte Mai 2026):**

Go-Kriterien:
- Dashboard-Verbesserungen sind visuell evident (CEO kann das sehen)
- Mobile Usability Improvement ist messbar (interne Testing-Session, > 80% Zustimmung)
- API-Skeleton ist place, zero breaking changes zu Alt-System
- Documentation ist aktuell (Developers können ohne 10 Questions onboarden)

Entscheidung: Continue to Phase 2 oder Pause & Adjust?

**End of Phase 2 (Month 4 / Mitte Juli 2026):**

Go-Kriterien:
- Live Dispatch Board ist operational und Benutzer-Feedback ist positiv (NPS > 7/10)
- Advanced Filter adoption ist > 50% der Benutzer nutzen mindestens 1 Filter
- Report Scheduling funktioniert reliably (< 1% failed sends over 2 weeks)

Entscheidung: Continue to Phase 3 oder pivot zu 3a (Scale Phase) oder 3b (Stabilize Phase)?

**End of Phase 3 (Month 6 / Mitte September 2026):**

Go-Kriterien:
- 80%+ Test Coverage ist erreicht
- Performance Targets sind met (p99 < 200ms gemessen in Production)
- Security Audit ist bestanden (no critical findings)

Entscheidung: Full Production Rollout oder staged Release (10% users, then 50%, then 100%)?

---

### Quick Wins (Implement First)

Diese 6 Items sollten erste sein, die gestartet werden. Jede dauert < 1 Woche:

#### 1. Dashboard KPI Cards (A1.1)
- **Was:** 3 Cards prominent oben: Revenue (MTD), Open Invoices Count, Asset Utilization %
- **Wie:** Einfache SQL-Abfragen (GROUP BY month, COUNT(*), etc.), hardcoded Werte nicht nötig
- **Warum First:** Business Value ist sofort sichtbar, motiviert Team
- **Aufwand:** 3 Tage Frontend + Backend
- **Owner:** 1 Frontend + 1 Backend
- **Go-Live:** Erste Woche

#### 2. Mobile Navigation Toggle (A2)
- **Was:** Auf Smartphones (xs/sm Bootstrap breakpoints), Sidebar wird zu Hamburger-Menu
- **Wie:** Bootstrap's Collapse component + CSS media query
- **Warum First:** Mobile Usability ist frustration #1 aus User Feedback
- **Aufwand:** 2 Tage
- **Owner:** 1 Frontend
- **Go-Live:** Erste Woche

#### 3. Breadcrumb Navigation (A2.2)
- **Was:** "Home > Projects > Project #123" Breadcrumb auf jeder Page
- **Wie:** Twig Template helper, inject in base template
- **Warum First:** Reduziert Desorientierung, User-Feedback positiv
- **Aufwand:** 2 Tage
- **Owner:** 1 Frontend
- **Go-Live:** Erste Woche

#### 4. Dark Mode Toggle (A4.1)
- **Was:** Toggle-Button in Header, Dark CSS Theme, localStorage Persistence
- **Wie:** CSS Custom Properties (variables), @media (prefers-color-scheme), localStorage
- **Warum First:** Modern Feature, User-Erwartung, easy Wins für Dev Morale
- **Aufwand:** 3 Tage
- **Owner:** 1 Frontend
- **Go-Live:** Zweite Woche

#### 5. Report Email Scheduling (B1.3)
- **Was:** "Jeden Montag 6am diese Report per Email senden"
- **Wie:** Cron-Job existiert bereits, nur UI dazu (simple Dropdown + Zeit-Picker)
- **Warum First:** Automation Feature, Recurring Revenue potential
- **Aufwand:** 2 Tage Backend
- **Owner:** 1 Backend
- **Go-Live:** Zweite Woche

#### 6. Search Filters (A6.1)
- **Was:** Auf Asset/Customer/Project List: Collapsible Filter-Panel
- **Wie:** Existing SQL WHERE-Klauseln, neue UI panel
- **Warum First:** Data Discovery Productivity erheblich verbessert
- **Aufwand:** 4 Tage (3 Lists × 1.3 Tage)
- **Owner:** 1 Frontend + 1 Backend
- **Go-Live:** Zweite + Dritte Woche

**Total Effort:** ~18 Tage für 3 Personen = erste 3-4 Wochen abgedeckt + gives immense Momentum

---

## Appendix: Technology Recommendations

### Frontend Stack

**Current State:**
- jQuery (legacy, should deprecate)
- Bootstrap 4 (good but outdated)
- AdminLTE 3 (nice templates but heavy)
- Twig (server-side rendering, good choice)

**Proposed Stack (Gradual Migration):**

**jQuery → Deprecate (2026):**
Continue jQuery for Phase 1-2, plan migration to vanilla JavaScript or lightweight libraries (Alpine.js, HTMX) in Phase 6. Rationale: jQuery is outdated, but massive codebase. Incremental migration avoids rewrite-risk.

**Bootstrap 4 → Bootstrap 5 (Immediate):**
Bootstrap 5 has better mobile support, improved components, smaller CSS. Upgrade CSS, test mobile responsiveness. Effort: 1 week.

**Twig: Keep as-is (Excellent Choice):**
Twig is the PHP templating standard, excellent for server-side rendering, good for security (auto-escaping). No need to change.

**Chart.js for Visualizations (Phase 2+):**
Lightweight charting library, good for KPI dashboards, ~50KB minified. Replaces any heavier charting lib.

**Tailwind CSS for New Components (Phase 4+):**
Utility-first CSS framework. For new components (integration UI, modals), consider Tailwind instead of Bootstrap. Benefit: Smaller CSS bundle, highly customizable. Can coexist with Bootstrap.

**Alpine.js for Lightweight Interactivity (Phase 2+):**
Lightweight JS framework (15KB), great for: modal toggles, form validations, simple state. Alternative to jQuery for new code. Easier than Vue/React for simple features.

**HTMX for Server-Driven HTML (Phase 3+):**
HTMX allows: `<button hx-post="/api/invoice/123/send">Send Invoice</button>` — clicking triggers POST, returns HTML fragment, auto-updates page. Very PHP-friendly, reduces JS. Optional but powerful.

**Recommended Libraries:**
- **Forms:** Parsley.js (form validation client-side)
- **Icons:** Bootstrap Icons (ships with Bootstrap 5)
- **Modals:** Bootstrap Modals (built-in)
- **Tooltips:** Bootstrap Tooltips or Popper.js + Tooltip.js
- **DatePicker:** Litepicker.js (lightweight date range picker)

---

### Backend Stack

**Current:**
- PHP 8.3 ✓ (excellent choice, modern)
- Twig ✓ (good)
- mysqli (raw SQL, needs ORM)
- Phinx (migrations, good)

**Recommended Additions:**

**Slim 4 (Routing Framework):**
Lightweight, minimal overhead. Routes like:
```php
$app->post('/api/v2/invoices', InvoiceController::class . ':create');
```
Install: `composer require slim/slim:4`

**PHP-DI (Dependency Injection):**
Define services once, inject everywhere. Makes testing easier.
```php
$container->set(InvoiceRepository::class, function() {
    return new InvoiceRepository(PDO);
});
```
Install: `composer require php-di/php-di`

**Monolog (Structured Logging):**
JSON logging to stdout/files. Critical for debugging.
```php
$logger->info('Invoice created', [
    'invoice_id' => 123,
    'amount' => 5000,
    'customer' => 'Acme Corp'
]);
```
Install: `composer require monolog/monolog`

**Redis (Caching + Job Queue):**
Cache frequent queries, queue long-running tasks.
```php
$redis->set('projects:march', $projects, 3600); // 1hr TTL
$queue->push(new GenerateReportJob($user_id));
```
Install: `composer require redis/redis` and Docker Redis image.

**Elasticsearch (Optional, Phase 4+):**
Full-text search across projects, customers, invoices. Not needed for Phase 1-3.

**Ratchet (WebSocket Server):**
PHP WebSocket server for live updates.
```
composer require cboden/ratchet
```

**PHPUnit (Unit Testing):**
Already in use, expand coverage in Phase 3.

---

### DevOps & Infrastructure

**Current:**
- Docker Compose ✓ (good for local dev + production)
- GitHub ✓ (excellent)

**Recommended:**

**GitHub Actions (CI/CD):**
Free, GitHub-native. Workflow example:
1. On push to main: Run PHPUnit tests
2. If tests pass: Build Docker image
3. Push to Docker Registry
4. Deploy to Production

```yaml
name: CI/CD
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - run: composer test
      - run: docker build -t myms:latest .
      - run: docker push ...
```

**Docker (Keep):**
Excellent containerization. Current setup is good.

**Redis (Add):**
For caching + job queue. Docker image: `redis:7-alpine` (10MB).

**S3 (Keep):**
File storage. Good choice.

**Sentry (Keep):**
Error tracking. Good choice. Expand to track errors from new API v2.

**Cloudflare (Optional):**
CDN + DDoS protection. Not critical in Phase 1-2.

**PostgreSQL (Consider Phase 5+):**
MySQL is fine for now. If scaling to 100k+ records, PostgreSQL has better JSONB, window functions. Migration effort: 2-3 weeks with Doctrine ORM. Not urgent.

---

### Monitoring & Observability

**Tools:**

**Application Metrics: Prometheus + Grafana**
Self-hosted. PHP app exports metrics to `/metrics` endpoint. Grafana visualizes dashboards (CPU, Memory, Requests/sec). Cost: Free software + server resources.

**Error Tracking: Sentry**
Already in use, good. Continue.

**Synthetic Monitoring: Uptime Robot**
Simple monitors: Is the site up? Respond time? Free tier covers 50 monitors.

**User Analytics: Plausible or Fathom**
Privacy-friendly, GDPR-compliant (unlike Google Analytics). €20-30/month. Track feature adoption.

**APM (Application Performance Monitoring):**
Optional in Phase 3+. Tools: New Relic, Datadog, Elastic APM. Gives detailed request tracing: which queries slow, which external APIs slow. Cost: €100-300/month. Worth if scaling.

**Database Monitoring: pt-query-digest**
Open-source tool. Analyzes MySQL slow query log, suggests indexes. Run weekly.

---

## Conclusion

Diese umfassende Roadmap bietet einen strategischen Weg für MyRMS, sich von einem funktionalen Rental-Management-System zu einer modernen, wettbewerbsfähigen Plattform zu entwickeln. Der phasierte Ansatz balanciert Quick Wins (Dashboard, Mobile, Filter) mit architektonischen Verbesserungen (API, Routing, Testing) und umsatzgenerierenden Features (Integrationen, Analytics, Customer Portal).

### Schlüsselfaktoren für Erfolg

1. **Prioritäts-Disziplin:** Widerstehen Sie Scope Creep. Schneiden Sie erbarmungslos. Jede Phase hat einen Feature Freeze bei 80%.

2. **User-Feedback-Schleifen:** Validieren Sie Annahmen mit echten Benutzern. Bi-weekly Feedback Sessions mit 5-10 Power Users.

3. **Performance-Obsession:** Geschwindigkeit ist ein Wettbewerbsvorteil. Lighthouse Audits monatlich. p99 Latency < 200ms ist nicht verhandelbar.

4. **Qualität ist nicht verhandelbar:** Automatisierte Tests, Code Review (mindestens 2 Reviewer für critical Paths), Security Audits (quarterly).

5. **Team-Investment:** Einstellen gut, halten sie. Continuous Learning Budget (€1000/Person/Jahr für Conferences, Courses). Knowhow-Transfer dokumentieren.

### Timeline & Investment

**Timeline:** 12 Monate für vollständige Roadmap. MVP (Phase 1-2): 2-4 Monate.

**Investment:** €691k total (fully staffed team). Für Phase 1-2 allein: €269k.

**Erwarteter ROI:**
- Support-Kosten: -30% (Automation, besseres UI)
- Produktivität: +20-30% (bessere Tools, schnellere Workflows)
- Competitive Positioning: 3-5 unique Features
- Skalierungs-Potential: Foundation für 3-5× Growth ohne Rewrite

### Nächste Schritte (These Week)

1. **Diese Woche (bis 22. März):**
   - Management-Sign-Off für Gesamtbudget (€691k)
   - Entscheidungen für 5 kritische Punkte (Architecture, WebSocket, Mobile, Accounting, Lokalisierung)
   - Team-Hiring-Anfrage: 1 Frontend, 1 Backend (Start sofort, onboard bis 1. April)

2. **Nächste Woche (bis 29. März):**
   - Kick-off Meeting: Alle Stakeholder, Expectations klar machen
   - Sprint Planning für Phase 1: User Stories schreiben, 2-Wochen-Sprints planen
   - Development Environment Setup: Git Repos, Docker, Monitoring

3. **Erste Woche April:**
   - Phase 1 Start mit Quick Wins: Dashboard KPIs, Mobile Toggle, Dark Mode, Filter
   - Weekly Standup (10 min, Montag 9am)
   - Phase 1 Demo (Friday am, 30 min) für Stakeholders

---

**Document Version:** 3.1 (Expanded + UI Design + Multi-KI-Provider)
**Last Updated:** 18. März 2026
**Next Review:** Wöchentlich während Phase 1 (Montag 9:00 Uhr)
**Owner:** Technical Steering Committee
**Approval:** Required from CEO, CTO, Product Owner

---

# Part H: UI-Design-Spezifikationen für alle Module

## H1. Design-System und Style Guide

Das Design-System von MyRMS bildet die Grundlage für alle Benutzeroberflächen und gewährleistet konsistenz, Zugänglichkeit und eine angenehme Nutzerexperience über alle Module hinweg. Das System folgt Modern SaaS Dashboard Patterns von 2025 und basiert auf einer neutralen Farbpalette mit gezielten Accent-Farben für Alerts und Statusinformationen.

Die Farbpalette besteht aus Primary Color (Mittleres Blau, #2563EB), Secondary Color (Dezentes Grau, #6B7280), sowie semantischen Farben für Feedback: Success (Grün #10B981), Warning (Bernstein #F59E0B), Danger (Rot #EF4444), Info (Cyan #06B6D4). Hintergrundfarben nutzen ein neutrales Palette aus Off-White (#F9FAFB) für Seiten und #FFFFFF für Cards und Container, um Tiefe und Hierarchie zu erzeugen.

Die Typografie folgt einer Modern Sans-Serif Familie (Inter oder Segoe UI als Fallback). Heading Styles sind definiert als H1 (32px, Weight 700), H2 (24px, Weight 600), H3 (20px, Weight 600), H4 (16px, Weight 600) sowie Body Text (14px, Weight 400), Small Text (12px, Weight 400) und Labels (12px, Weight 500). Das 8px Grid-System wird konsistent angewendet: 8px, 16px, 24px, 32px, 48px für Spacing und Padding. Das Layout basiert auf einem 12-Column Grid mit 20px Gutter und 1200px Max-Width für Desktop.

Das Icon-System nutzt Lucide Icons in den Größen 16px (UI-Controls), 20px (default), 24px (prominente Actions), und 32px (Hero/Empty States). Icons sind monochromatisch und werden in Körperfarbton oder Accent-Farben eingefärbt. Schatten und Elevation nutzen drei Stufen: Cards (box-shadow: 0 1px 3px rgba(0,0,0,0.1)), Dropdowns/Modals (0 10px 15px rgba(0,0,0,0.15)), und Modal Overlay (0 20px 25px rgba(0,0,0,0.2)). Border Radius ist konsistent bei 4px für kleine Elemente (Input, Buttons), 8px für Cards und Panels, 12px für größere Modals. Alle Animationen nutzen eine Standard-Easing von ease-in-out mit 150ms Duration für Micro-Interactions (Hover, Focus), 300ms für Modal-Übergänge und 500ms für Page-Transitions.

## H2. Dashboard-UI (Referenz: Rentman, Booqable)

Das Dashboard bildet die Landingpage nach dem Login und präsentiert KPIs, aktive Projekte, anstehende Aufgaben und Finanz-Übersichten in einem customisable Widget-System. Das Layout folgt einem 4-Spalten Grid auf Desktop mit automatischem Responsive Reflow zu 2 Spalten auf Tablet und 1 Spalte auf Mobile. KPI-Karten zeigen numerische Metriken (z.B. „Gesamtumsatz diesen Monat", „Verfügbare Assets", „Überfällige Rechnungen") mit großer, leicht lesbarer Zahlendarstellung (32px Weight 700), einer kurzen Beschreibung, sowie einer farbcodierten Trend-Anzeige (grüner Pfeil nach oben für positive Trends, roter Pfeil nach unten für negative, grauer Strich für neutral).

Das Widget-System ermöglicht Drag-and-Drop Anordnung: Benutzer können Cards verschieben, in der Größe anpassen (1x1, 2x1, 2x2 Grid Spots) und beliebig ein-/ausblenden. Jedes Widget hat einen Header mit Titel, Info-Icon (Tooltip mit Erklärung), und ein Menü-Icon (Zahnrad) für Widget-spezifische Einstellungen. Die Aktion „Widget entfernen" ist im Kontextmenü hinterlegt und wird mit einer Toast-Nachricht bestätigt, die einen „Undo" Link anbietet.

Chart-Widgets zeigen Umsatzentwicklung (Linien-Diagramm mit Monatstrends), Auslastungsquoten (Säulen-Diagramm: Assets pro Kategorie), und fällige Rechnungen (sortiertes Ranking mit Fälligkeitsdatum). Die Quick-Action-Leiste am oberen Dashboard-Rand bietet primäre Buttons für wiederkehrende Actionen: „+ Neues Projekt", „+ Neuer Kunde", „+ Neue Rechnung", „+ Equipment erfassen" mit Icon + Text. Diese Buttons öffnen je ein Modal oder Seite zum Anlegen.

Kritische Alarme (z.B. „Zahlungsfällige Rechnungen", „Server-Fehler", „Benutzer-Einladung ausstehend") werden als Notification-Banner oben auf dem Dashboard angezeigt, mit Farbe rot (#EF4444), einem Warning-Icon und einer Dismiss-Option (X). Dashboard-Presets sind pro Benutzer-Rolle konfiguriert: Admin sieht Finanzen + System-Health, Projektmanager sieht Projekte + Team-Auslastung, Lager-Personal sieht Asset-Verfügbarkeit + Scan-Queue, Finanzen sieht Rechnungen + Zahlungseingänge. Benutzer können vordefinierte Presets über ein Dropdown am Dashboard-Titel auswählen oder ein Custom Preset speichern.

## H3. Navigations-UI

Die primäre Navigation besteht aus einer vertikalen Sidebar auf der linken Seite, die in einer Standard-Breite von 240px expandiert wird und auf 64px (Icon-Only Modus) kollabiert werden kann. Der Toggle-Button (Hamburger-Icon) ist oben rechts in der Sidebar positioniert und hat einen klaren Hover-State mit Hintergrundfarbe. Die Sidebar-Struktur nutzt Sektion Headers (Labels in Small-Text, Weight 500, Gray-600) mit jeweils 4-6 Menüpunkten. Jeder Menüpunkt hat ein Icon (20px, links), einen Label (Body Text, zentriert vertikal), und optional einen Badge (z.B. rote Punkt für Unread-Benachrichtigungen). Der aktive Menüpunkt wird mit einer linken Border (4px, Primary-Blau) und geändertem Hintergrund (#F3F4F6) hervorgehoben.

Für komplexe Bereiche mit vielen Subitems (z.B. Modulverwaltung, Berichterstellung) wird ein Mega-Menu eingesetzt: beim Hover über einen Menüpunkt wird horizontal ein Panel ausgeklappt, das Subitems in Kolonnen strukturiert. Dies verhindert endlose verschachtelte Menüs und macht Abschnitte schneller navigierbar. Das Breadcrumb-System unter dem Page-Titel zeigt die aktuelle Position in der Navigation (z.B. „Dashboard > Projekte > Projektname") und erlaubt Rücksprünge durch Klick auf einzelne Seiten.

Für Power-User wird eine Command Palette implementiert, erreichbar über Ctrl+K (oder Cmd+K auf Mac). Die Palette öffnet ein Modal mit einer Search-Box und einer Rangliste kürzlich genutzter Commands sowie Search-Results nach Eingabe von Keywords (z.B. „Rechnung erstellen", „Assets exportieren"). Dies reduziert Klicks für häufige Aufgaben erheblich.

Auf Mobile-Geräten (< 768px) wird die Sidebar in einen Bottom-Navigation-Tab-Bar transformiert mit 5 Haupt-Tabs (Dashboard, Projekte, Assets, Kunden, Mehr). Der „Mehr"-Tab öffnet ein Menü-Modal mit restlichen Navigation Items. Eine Contextual Quick-Action wird als Floating Action Button (FAB) implementiert, typischerweise mit dem Icon „+" (Primary-Farbe, Box-Shadow, 56px Durchmesser), positioniert unten rechts mit 16px Abstand zu den Edges, um schnelle Aktionen auf Mobile zu ermöglichen (z.B. „Schnell scannen" auf der Assets-Seite).

## H4. Tabellen-UI (Datentabellen für alle Listen)

Alle Listen von Daten (Projekte, Assets, Kunden, Rechnungen, etc.) werden in Tabellen-Form mit einer erweiterten Feature-Set dargestellt. Die Column Headers sind sortierbar (Klick auf Header führt ASC-Sort aus, zweiter Klick DESC, dritter Klick entfernt Sorting). Ein Pfeil-Icon neben dem Spalten-Name zeigt die Sortierungsrichtung an (▲ für ASC, ▼ für DESC). Column Headers sind auch resizable: Benutzer können die Grenze zwischen zwei Spalten mit der Maus greifen und verschieben (Cursor wird zu col-resize), die Breite wird im Browser Local Storage persistiert.

Eine collapsible Filter-Bar sitzt über der Tabelle und zeigt aktive Filter als removable Chips an (z.B. „Status: Aktiv ✕", „Kategorie: Equipment ✕"). Neue Filter werden über ein „+ Filter" Button hinzugefügt, welches ein Dropdown mit verfügbaren Spalten und ihren Filter-Operatoren öffnet (Text: contains/equals, Number: =/>/<, Date: before/after/range). Angewendete Filter werden dynamisch aus der Spalten-Liste genommen, um Redundanzen zu vermeiden.

Inline-Editing ist für viele Spalten unterstützt: beim Hover über eine Zelle wird ein kleines Bleistift-Icon (Edit-Icon) rechts in der Zelle sichtbar. Klick auf die Zelle oder das Icon öffnet einen Edit-Mode: die Zelle erhält einen Focus-Highlight (1px Border, Primary-Farbe), zeigt ein Input-Feld oder Select-Dropdown je nach Datentyp, und hat zwei Buttons (Checkmark zum Speichern, X zum Abbrechen). Nach erfolgreicher Speicherung blinkt die Zelle kurz grün auf.

Bulk-Selection ist durch eine Checkbox in der ersten Spalte implementiert. Ein „Select All" Checkbox im Column Header wählt alle sichtbaren (oder alle) Datensätze. Sobald >= 1 Zeile ausgewählt ist, wird eine Floating-Action-Toolbar am unteren Rand der Tabelle angezeigt mit Bulk-Aktionen: „Delete" (mit Bestätigungs-Modal), „Archive", „Export", „Assign to..." (z.B. zu einem Projekt oder Besitzer). Diese Toolbar hat einen Semi-transparenten Hintergrund, sticky positioning und ist weit oben z-index weise (um Modals nicht zu blocken).

Row Hover States zeigen zusätzliche Interaktivität: beim Hover über eine Zeile wird der komplette Row mit einem leichten Hintergrund-Highlight (#F3F4F6) versehen, und am rechten Ende werden Quick-Action Icons erscheinen (Edit, Details-anschauen, Löschen, Duplikate, etc.). Diese Icons sind nur auf Hover sichtbar, um Tabellen nicht zu überladen. Der Row selbst ist auch als Link konfigurierbar, um die Detailseite zu öffnen.

Responsive Verhalten auf Mobile: statt Horizontales Scrollen wird ein Card-Layout verwendet. Jede Zeile wird zu einer vertikalen Card mit großeren, stacked Labels und Values, sowie einem Expand-Icon zum Anschauen aller Spalten. Pagination vs. Infinite Scroll ist konfigurierbar per Tabellen-Konfiguration: Standard ist Pagination mit Seite X von Y und Größen-Selector (10, 25, 50, 100 Einträge pro Seite). Infinite Scroll kann für mobile Erfahrung aktiviert werden, wo Scroll zum Seitende automatisch weitere Einträge lädt.

Column Visibility Toggle ist über ein Spalten-Icon (drei Punkte oder ähnlich) im Header erreichbar und öffnet ein Dropdown mit Checkboxen für jede Spalte. Deselektierte Spalten werden ausgeblendet. Ein Export-Button (Download-Icon) bietet CSV, Excel, und PDF Export mit konfigurierbarem Spalten-Include.

Saved Views/Filter-Presets ermöglichen Benutzer, häufig verwendete Filter-Kombinationen unter einem Namen zu speichern (z.B. „Meine aktiven Projekte diese Woche") und später schnell wieder zu laden über ein Dropdown im Filter-Bar.

## H5. Formular-UI (Alle Eingabemasken)

Alle Formular-Eingaben folgen einem konsistenten Design, ob einfache Single-Page Formulare oder komplexe Multi-Step Wizards. Für komplexe Szenarien (z.B. Projekt anlegen, Rechnung erstellen) wird ein Multi-Step Wizard mit 3-5 Feldern pro Step implementiert. Der Wizard zeigt oben einen Progress Indicator: Schritte als nummerierte Kreise (z.B. „1. Basis-Info → 2. Positionen → 3. Konditionen → 4. Bestätigung"), wobei der aktuelle Schritt gefüllt und blau ist, vorherige grün, und kommende grau.

Jeder Step hat einen Titel und optionalen Beschreibungstext, gefolgt von den Eingangsfeldern. Progressive Disclosure ist zentral: grundlegende Informationen (Name, Datum, Kunde) werden im ersten Step gezeigt, komplexe Feldgruppen (z.B. Zahlungsbedingungen, Versanddetails) erst später. Conditional Fields werden basierend auf vorherigen Antworten sichtbar oder verborgen (z.B. wenn Zahlungsart „SEPA" ist, zeige IBAN-Feld).

Feldtypen sind standardisiert: Text-Input (mit Placeholder-Text), Number-Input (mit +/- Spinner oder nur Tastatur-Input), Date Picker (Icon öffnet Kalender mit schnellen Presets), Select-Dropdown (mit Suchbar falls > 10 Optionen), Multi-Select (Chips/Tags anzeigen Auswahl), Toggle-Switch (für Boolean Werte), File-Upload (Drag-Drop Zone mit „Click to upload" Fallback).

Inline Validation bietet Echtzeit-Feedback: während des Eingabe wird das Feld überprüft (auf Seite oder via API), und ein Validierungsstatus wird angezeigt als färbiger Border (grün für valid, rot für error) mit optional einer Meldung unter dem Feld (rote Schrift, 12px, mit Icon). Ein grüner Checkmark-Icon im Feld selbst (rechts) zeigt completion an.

Auto-Save Draft ist zentral für UX: alle 30 Sekunden werden Formulardaten im Local Storage (oder via API) gespeichert. Ein Indicator oben im Wizard zeigt „Entwurf wird gespeichert..." → „Entwurf gespeichert" mit Timestamp. Falls Benutzer die Seite verlässt, wird beim Zurückkommen das Formular wieder geladen und ein Banner oben fragt, ob Entwurf fortgesetzt oder neu gestartet werden soll.

Keyboard Navigation ist vollständig implementiert: Tab durchläuft alle Felder in logischer Reihenfolge, Enter absendert das Formular oder geht zum nächsten Step, Escape bricht ab (mit Bestätigungs-Modal wenn Changes vorhanden). Help Text und Tooltips werden über ein Info-Icon (Info-Icon, Farbe Gray-500) neben jedem Label angezeigt; Hover zeigt einen Tooltip mit erweiterter Erklärung.

Required Field Indicators nutzen einen roten Stern (*) neben dem Label. Buttons am unteren Ende des Wizards sind „Zurück" (Secondary Button, disabled im Step 1), „Weiter" oder „Absenden" (Primary Button, disabled wenn validation errors vorhanden). Nach erfolgreichem Submit wird eine Success-Toast-Nachricht angezeigt (grüner Hintergrund, Checkmark-Icon, Auto-Dismiss nach 5s) mit Link zu nächster relevanter Seite (z.B. „Projekt erstellt. → Zum Projekt").

## H6. Projekt-UI (Referenz: Rentman Drag-Drop)

Die Projekt-Management Oberfläche bietet multiple Ansichten für verschiedene Workflow-Phasen und -Aspekte. Die Standard-Ansicht ist ein Kanban-Board, das die Pipeline eines Projekts visualisiert: fünf Spalten repräsentieren die Stages „Anfrage" (gelb), „Angebot" (blau), „Bestätigt" (grün), „Aktiv" (dunkelgrün), „Abgeschlossen" (grau). Jede Stage zeigt Cards für Projekte in dieser Phase, mit Projekt-Name, Kunde, Datum und Progress-Indikator (% mit Fortschritts-Balken).

Drag-and-Drop Funktionalität erlaubt, eine Projekt-Card von einer Stage in die nächste zu ziehen, um den Status zu ändern. Ein visuelles Feedback zeigt dabei: die Card hebt sich ab (Schatten), die Ziel-Spalte wird leicht hervorgehoben (Border, Hintergrund-Farbe), und beim Loslassen animiert die Card in die neue Position. Falls die Stage ein Bestätigungsschritt ist (z.B. „Bestätigt"), öffnet sich optional ein Modal zum Eintragen von Informationen (z.B. Termin bestätigen).

Auf der Projekt-Detail-Seite wird eine Tabbed-Navigation mit fünf Tabs genutzt: „Übersicht" (Basis-Infos, Status, Kunde, Termine), „Equipment" (zugeordnete Assets mit Mengen, Preisen), „Team" (zugeordnete Crew, Rollen, Vefügbarkeiten), „Dokumente" (Angebot, Rechnung, Lieferschein, weitere hochgeladene Dateien), „Finanzen" (Kostenaufschlüsselung, Umsatz), „Aktivität" (Änderungshistorie, Kommentare). Jeder Tab ist eine separate „Seite" im Frontend, kann aber derselben URL-Struktur folgen (z.B. /projects/123/equipment).

Drag-and-Drop Equipment-Zuordnung wird auf dem Equipment-Tab implementiert: die linke Seite zeigt verfügbare Assets (aus dem Lagerbestand, gefiltert nach Kategorie und Verfügbarkeit), die rechte Seite zeigt zugeordnete Assets für dieses Projekt. Assets können von links nach rechts gezogen werden, oder umgekehrt zum Entfernen. Bei Zuordnung wird eine Menge eingegeben (Number-Input oder Spinner) und optional ein Custom-Price-Override.

Timeline-View zeigt ein Gantt-Chart: Projekte/Positionen auf Y-Achse, Zeit auf X-Achse, farbige Blöcke repräsentieren Projektdauer oder Asset-Nutzungszeiträume. Zoom-Controls (+/-) ermöglichen, die Zeitspanne anzupassen (Tage/Woche/Monat View). Heute ist mit einer roten Vertikallinie markiert. Drag-and-Drop am Gantt erlaubt Projekte zu verschieben oder Dauer zu ändern (Anfass am Block-Ende zum Resize).

Konflikt-Anzeige ist zentral: falls ein Asset für dieses Projekt zu zwei Zeiten verwendet werden soll (oder für zwei Projekte gleichzeitig), wird eine rote Markierung angezeigt, mit Hover-Tooltip detaillierter Info. Der Konflikt kann basierend auf verfügbarer Menge oder bestehenden Buchungen sein. Eine Warn-Banner oberhalb der Seite zeigt „1 Konflikt erkannt" mit Link zum Konflikt.

Status-Badges zeigen den Projekt-Status mit Farben: Grau für Entwurf, Gelb für Anfrage/Angebot, Grün für Bestätigt/Aktiv, Orange für Überfällig, Rot für Abgebrochen. Ein Dropdown erlaubt Status-Änderungen mit optionalem Grund/Notiz (Modal öffnet sich).

## H7. Equipment/Asset-UI (Referenz: EZRentOut)

Die Asset-Management Oberfläche präsentiert Vermögenswerte (Geräte, Möbel, etc.) in einer flexiblen Ansicht. Die Standard-Ansicht ist ein Card-basiertes Layout (Gitter) mit je einer Asset-Karte pro Item: oben ein großes Foto (oder Placeholder-Icon falls keine Foto), darunter Name (14px, Weight 600), Kategorie (Small-Text, Gray-600), und Status-Badge (farbcodiert: Verfügbar=Grün, Reserviert=Orange, In-Benutzung=Dunkelblau, Wartung=Rot). Darunter eine Preis/Tag Anzeige (16px, Weight 600, Primary-Farbe). Ein Menü-Icon (drei Punkte) oben rechts in jeder Karte bietet Quick-Actions: Bearbeiten, Duplizieren, QR-Code anzeigen, Historie, Löschen.

Alternativ ist eine Tabellenansicht verfügbar (Toggle oben rechts), welche Assets als Zeilen mit Spalten für Name, Kategorie, Verfügbarkeit, Preis/Tag, Letzter Scan, Status zeigt, mit denselben Inline-Edit und Quick-Action Funktionen wie in H4 beschrieben.

Die Verfügbarkeits-Kalender auf der Asset-Detail-Seite zeigt einen Monatliche/Wochenliche/Tägliche Ansicht (Tabs zum Wechsel). Tage oder Zeitblöcke werden farbcodiert: Grün=verfügbar, Orange=teilweise verfügbar (nur einige Mengen), Rot=vollständig ausgebucht. Klick auf einen Tag öffnet ein Detail-Modal, das alle Buchungen an diesem Tag zeigt (Projekt, Kunde, Zeitraum, Notizen).

Der QR-Code Scanner UI wird vollbildig dargestellt (Modal oder vollständige Seite, je nach Kontext). Der Kamera-Bereich zeigt den Live-Video-Feed mit einer Fokussierungs-Box in der Mitte (quadratisch, mit vier Ecken-Markern). Oben ist ein großes Text-Label „Richte deine Kamera auf einen QR-Code oder Barcode" mit freundlichem Icon. Ein erfolgreicher Scan triggert eine Vibration (auf mobilen Geräten) und einen kurzen Beep-Sound, gefolgt von einer grünen Bestätigungs-Animation (Checkmark, kurz eingeblendet). Das gescannte Asset wird automatisch hinzugefügt oder eine „Asset gefunden" Meldung wird angezeigt mit Optionen (Bestätigen, Nochmal scannen, Ändern).

Die Asset-Detail-Seite hat Tabs: „Info" (Name, Seriennummer, Kategorie, Preis, Beschreibung, Fotos), „Buchungen" (Chronologische Liste kommender/vergangener Buchungen), „Wartung" (Wartungshistorie mit Datum, Art, Notizen, Kosten), „Fotos" (Grid aller hochgeladenen Bilder, mit Möglichtkeit neue hochzuladen), „Historie" (Änderungslog mit Wer/Was/Wann). Jeder Tab hat einen Button zum Hinzufügen („+ Wartung eintragen", „+ Foto hinzufügen", etc.).

Die Zustandserfassungs-Formular wird angezeigt, wenn ein Asset aus einer Miete zurückkommt. Ein Modal zeigt: „Zustand erfassen für [Asset-Name]" mit einer Checkliste von häufigen Schäden (Kratzer, Dellen, Flecken, Elektronik defekt, etc.) mit Checkboxen, sowie ein großer Button zum Hochladen von Fotos (vorher/nachher). Ein Textarea für Notizen ist auch present. Ein „Bestätigen" Button speichert die Zustandserfassung und triggert eine Toast-Nachricht.

Barcode-Label Druck-UI ist erreichbar über einen „Print-Label" Button auf Asset-Detail. Ein Modal zeigt eine Vorschau des Labels (QR-Code + Asset-Name + Seriennummer + Barcode) mit Optionen für Label-Größe (A6, A5, A4, etc.) und Menge (1-10). Ein „Drucken" Button öffnet das Browser-Print-Dialog mit optimierten Layout für diese Label. Optional kann ein Label-Vorlage-Editor angeboten werden (Custom-Text, Logo-Position, Farben).

Lagerplatz-Visualisierung kann zwei Formen annehmen: Grid-View zeigt Lagerplatz als eine Top-Down Map mit Regalblöcken und Positionen, farbcodiert nach Asset-Kategorie oder Status (Grün=bestückt, Grau=leer). Klick auf Position zeigt Asset-Details. Eine Alternative ist Map-View (für größere Lager) mit zoom-baren Grundriss und Asset-Positionen als Pins.

## H8. Kunden-UI

Das Kundenmanagement bietet mehrere Ansichten und Interaktionsmöglichkeiten. Die Kundenübersicht wird in zwei Ansichten angeboten: Card-View (ähnlich Asset-Cards, mit Kundenname, Ort, Gesamtumsatz, letzter Kontakt, Status-Badge) und Tabellen-View (mit Sortierung, Filtering, Inline-Edit wie in H4). Ein „+ Neue Kunde" Button öffnet ein Quick-Create Inline-Formular am oberen Ende der Liste oder ein vollständiges Modal.

Die Kunden-Detail-Seite ist in Tabs organisiert: „Stammdaten" (Name, Adresse, Steuernummer, E-Mail, Telefon, Web, Zahlungsbedingungen, Kreditlimit, Rechnungs-Adresse vs. Lieferadresse Auswahl), „Projekte" (Tabellenansicht aller Projekte dieses Kunden, sortierbar nach Datum oder Umsatz), „Rechnungen" (Tabellenansicht mit Status, Betrag, Fälligkeitsdatum, mit Filtermöglichkeiten nach Status), „Kommunikation" (siehe unten), „Dokumente" (hochgeladene Dateien, Verträge, Angebote).

Kontaktpersonen-Management zeigt auf der „Stammdaten" Tab eine Sub-Liste von Ansprechpersonen (Name, Rolle, E-Mail, Telefon) mit Edit/Delete Actions pro Person. Ein „+ Kontaktperson hinzufügen" Button öffnet ein Quick-Form oder Modal.

Kommunikations-Timeline ist eine chronologische Ansicht aller Interaktionen: E-Mails (mit Subject, Datum, Gesprächspartner, kurzer Preview), Anrufe (Datum, Dauer, Notizen), Notizen (manuell erstellt, Datum, Autor). Diese werden in umgekehrter chronologischer Reihenfolge angezeigt (neueste oben). Ein „+ Notiz hinzufügen" Button erlaubt schnelle Notiz-Erfassung. Ein @-Mention Autocomplete erlaubt, Teamkollegen zu verlinken.

Kundenbewertung/Scoring wird als farbcodiertes Zeichen oben auf der Kunden-Detail angezeigt: A-Kunde (Gold-Farbe, hohes Volumen), B-Kunde (Silber), C-Kunde (Standard). Das Score wird basierend auf Gesamtumsatz, Zahlungshistorie (Pünktlichkeit), und Kontakthäufigkeit berechnet. Ein Tooltip oder Info-Sektion erklärt die Berechnung.

Quick-Create Inline-Formular auf der Kundenübersicht: Ein kollabierter Card-Block mit „+ Neue Kunde hinzufügen" Text. Klick expandiert den Block, um 3-4 Grundfelder anzuzeigen (Name, Ort, Telefon, E-Mail) mit Enter zum Speichern oder X zum Abbrechen. Dies reduziert Klicks für häufige Actionen.

## H9. Rechnungs- und Finanz-UI

Der Rechnungs-Editor bietet ein WYSIWYG-Interface mit Split-Screen: linke Seite zeigt das Editor-Formular mit Standard-Feldern (Rechnungsnummer, Datum, Kunde, Lieferdatum, Zahlungsbedingungen, Discount, Steuersatz), rechts zeigt die Live-Vorschau wie die Rechnung aussieht (mit Firma-Logo, Kopfzeile, Positionen, Summen, Zahlungshinweise). Änderungen in Editor aktualisieren die Vorschau in Echtzeit.

Positionen werden in einer Tabelle unterhalb des Editors hinzugefügt: Spalten für Pos.-Nummer, Artikel-Name, Menge, Einheit (Tage, Stunden, Stück), Einzelpreis, Summe. Drag-Drop erlaubt Umsortieren von Positionen. Ein „+ Position hinzufügen" Button öffnet eine neue Zeile mit Select-Dropdown für Artikel (aus Asset-Bibliothek), Number-Input für Menge, Auto-Berechnung von Summe. Jede Position hat ein Edit und Delete Icon. Bei Bearbeitung wird Inline-Edit Mode aktiviert (wie in H4).

Rechnungsliste zeigt Tabellenansicht mit Spalten: Rechnungsnummer, Kunde, Betrag, Datum, Fälligkeitsdatum, Status-Badge (mit Farben: Entwurf=Grau, Gesendet=Blau, Bezahlt=Grün, Überfällig=Rot, Mahnung=Orange). Inline-Edit erlaubt Status-Änderung durch Klick auf die Status-Zelle. Ein Badge-Klick öffnet ein Modal zum Vermerken von Zahlungseingang (Betrag, Datum, Zahlungsart, Referenz).

Zahlungs-Zuordnung UI ist kritisch für Finanzprozesse: ein Split-Screen zeigt links alle unbezahlten Rechnungen (mit Betrag und Fälligkeitsdatum), rechts alle eingegangenen Zahlungen (mit Betrag, Eingangsdatum, Referenz, optional Kundenzuweisung). Ein Drag-Drop Interface erlaubt, Zahlungen auf Rechnungen zu ziehen, um sie zuzuordnen. Ein Match-Algorithmus kann auch automatische Vorschläge machen (bei Betragsgleichheit oder Customer-Referenz-Match). Nach Zuordnung wird die Zeile in beiden Seiten grün hervorgehoben und kann entfernt werden.

Mahnwesen-Dashboard zeigt alle überfälligen Rechnungen in einer Tabelle, gruppiert nach Eskalationsstufe (Stufe 1: bis 7 Tage überfällig, Stufe 2: bis 30 Tage, Stufe 3: über 30 Tage). Jede Gruppe ist farbcodiert (gelb/orange/rot). Ein Button „Mahnung schreiben" öffnet einen E-Mail Template Editor mit vorausgefüllter Rechnungsinformation und Kundenadresse. Ein weiterer Button „Zuordnung aktualisieren" nach Zahlungseingang setzt den Status auf „Bezahlt".

Kassenbuch-UI zeigt eine Tabellenansicht aller Zahlungsbewegungen (Rechnungen, Zahlungseingänge, manuell erfasste Buchungen): Datum, Art (Rechnung/Zahlung/Gutschrift), Partner (Kunde/Lieferant), Betrag (positiv für Einnahmen, negativ für Ausgaben), Saldo-Laufzeile (kumulierte Summe). Eine Saldo-Laufzeile auf der rechten Seite zeigt die laufende Bilanz nach jeder Transaktion. Ein „Saldo zu Datum anschauen" Button erlaubt, den Kontostand zu einem beliebigen Termin zu ermitteln.

SEPA-Lastschrift Management UI ist ein Bereich für Setup und Verwaltung von automatischen Zahlungen. Ein „Lastschrift-Mandat erstellen" Button öffnet ein Formular, welches Kundendaten abfragt (Name, IBAN, Mandatreferenz, Gültigkeitsdatum). Gespeicherte Mandate werden in einer Tabelle aufgelistet mit Optionen zum Einsehen, Bearbeiten oder Widerrufen. Ein „Batch-Lastschrift-Einzug erstellen" Button öffnet einen Wizard zum Auswählen von Rechnungen und zum Starten des Einzugsprozesses.

ZUGFeRD/XRechnung Export UI bietet einen Button „Als XRechnung exportieren" auf der Rechnungs-Detail Seite. Das öffnet ein Modal mit Export-Optionen: Format (XRechnung, ZUGFeRD), Zielort (Download, E-Mail an Kunde, Server). Nach Export ist eine Success-Meldung mit Link zum Download angezeigt.

## H10. Crew/Team-UI (Referenz: Rentman Crew Planner)

Der Crew-Planner ist das Herzstück der Team-Ressourcenplanung. Eine Kalender-Ansicht (Woche oder Monat, schaltbar) zeigt Tage/Wochen auf der X-Achse und Crew-Mitglieder auf der Y-Achse. Jede Zelle repräsentiert die Verfügbarkeit einer Person an einem Tag oder ein Zeitblock (Stunden). Farbcodierung zeigt: Grün=verfügbar, Rot=vollständig gebucht, Orange=teilweise gebucht, Grau=nicht eingetragen (keine Info). Klick auf eine Zelle öffnet ein Detail-Panel mit bestehenden Zuordnungen und der Möglichkeit, neue hinzuzufügen.

Drag-and-Drop Zuordnung ist zentral: von der Projekt-Liste (oder Projekt-Detail Crew-Tab) kann eine benötigte Rolle/Aufgabe auf den Crew-Planner gezogen werden, z.B. „Kamera-Op benötigt für 18.4. 8h" wird auf einen freien Mitarbeiter zu dem Termin gezogen. Das System zeigt visuelles Feedback (Ziel wird hervorgehoben, Cursor ändert sich). Nach dem Drop wird die Zuordnung bestätigt (oder optional ein Modal öffnet für Details wie Schicht, Sondervergütung, Anmerkungen).

Verfügbarkeits-Heatmap ist eine Ansicht, die alle Crew-Mitarbeiter zeigt mit farbcodierter Heatmap für ihre Verfügbarkeit (über einen Monat oder Quartal hinweg): pro Tag ein Quadrat, Farbe zeigt Verfügbarkeit (Grün=0-20% belegt, Gelb=20-50%, Orange=50-80%, Rot=80-100%). Dies gibt einen schnellen Überblick, wer wann überlastet ist. Klick auf einen Tag in der Heatmap öffnet die Crew-Planner Ansicht für diesen Tag.

Skill-Matrix ist eine Tabelle: Zeilen sind Crew-Mitarbeiter, Spalten sind Skills (Kamera, Licht, Ton, Rigging, etc.). Zellen zeigen Kompetenz-Level (1-5 Sterne oder Farben: Keine Erfahrung/Anfänger/Fortgeschritten/Experte). Edit-Mode erlaubt Klick auf eine Zelle zum Ändern des Levels oder Datum der letzten Zertifizierung. Ein „Skill-Gap Analysieren" Report kann automatisch zeigen, welche Skills für kommende Projekte fehlen.

Schichtplan-Ansicht ist eine Wochenansicht (Montag-Sonntag) mit Zeiten (8:00-22:00 oder konfigurierbar). Jeder Crew-Mitarbeiter hat eine Reihe, und seine Schichten sind farbige Blöcke (Farbe je nach Rolle: Kamera=Blau, Licht=Orange, etc.). Die Blöcke zeigen Start-/Endzeit und optional Projekt-Name. Drag-Drop erlaubt Schicht-Verschiebung oder Resize (Dauer ändern). Ein Klick zeigt Schicht-Details (Projekt, Rolle, Pay, Standort, Notizen). Überlappende Schichten werden mit Warnung angezeigt (rote Markierung, Warning-Toast).

Kostenübersicht pro Crew-Mitglied: unter dem Crew-Planner oder in der Crew-Detail-Seite, eine Zusammenfassung der Arbeitskosten für einen Zeitraum (z.B. April): Stunden pro Schicht × Stundensatz = Kosten pro Schicht, aufgesummiert zu Gesamt-Crew-Budget. Ein «Budget vs. Actual» Chart kann zeigen, falls Ist-Kosten über geplante Kosten gehen. Die Ansicht kann pro Projekt oder pro Mitarbeiter gefiltert werden.

Push-Benachrichtigung bei Zuordnungs-Änderung: wenn ein Admin eine Schicht zu einem Crew-Mitglied zuordnet oder ändert, wird eine Push-Notification an diese Person gesendet (in der App + optional E-Mail). Der Notification zeigt Projekt-Name, Datum, Uhrzeit, Rolle. Ein Link in der Notification öffnet das Schicht-Detail im Crew-Portal.

## H11. RFID/Scan-UI

Der RFID/Barcode-Scanning Modus wird vollbildig dargestellt, entweder als Modal (für Scanning innerhalb eines Prozesses) oder als vollständige Seite (für dedizierte Scan-Sessions, z.B. im Lager). Der Scan-Bereich zeigt einen großen Input-Feld oben (mit Cursor automatisch fokussiert) oder eine Live-Kamera mit Fokus-Box (falls Kamera-Scanning verwendet wird). Die Hintergrundfarbe ist neutral (hellgrau) mit großer, leicht lesbarer Instruktions-Text: „Richte Kamera auf QR-Code oder tippe Barcode ein" in 18px, zentriert.

Nach erfolgreichen Scan wird unmittelbar ein Feedback gegeben: Der gescannte Code wird in einer Bestätigungs-Animation angezeigt (kurz grüner Hintergrund, Checkmark-Icon, „[Asset-Name] hinzugefügt" Text mit grünem Farbton). Ein kurzer Beep-Sound bestätigt auch akustisch (wenn Volume > 0). Das gescannte Item wird sofort unten in eine Liste hinzugefügt (siehe unten).

Batch-Scan-Modus erlaubt mehrere Items hintereinander zu scannen: nach jedem erfolgreichen Scan wird das Item der Liste hinzugefügt und das Input-Feld wird geleert und bleibt fokussiert für den nächsten Scan. Eine "Fertig scannen" Button (oder Escape-Taste) beendet die Scanning-Session und zeigt eine Zusammenfassung (X Items gescannt). Ein "Zurückgehen" Button erlaubt, einzelne Items aus der Liste zu entfernen (X Icon neben jedem Item).

Scan-History zeigt die letzten 50 Scans in einer Dropdown-Liste oder separaten View: für jeden Scan wird Barcode/QR-Code, Asset-Name, Timestamp (hh:mm:ss), Status (Erfolg/Fehler mit Icon und Farbe) angezeigt. Dies ist hilfreich für Debugging oder zur Überprüfung von Scanning-Aktivitäten. Ein „Historie löschen" Button setzt diese zurück.

Sound-Feedback ist konfigurierbar im Settings: Success-Ton (kurzer positiver Sound, z.B. "Ding"), Error-Ton (Warnung, z.B. "Buzzer"). Vibrationen auf Mobil sind auch möglich (Vibration API, kurze Pulse für Erfolg, längere für Error).

Offline-Scan mit Sync-Queue: falls die Netzverbindung unterbrochen wird, können Scans weiterhin lokal gepuffert werden (im Local Storage/IndexedDB). Ein Offline-Banner mit gelber Hintergrundfarbe wird oben angezeigt: "Keine Verbindung. Scans werden lokal gespeichert und synchronisiert, wenn Verbindung wiederhergestellt ist." Sobald Netzwerk verfügbar, wird ein Auto-Sync ausgelöst und ein Success-Toast zeigt "X Scans synchronisiert". Falls Sync fehlschlägt, wird eine Fehler-Toast angezeigt mit Option "Später versuchen" oder "Manuell senden".

## H12. Benachrichtigungs-System UI

Das Benachrichtigungs-System ist zentral für User-Engagement und Awareness. Ein Bell-Icon (Glocken-Icon, Farbe Gray-600) sitzt in der oberen rechten Ecke des Headers, neben User-Profil. Falls ungelesene Benachrichtigungen vorhanden sind, zeigt ein rotes Badge (Kreis mit Zahl, z.B. "5") die Anzahl an. Klick auf das Bell-Icon öffnet ein Dropdown-Panel mit einer Höhe von ~400px.

Das Notification-Dropdown zeigt die letzte 20 Benachrichtigungen, gruppiert optional nach Typ oder Zeitraum (z.B. "Heute", "Diese Woche"). Jede Benachrichtigung ist ein Eintrag mit: Icon (je nach Typ: Project=Briefcase, Payment=CreditCard, System=Gear), kurzer Title (16px), optional kurzer Description (Small-Text, Gray-600), Timestamp (z.B. "vor 2h"), und ein Read/Unread Status (grauer Punkt wenn ungelesen). Ein Link oder Klick-Area leitet zur entsprechenden Seite weiter.

Das Notification-Center (Vollansicht) ist über einen „See All" Link im Dropdown erreichbar oder über ein Menu-Item in der Sidebar. Die Seite zeigt eine Tabelle mit allen Benachrichtigungen (sortierbar nach Datum, Typ, Status). Filter können nach Typ (Project, Payment, System, Team), Status (Unread, Starred, Archived) und Zeitraum gefiltert werden. Eine Bulk-Aktion ermöglicht, mehrere Benachrichtigungen als gelesen zu markieren oder zu archivieren.

Toast-Benachrichtigungen erscheinen oben rechts am Bildschirm (Margin 16px vom Top und Right Edge), weiße Card mit leichtem Schatten. Sie zeigen ein Icon (abhängig von Typ), Nachricht (14px Body-Text), optional einen Action-Link (z.B. "Jetzt anschauen"), und ein Close-Icon (X). Standard-Dauer ist 5 Sekunden, danach animiert die Toast aus dem Sichtfeld (Fade + Slide nach rechts). Der Benutzer kann die Toast früher schließen mit einem Klick auf X oder kann mit Hover die Dauer "pausieren" (Timer stoppt).

Snackbar ist eine Variante für destruktive Aktionen (z.B. "Item gelöscht"): eine dunkle Bar am unteren Rand des Bildschirms mit Action-Text und einem „Undo" Button oder Link (in Primary-Farbe). Klick auf Undo rückgängig die Aktion. Die Snackbar verschwindet nach 10 Sekunden oder wenn der Benutzer die Seite navigiert.

Real-time Benachrichtigungen nutzen WebSocket (oder Server-Sent Events als Fallback) für sofortige Delivery. Ein "Verbindungsstatus" Indicator (grüner Punkt für verbunden, grauer für getrennt) ist optional neben dem Bell-Icon. Falls Verbindung unterbrochen und Benachrichtigung würde verpasst werden, wird diese beim Wiederherstellen der Verbindung nachgeliefert.

Multi-Channel Delivery: Benachrichtigungen werden über mehrere Kanäle zugestellt: In-App Toast/Notification-Dropdown, optionale Browser Push-Notification (falls Benutzer diese aktiviert hat), und E-Mail-Fallback für kritische Benachrichtigungen (z.B. Überfällige Rechnung). Der Benutzer kann die Kanäle und Häufigkeit per Benachrichtigungs-Typ konfigurieren (siehe H13 Einstellungen).

## H13. Settings/Einstellungs-UI

Die Settings-Seite ist unterteilt in kategorisierte Sektionen, erreichbar über User-Profil-Menü oder Sidebar-Menü-Item. Eine Sidebar auf der linken Seite (150px breit) zeigt Kategorien als einfache Links: „Mein Profil", „Mein Team", „Firma-Einstellungen", „Rechnungen & Finanzen", „Integrationen", „Sicherheit", „Benachrichtigungen", „System/Admin" (falls Rolle Admin). Klick auf eine Kategorie wechselt den Inhalt rechts (Main Content Area).

Profil-Kategorie zeigt Felder zum Bearbeiten: Profilfoto (mit Upload-Area oder Gravatar-Integration), Name, E-Mail, Telefon, Sprache (Dropdown: Deutsch, Englisch), Zeitzone (mit Suche), Arbeitszeit/Verfügbarkeit (Dropdown oder Kalender). Ein "Passwort ändern" Abschnitt mit alter/neuer Passwort Feldern.

Mein Team zeigt Tabelle von Kollegen (nur für Admin oder Team-Lead Rollen sichtbar): Name, Rolle, E-Mail, Status (Aktiv/Inaktiv), Letzte Aktivität. Edit-Icon öffnet User-Detail Modal zum Ändern von Rolle, Berechtigungen. Ein "Team-Mitglied einladen" Button öffnet ein Modal zum Eingeben von E-Mail und Rolle; eine Einladungs-E-Mail wird versandt.

Firma-Einstellungen (Admin-only) hat mehrere Sub-Tabs: „Basis-Info" (Firmenname, Logo, Adresse, Steuernummer, Webseite), „Branding" (Primary-Farbe, Logo für Dokumente, E-Mail Footer), „Standard-Zahlungsbedingungen" (Zahlungsziel in Tagen, SEPA-Account-Info), „Standard-Gebühren/Rabatte" (prozentuale oder festbetrag Rabatte, Liefergebühren).

Rechnungen & Finanzen Kategorie (Admin-only): „Rechnungs-Einstellungen" (Nummernformat, nächste Nummer, Präfix, Rechnungszeitraum), „Zahlungsarten" (SEPA, Überweisung, PayPal, Kreditkarte - aktivieren/deaktivieren), „SEPA-Setup" (Kontoinhaber, IBAN, BIC, Gläubiger-ID).

Integrationen zeigt eine Liste von verfügbaren Services (Zapier, Make, E-Mailing-Service, Buchhaltungs-Software): für jede ist ein Connect-Button oder ein Status angezeigt (z.B. "Verbunden seit 12.3.2025"). Klick auf einen Integrations-Namen öffnet Detail-Panel mit Konfigurationsoptionen (z.B. für E-Mail: SMTP-Server, Port, Credentials eintragen - auf sichere Weise mit Password-Manager oder OAuth wenn verfügbar). Ein "Disconnect" Button trennt die Integration.

Sicherheit Kategorie: „Zwei-Faktor-Authentifizierung" (An-/Aus-Toggle mit Setup-Wizard für TOTP), „Aktive Sessions" (Liste, mit "Diese Session abmelden" Option), „Login-Benachrichtigungen" (Toggle: benachrichtige mich bei Login von neuem Gerät), „API-Keys" (Tabelle mit erstellte Keys, Letzter Zugriff, Scope, mit "Neue API-Key generieren" Button und "Widerrufen" Option).

Benachrichtigungen Kategorie: eine Tabelle von Benachrichtigungs-Typen (Projekt erstellt, Rechnung überfällig, Teamkollege zugeordnet, etc.) mit Spalten für Benachrichtigungstyp, In-App (Toggle), Push (Toggle), Email (Toggle). Ein weiterer Sub-Tab zeigt E-Mail-Digest-Einstellungen: Häufigkeit (Sofort, Täglich um 09:00, Wöchentlich Montag um 09:00) mit Dropdown.

System/Admin Kategorie (Admin-only): „Feature Flags" (Tabelle von experimentellen Features mit Toggle zum An-/Ausschalten für die Org), „Audit Log" (Tabelle: Benutzer, Aktion, Datum, IP-Adresse, mit Filtern und Export), „Webhooks" (Tabelle: Event-Type, Target-URL, aktiver Status, Letzter Aufruf, mit "Test-Webhook senden" und "Löschen" Buttons).

Alle Toggle-Settings sind mit Icon und Beschreibungstext versehen (z.B. "Sende mir E-Mails für kritische System-Alerts"). Ein "Speichern" Button am unteren Ende speichert alle Änderungen mit Success-Toast.

## H14. Dokument-Editor UI

Der Dokument-Editor ist ein WYSIWYG (What You See Is What You Get) Interface zur Erstellung und Bearbeitung von Rechnungen, Angeboten und Lieferscheinen. Das Layout ist Split-View: linke Seite (60%) zeigt den Editor mit Formularfeldern und Komponenten, rechte Seite (40%) zeigt Live-Vorschau des finalen Dokuments (wie es aussieht in PDF).

Der Editor oben hat eine Toolbar mit: Font-Family Dropdown, Font-Size Dropdown (10-48px), Bold/Italic/Underline Buttons, Text-Farbe Picker, Align-Buttons (Links/Mitte/Rechts), und List-Buttons (Bullet/Numbered). Die Seite selbst ist wie ein leeres Dokument-Template mit Platzhaltern. Eine Platzhalter-Bibliothek ist auf der linken Seite sichtbar (unter dem Editor) mit verfügbaren Variablen (z.B. {kunde.name}, {kunde.ort}, {projekt.datum}, {projekt.summe}, {rechnungsnummer}, {positionen_tabelle}). Drag-Drop oder Doppel-Klick fügt einen Platzhalter ein.

Template-Bibliothek zeigt vordefinierte Designs: Minimalistische Vorlage, Modern mit Farbe, Classic mit Firmennamen, etc. Klick auf eine Vorlage lädt diese (mit Bestätigungs-Dialog falls aktuelle Changes vorhanden). Neue Vorlagen können gespeichert werden ("Aktuelle als Vorlage speichern" Button), mit Name und Beschreibung.

Versions-Historie ist erreichbar über ein Menu-Item oder Button (History-Icon). Ein Modal zeigt eine Liste aller Versionen (mit Timestamp, Autor, kurzer Beschreibung). Klick auf eine alte Version öffnet Diff-View: alte und neue Seite nebeneinander mit Unterschieden farbcodiert hervorgehoben (Hinzugefügt=grün, Gelöscht=rot). Ein "Diese Version restore" Button setzt den Editor zurück auf diese Version.

PDF-Export wird über einen großen "PDF exportieren" Button unten rechts oder in der Toolbar ausgelöst. Ein Modal öffnet sich mit: PDF-Einstellungen (Seitengröße A4/A5/Letter, Ausrichtung Portrait/Landscape, Qualität, Passwort optional), und zwei Buttons "Download" und "E-Mail an Kunde" (öffnet eine Auswahl des Empfängers).

Unterschriften-Feld kann per Drag-Drop oder Platzhalter {unterschrift_kunde} eingefügt werden. Ein Canvas-Bereich wird danach im finalen Dokument angezeigt; der Unterzeichner kann digital (mit Maus oder Touch) unterschreiben. Alternativ kann ein Upload-Bereich zum Hochladen einer Unterschriften-Bild angeboten werden.

## H15. Kalender-UI

Der Kalender ist zentral für Projektplanung und Ressourcenmanagement. Mehrere Ansichten sind verfügbar, schaltbar über Buttons oben: Monats-Ansicht (Default), Wochen-Ansicht (7 Tage nebeneinander), Tages-Ansicht (Stunden-Grid). Mini-Kalender auf der linken Seite ermöglicht schnelle Navigation (vorherige/nächste Monat Pfeile, Klick auf Tag springt zu diesem Tag).

Farb-Kodierung ist konsistent: Projekte (Blau), Wartungsfenster (Orange), Lieferungen (Grün), Interne Meetings (Grau), Crew-Schichten (Lila). Jeder Event ist ein farbiger Block mit Event-Name und Uhrzeit (falls relevant). Hover zeigt ein Tooltip mit mehr Details (Projekt-Name, Kunde, Ressourcen, etc.).

Drag-and-Drop Event-Verschiebung: ein Event kann auf einen anderen Tag/Zeitblock gezogen werden. Ein visuelles Feedback zeigt den Ziel-Tag hervorgehoben. Nach Drop wird die Event-Uhrzeit aktualisiert (mit Bestätigungs-Dialog falls Konflikte entstehen).

Konflikt-Overlay: falls zwei Events überlappen (z.B. zwei Projekte zur gleichen Zeit oder Ressource doppelt gebucht), wird eine visuelle Markierung angezeigt (z.B. rote Markierung oder Icon). Ein "Konflikt anschauen" Link öffnet ein Detail-Modal mit Konflikt-Erklärung und Options zum Auflösen (Verschieben, Löschen, Genehmigen als Exception).

iCal/Google Calendar Sync-Status ist oben im Kalender angezeigt: „Synchronisiert mit Google Calendar" oder „Synchronisierungsfehler - letzte erfolgreiche Sync vor 2h". Ein Sync-Button triggert manuelles Sync. Ein Settings-Icon öffnet Sync-Einstellungen (Google Calendar Verbindung, welche Kalender bidirektional synchen, Auto-Sync Häufigkeit).

Recurring Events UI: beim Erstellen oder Bearbeiten eines Events kann eine "Wiederholung" Option aktiviert werden. Ein Modal öffnet sich mit Optionen: Häufigkeit (Täglich, Wöchentlich, Monatlich, Jährlich), Enddatum oder Anzahl der Wiederholungen, Wochentage für Wöchentliche Wiederholungen. Ein "Speichern" Button erstellt alle Instanzen.

## H16. Chat/Kommunikations-UI

Ein Messaging-System ermöglicht Kommunikation innerhalb von Projekten und zwischen Teamkollegen. Das Layout ist Messenger-Style: Kontaktliste auf der linken Seite (200px breit, mit Suchbar oben), Chat-Bereich rechts. Die Kontaktliste zeigt: Profilfoto (32px Avatar), Name, optional Online-Status (grüner Punkt), letzter Nachricht Preview und Timestamp. Favoriten können gepinnt sein (Stern-Icon).

Chat-Fenster rechts zeigt Konversation mit Nachrichtenhistorie (älteste oben, neueste unten). Jede Nachricht zeigt: Sender-Avatar, Name, Timestamp, Nachrichtentext, optional Anhänge oder Reaktionen. Eigene Nachrichten sind rechts aligniert (hellblauer Hintergrund), fremde Nachrichten links (grauer Hintergrund). Message-Grouping: aufeinanderfolgende Nachrichten desselben Senders werden gebündelt (nur erste hat Avatar).

Projekt-gebundene Channels: in der Kontaktliste können auch Team-Channels angezeigt werden (z.B. "#Projekt_Großveranstaltung", "#Team_Lager"). Klick auf Channel öffnet die Channel-Konversation. Ein "+" Button ermöglicht, neue Channel zu erstellen mit Name, optionaler Beschreibung, und Mitgliederliste.

@Mentions mit Autocomplete: wenn "@" eingegeben wird, öffnet sich ein Autocomplete-Dropdown mit allen Team-Mitgliedern. Auswahl eines Namens fügt @Name ein, und eine Benachrichtigung wird an die erwähnte Person versandt. Thread/Reply-System: ein Hover über eine Nachricht zeigt ein "Reply" Icon; Klick öffnet einen Thread-Panel auf der rechten Seite, wo Replies die Original-Nachricht referenzieren.

Datei-Anhänge sind Drag-Drop Zonen unterhalb des Chat-Eingabebereichs. Ein "+" Button erlaubt Datei-Upload oder Bilder-Aufnahme (mit Kamera-Icon). Hochgeladene Dateien zeigen eine Vorschau (Bild als Thumbnail, Dokumente als File-Icon mit Name). Ein Download-Icon erlaubt Download.

Read Receipts: sobald eine Nachricht gelesen wird, wird ein Häkchen angezeigt (einfaches Häkchen = gesendet, doppeltes Häkchen = gelesen). Ein Hover auf das Häkchen zeigt Zeitstempel des Lesens.

Emoji-Reactions: ein Emoji-Icon unterhalb jeder Nachricht ermöglicht, Reaktionen hinzuzufügen. Ein Emoji-Picker öffnet sich mit häufigen Emojis und Suche. Ausgewählter Emoji wird als Chip unter der Nachricht angezeigt (mit Zähler wie viele Reaktionen).

## H17. Reporting-UI

Das Report-Builder Interface erlaubt Geschäftsnutzern, Custom Reports ohne Programmierung zu erstellen. Ein Drag-Drop Interface zeigt: linke Seite eine Liste von verfügbaren Metriken (Umsatz, Anzahl Projekte, durchschn. Projektdauer, etc.) und Dimensionen (Kunde, Projekttyp, Monat, Kategorie, etc.). Rechte Seite zeigt die aktuelle Report-Konfiguration: ausgewählte Metriken, Dimensionen, Filter, Sortierung, Chart-Type.

Metriken werden von links per Drag-Drop in die "Metriken" Sektion rechts gezogen. Dimensionen werden in die "Dimensionen" Sektion gezogen (z.B. X-Achse = Monat, Y-Achse = Umsatz nach Kunde). Filter können hinzugefügt werden über ein Filter-Button: Datum-Range, Text-Match, numerische Ranges, etc. Ein "Sortierung hinzufügen" erlaubt Sortiertierfolge zu definieren.

Chart-Type Selector zeigt Optionen: Linie (Trends), Balken/Säule (Vergleiche), Kreis/Donut (Anteile), Tabelle (Details), Heatmap (Muster erkennen), Scatter (Korrelation). Auswahl eines Chart-Types aktualisiert die Vorschau sofort.

Date Range Picker hat Presets: „Heute", „7 Tage", „30 Tage", „Quartalsanfang bis jetzt", „Jahresanfang bis jetzt", „Letztes Jahr", „Custom...". Custom öffnet einen Kalender-Picker für Start- und Enddatum.

Drill-Down: Klick auf einen Datenpunkt im Chart (z.B. ein Balken) öffnet eine Detail-Ansicht mit Einzelheiten zu diesem Punkt (z.B. alle Projekte im April, wenn im Chart ein Balken für April geklickt wurde).

Export-Optionen: Button "Export" oben zeigt Dropdown mit „Als PDF", „Als Excel (Spreadsheet)", „Als CSV", „Als Dashboard-Widget". "Als Dashboard-Widget" speichert den Report und zeigt ihn als Widget im Dashboard.

Scheduling-UI: Button "Zeitplan" öffnet ein Modal zum Konfigurieren von regelmäßigen Report-Zustellungen: Häufigkeit (täglich, wöchentlich, monatlich), Wochentag/Tageszeit, Format (PDF, Excel, als E-Mail-Tabelle), An wen (Liste von E-Mail-Adressen, mit Autocomplete von Team-Mitgliedern). Ein "Test-Report versenden" Button sendet eine Sofort-Version.

Saved Reports Library: alle erstellten Reports werden in einer Bibliothek gespeichert. Ein Sidebar-Menü unter "Berichte" zeigt die Liste (mit Suchbar, Kategorien, Sortierung). Klick auf einen Report öffnet ihn zum Anschauen oder Bearbeiten.

## H18. Mobile-Spezifische UI-Patterns

Mobile-Oberflächen (< 768px) folgen etablierten Mobile-UI Patterns für Effizienz und Benutzerfreundlichkeit. Bottom Sheets werden statt vollständige Modals verwendet: ein Modal öffnet von unten statt von der Mitte, und kann nach unten weggeswiped werden (statt mit X-Button zu schließen). Dies nutzt verfügaren Platz besser auf kleinen Screens.

Pull-to-Refresh: Ziehen der Liste nach unten triggert ein Refresh (API-Call für neue Daten). Ein Spinner zeigt während des Ladens. Dies ist das Mobile-Standard-Pattern und reduziert need für Refresh-Buttons.

Swipe-Actions auf Listeneinträgen: Swipe nach links auf einen Listeneintrag zeigt Quick-Action Buttons (z.B. "Bearbeiten", "Löschen", "Archivieren"). Dies spart Platz statt Buttons direkt zu zeigen. Swipe nach rechts kann "Markieren" oder andere Aktion triggern.

Floating Action Button (FAB) sitzt unten rechts (56px Durchmesser, Primary-Farbe, weißer Icon). Dies ist für die Haupt-Aktion der Seite (z.B. "Neues Projekt", "Neuer Scan"). Druck auf FAB öffnet optional ein Speed-Dial mit mehreren sekundären Aktionen (z.B. FAB mit "+" zeigt bei Druck mehrere Buttons).

Sticky Header mit Titel + Zurück-Button: der Seiten-Titel (z.B. "Projektdetails") bleibt immer oben sichtbar, mit Zurück-Pfeil (← Icon) links zum Zurück-Navigation. Dies gibt immer Kontakt, wo man ist.

Touch-friendly Design: alle interaktive Elemente haben min. 48px Größe (Apple HIG, WCAG), mit min. 8px Abstand zwischen. Buttons sind große Ziele, keine kleinen Symbole. Text ist min. 16px (12px nur für unwichtige Labels).

Offline-Banner: falls keine Netzverbindung, erscheint ein gelbes Banner oben: "Keine Verbindung - lokal arbeitend". Synced Daten sind grün markiert, nicht-synced rot.

## H19. Onboarding- und Hilfe-UI

Der Setup-Wizard wird gezeigt, wenn neue Nutzer das System zum ersten Mal öffnen. Ein Multi-Step Wizard (ähnlich wie H5, aber fokussiert auf Basis-Setup) führt durch: Schritt 1 - "Firma Setup" (Firmenname, Logo, Adresse), Schritt 2 - "Bankdaten" (IBAN für Lastschriften), Schritt 3 - "Erster Artikel" (ein Sample-Equipment zum Starten). Jeder Schritt hat großes Icon und ermutigender Text ("Fast fertig!"). Ein Skip-Button erlaubt, Setup später zu vollenden.

Feature-Tour wird nach Onboarding auf spezifischen Seiten angezeigt. Ein Spotlight-Overlay highlights ein Bereich (mit semi-transparentem Overlay rings herum), und ein Tooltip erklärt die Funktion ("Dies ist der Crew-Planner. Drag-drop Aufgaben hier um zu planen."). Pfeile (← →) erlauben, zwischen Tour-Steps zu navigieren. Ein "Skip Tour" Button beendet die Tour. Tours werden pro Feature konfiguriert und können mehrfach angezeigt werden (oder "Don't show again" Option).

Contextual Help: neben Formularfeldern und Funktionen ist ein kleines Info-Icon (?) sichtbar. Klick zeigt ein Tooltip oder öffnet ein Help-Panel mit erweiterte Erklärung und Link zur Dokumentation/Video. Ein "Help-Center öffnen" Link navigiert zur externen Dokumentations-Website.

Empty States (leere Ansichten): wenn eine Liste leer ist (z.B. keine Projekte), wird eine motivierende Grafik angezeigt (Illustration mit Projekt-Symbol), kurzer Text ("Noch keine Projekte"), und ein großer "Erstes Projekt erstellen" CTA Button. Dies ist besser als leere weiße Fläche.

Keyboard Shortcuts Overlay: drücken der "?" Taste öffnet ein Modal mit einer Tabelle aller verfügbaren Shortcuts (z.B. "Ctrl+K = Command Palette", "? = Diese Hilfe", "D = Dashboard", etc.). Diese sind nach Kategorie gruppiert.

What's New Modal: nach Deployment von neuen Features, wird beim nächsten Login ein Modal angezeigt mit "Was gibt's Neues" Überschrift und Bullet Points von Major Changes. Bilder/GIFs zeigen die neuen Features. Ein "Verstanden" Button schließt das Modal (mit Option "Nicht mehr zeigen").

In-App Feedback Widget: ein kleiner Icon (Sprechblase oder Feedback-Icon) in der unteren rechten Ecke erlaubt Benutzer, Feedback/Bug-Reports zu senden. Klick öffnet ein Modal mit Kategorie-Select (Bug, Feature-Request, Feedback), Nachricht-Textarea, optionaler Screenshot-Upload (Screenshot-Button öffnet ein Tool zum Bereich auszuwählen). Ein "Senden" Button sendet das Feedback an das Team (via E-Mail oder Ticketing-System).

## H20. Barrierefreiheit (Accessibility UI)

MyRMS ist vollständig barrierefrei für Nutzer mit verschiedenen Fähigkeiten, im Einklang mit WCAG 2.1 Stufe AA. Keyboard-Only Navigation ist möglich: alle Funktionen (nicht nur Links) sind via Tastatur erreichbar. Tab-Taste navigiert durch fokussierbare Elemente in logischer Reihenfolge, Shift+Tab geht zurück. Enter und Space aktivieren Buttons und Links. Pfeiltasten navigieren in Menüs und Listen. Escape schließt Modals und Dropdowns.

Focus Management ist korrekt implementiert: die Focus-Outline ist sichtbar (1-2px Border in Primary-Farbe oder Kontrast-Farbe), kein Element hat `outline: none` ohne sichtbare Alternative. In Modals wird Focus "getrappt" (Tab innerhalb des Modal zirkuliert nicht zum Hintergrund). Nach Schließen eines Modal springt Focus zurück zum öffnenden Element.

Skip Links: am Anfang jeder Seite ist ein Skip-Link vorhanden ("Skip to main content"), der fokussierbar ist (nur bei Keyboard-Navigation sichtbar), und erlaubt zu springen über Navigation direkt zum Haupt-Inhalt.

Screen Reader Kompatibilität: alle visuellen Informationen sind auch für Screen Reader erreichbar. Bilder haben alt-Text. Icons haben aria-label. Formularfelder haben explizite Labels (nicht nur Placeholder). Tabellen haben richtige thead/tbody/th Struktur. Statusänderungen werden über aria-live regions angekündigt (z.B. "Datei hochgeladen" Meldung).

Kontrastanforderungen: alle Text hat min. 4.5:1 Kontrast-Verhältnis gegen Hintergrund (WCAG AA). Dies ist auch im Color-System berücksichtigt (Primary-Blau auf Weiß, Rot auf Grau, etc.).

Barrierefreiheits-Modus kann in Settings aktiviert werden: ein Toggle "Erhöhter Kontrast" invertiert Farben für bessere Sichtbarkeit (dunkle Hintergründe, helle Text). Ein weitere Toggle "Reduzierte Bewegung" deaktiviert alle Animationen und Übergänge (statt 300ms Fade wird sofort angezeigt).

Spracheinstellungen: das System unterstützt mehrere Sprachen (Deutsch, Englisch) mit rechtzeitiger Umschaltung. Formularfelder und Fehler-Meldungen folgen Spracheinstellung.

Text-Skalierung: die Seite unterstützt Browser-Zoom bis zu 200% ohne Funktionsverlust (Layout sollte responsive bleiben). Text-Größe sollte nicht via px sondern rem/em definiert sein, um Zoom zu respektieren.

Dies komplettiert die comprehensive UI-Design Spezifikation für MyRMS mit allen 20 Sektionen, jede mit detaillierter Beschreibung und Referenzen zu Best Practices aus Competitors und modernen SaaS Standards.

---

**Document Version:** 3.0
**Last Updated:** March 18, 2026

---

# Part I: Multi-KI-Modell-Integration und API-Anbindung

## Überblick und Strategie

MyRMS setzt bereits auf KI-Funktionen (E-Mail-Entwürfe, Schadensbericht-Zusammenfassungen, AI Action Queue via Claude API). Dieses Kapitel erweitert die KI-Architektur grundlegend: Statt fest an einen einzigen Anbieter gebunden zu sein, wird ein **Provider-agnostisches Adapter-System** implementiert, das es Administratoren erlaubt, verschiedene KI-Modelle über die Oberfläche auszuwählen, zu konfigurieren und für unterschiedliche Aufgaben einzusetzen. Das Ziel ist maximale Flexibilität: Der Benutzer entscheidet selbst, ob er Cloud-APIs (OpenAI, Anthropic Claude, Google Gemini, Mistral) nutzt, oder ob er aus Datenschutzgründen ein lokal gehostetes Modell via Ollama oder vLLM einsetzt – alles konfigurierbar über die Settings-UI, ohne eine einzige Zeile Code anfassen zu müssen.

Dieses Design folgt dem **Provider Strategy Pattern** – einer Kombination aus Strategy-Pattern (jeder Provider implementiert dasselbe Interface) und Adapter-Pattern (Provider übersetzen externe API-Formate in interne Contracts). In PHP existieren bereits ausgereifte Bibliotheken dafür: **php-llm/llm-chain** bietet eine universelle Abstraktionsschicht für LLM-basierte Features, **Neuron AI** liefert ein komplettes Agent-Framework mit Tool-Support und Orchestrierung, und **Prism** bietet eine saubere Abstraction Layer über verschiedene LLM-Provider. MyRMS nutzt ein eigenes leichtgewichtiges Adapter-System, das auf diesen Konzepten aufbaut, aber spezifisch auf die Anforderungen eines Rental Management Systems zugeschnitten ist.

---

## I1. Unterstützte KI-Provider und Modelle

### I1.1 Cloud-basierte Provider

#### OpenAI (GPT-Modelle)

OpenAI ist der bekannteste KI-Anbieter und bietet eine breite Palette an Modellen für unterschiedliche Anforderungen. Die aktuelle Modellreihe umfasst GPT-4o als Flaggschiff für komplexe Aufgaben wie Vertragsanalyse, Angebotsoptimierung und intelligente Berichtserstellung, GPT-4o-mini als kosteneffiziente Alternative für alltägliche Aufgaben wie E-Mail-Entwürfe und einfache Zusammenfassungen, sowie die o1/o3-Reasoning-Modelle für besonders anspruchsvolle analytische Aufgaben wie Finanzprognosen und Anomalieerkennung. Die Preisgestaltung folgt einem Token-basierten Modell: GPT-4o liegt bei ca. $2.50/$10 pro Million Token (Input/Output), GPT-4o-mini bei ca. $0.15/$0.60, was es zu einer sehr wirtschaftlichen Wahl für Massenaufgaben macht.

**API-Key beschaffen – Schritt für Schritt:**
1. Besuche **platform.openai.com** (das ist die API-Plattform, nicht chatgpt.com)
2. Klicke auf „Sign up" und erstelle einen Account mit E-Mail oder Google/Microsoft SSO
3. Nach der Registrierung gehe zu **Settings → Billing** und hinterlege eine Kreditkarte (Prepaid möglich)
4. Navigiere zu **API Keys** im linken Menü (oder direkt: platform.openai.com/api-keys)
5. Klicke „Create new secret key", vergib einen Namen (z.B. „MyRMS Production")
6. **Wichtig:** Der Key wird nur einmal angezeigt – sofort kopieren und sicher speichern
7. Der Key beginnt mit `sk-` und wird in MyRMS unter Einstellungen → KI-Konfiguration → OpenAI eingegeben
8. Optional: Setze ein monatliches Spending Limit unter Settings → Limits (z.B. $50/Monat)

**Empfohlene Nutzung in MyRMS:** GPT-4o-mini als Standard für E-Mail-Drafts, Zusammenfassungen und Chat-Antworten (kostengünstig bei hoher Qualität). GPT-4o für komplexe Aufgaben wie Vertragsanalyse, Preisoptimierung und detaillierte Berichte. Die o-Serie für Predictive Analytics und Demand Forecasting wo präzise Reasoning wichtig ist.

#### Anthropic Claude

Anthropic Claude ist bekannt für besonders sichere, zuverlässige und kontexttreue Antworten und ist derzeit der primäre Provider in MyRMS. Die Modellreihe umfasst Claude Opus (das leistungsstärkste Modell für komplexe Analysen, ca. $15/$75 pro MTok), Claude Sonnet (der beste Allrounder mit exzellentem Preis-Leistungs-Verhältnis, ca. $3/$15 pro MTok) und Claude Haiku (das schnellste und günstigste Modell für einfache Aufgaben, ca. $0.25/$1.25 pro MTok). Claude hat ein besonders großes Kontextfenster (bis 200K Tokens), was ideal für die Verarbeitung langer Dokumente wie Verträge, Lieferscheine oder umfangreiche Schadensberichte ist.

**API-Key beschaffen – Schritt für Schritt:**
1. Besuche **console.anthropic.com** und erstelle einen Account
2. Verifiziere deine E-Mail-Adresse
3. Gehe zu **Settings → Billing** und hinterlege eine Zahlungsmethode (Kreditkarte)
4. Neue Accounts erhalten häufig ein kleines Startguthaben (ca. $5) zum Testen
5. Navigiere zu **API Keys** im Dashboard
6. Klicke „Create Key", vergib einen beschreibenden Namen (z.B. „MyRMS-Prod-2026")
7. **Wichtig:** Der Key wird nur einmal angezeigt – sofort sicher abspeichern
8. Der Key beginnt mit `sk-ant-` und wird in MyRMS unter Einstellungen → KI-Konfiguration → Claude eingegeben
9. Optional: Erstelle separate Keys für Development und Production mit unterschiedlichen Rate Limits

**Empfohlene Nutzung in MyRMS:** Claude Haiku als Standard für schnelle Aufgaben (E-Mail-Entwürfe, kurze Zusammenfassungen, Chat-Antworten). Claude Sonnet für mittelschwere Aufgaben (Schadensberichte, Angebotsanalyse, Kundenkorrespondenz). Claude Opus für Premium-Features (Vertragsanalyse, umfassende Finanzberichte, komplexe Datenanalysen).

#### Google Gemini

Google Gemini bietet eine wettbewerbsfähige Alternative mit besonders starker multimodaler Fähigkeit (Text + Bild + Audio + Video). Die Modellreihe umfasst Gemini 2.5 Pro (das leistungsstärkste Modell mit 1M Token Kontext, ideal für umfangreiche Dokumentanalyse), Gemini 2.5 Flash (schnell und kostengünstig für Standardaufgaben), sowie Gemini 2.0 Flash Lite (extrem günstig für einfache Aufgaben). Ein besonderer Vorteil von Gemini ist die kostenlose Stufe: Gemini bietet großzügige Free-Tier-Limits, die für kleine Instanzen oder Testumgebungen ausreichen können.

**API-Key beschaffen – Schritt für Schritt:**
1. Besuche **aistudio.google.com** (Google AI Studio)
2. Melde dich mit deinem Google-Account an
3. Klicke auf „Get API key" im linken Menü
4. Wähle „Create API key in new project" oder wähle ein bestehendes Google Cloud Project
5. Der Key wird sofort generiert und angezeigt – kopieren und sicher speichern
6. Der Key beginnt mit `AIza` und wird in MyRMS unter Einstellungen → KI-Konfiguration → Gemini eingegeben
7. **Kostenlos starten:** Die Free Tier erlaubt bis zu 15 Requests/Minute und 1 Million Tokens/Tag für Flash-Modelle
8. Für höhere Limits: Aktiviere Billing im Google Cloud Console (console.cloud.google.com)

**Empfohlene Nutzung in MyRMS:** Gemini Flash als kosteneffiziente Alternative für Standard-KI-Aufgaben. Gemini Pro für multimodale Aufgaben wie OCR von Rechnungen/Lieferscheinen (Foto → strukturierte Daten), Schadensdokumentation mit Bildanalyse und Analyse von Equipment-Fotos für Zustandsbewertung.

#### Mistral AI

Mistral AI ist ein europäischer KI-Anbieter (Frankreich) und bietet besonders datenschutzfreundliche Optionen, die für den deutschen Markt und DSGVO-Konformität relevant sein können. Die Modellreihe umfasst Mistral Large (das leistungsstärkste Modell, vergleichbar mit GPT-4o), Mistral Small (hervorragendes Preis-Leistungs-Verhältnis bei ca. $0.20/$0.60 pro MTok – einer der günstigsten Anbieter überhaupt), und Mistral Nemo (Open-Source, extrem günstig bei $0.02/$0.02). Ein wichtiger Vorteil: Als europäisches Unternehmen unterliegt Mistral direkt der EU-Datenschutzverordnung, was die DSGVO-Konformität vereinfacht.

**API-Key beschaffen – Schritt für Schritt:**
1. Besuche **console.mistral.ai** und erstelle einen Account
2. Aktiviere Billing: Wähle einen Plan (Free Tier verfügbar mit Limits, oder Pay-as-you-go)
3. Hinterlege eine Zahlungsmethode zur Aktivierung
4. Klicke im linken Menü auf **API keys**
5. Klicke „Create new key", vergib einen Namen und ein Ablaufdatum
6. **Wichtig:** Key wird nur einmal angezeigt – sofort sicher abspeichern
7. Der Key wird in MyRMS unter Einstellungen → KI-Konfiguration → Mistral eingegeben

**Empfohlene Nutzung in MyRMS:** Mistral Small als extrem kostengünstige Option für hohe Volumina (z.B. automatische Kategorisierung aller eingehenden E-Mails, Bulk-Beschreibungen für Asset-Katalog). Mistral Large als europäische Premium-Alternative wenn DSGVO-Konformität mit EU-Hosting Priorität hat.

#### Weitere Cloud-Provider (erweiterbar)

Die Adapter-Architektur erlaubt einfache Erweiterung um zusätzliche Provider. Potenzielle Kandidaten sind: **Cohere** (spezialisiert auf Enterprise-Suche und Retrieval Augmented Generation, ideal für Dokumentensuche), **DeepSeek** (chinesischer Anbieter mit sehr günstigen Preisen, ab $0.07/MTok, aber Datenschutzbedenken bei europäischem Einsatz), und **xAI Grok** (Elon Musks Modell mit Echtzeit-Zugang zu aktuellen Informationen).

### I1.2 Lokal gehostete Modelle (Self-Hosted)

#### Ollama – Lokale KI ohne Cloud

Ollama ist die wichtigste Option für Unternehmen, die keine Daten an externe Cloud-Dienste senden wollen oder können. Ollama funktioniert wie Docker, aber für KI-Modelle: Man kann Modelle mit einem einzigen Befehl herunterladen und lokal ausführen. Ollama verwaltet automatisch das Herunterladen der Modellgewichte, das Speichermanagement und das Serving über eine lokale REST-API. Der entscheidende Vorteil: **Alle Daten bleiben auf dem eigenen Server** – es findet keinerlei Datenübertragung an Dritte statt. Das vereinfacht die DSGVO-Konformität enorm, da keine Auftragsverarbeitungsverträge (AVV) mit Cloud-Anbietern geschlossen werden müssen und kein Risiko besteht, dass ein Cloud-Provider versehentlich Daten loggt oder nutzt. Auch für HIPAA und SOC 2 Compliance ist Self-Hosting ideal.

Die lokale REST-API ist kompatibel mit dem OpenAI-API-Format. Das bedeutet: Jede Anwendung, die mit OpenAI kommunizieren kann, kann ohne Code-Änderungen auf Ollama umgestellt werden – man ändert lediglich die Base-URL von `api.openai.com` auf `localhost:11434` und entfernt die Authentifizierung. Für MyRMS bedeutet das: Der gleiche Adapter, der OpenAI bedient, kann mit minimaler Konfigurationsänderung auf Ollama umgestellt werden.

**Einrichtung – Schritt für Schritt:**
1. **Installation:** `curl -fsSL https://ollama.com/install.sh | sh` (Linux/Mac) oder Installer von ollama.com (Windows)
2. **Modell herunterladen:** `ollama pull llama3.1:8b` (8B-Modell, ca. 4.7 GB, läuft auf den meisten modernen PCs)
3. **Server starten:** `ollama serve` (läuft standardmäßig auf `http://localhost:11434`)
4. **Testen:** `curl http://localhost:11434/api/generate -d '{"model":"llama3.1:8b","prompt":"Hallo"}'`
5. In MyRMS: Einstellungen → KI-Konfiguration → Provider „Ollama (Lokal)" wählen
6. Server-URL eingeben: `http://localhost:11434` (oder die IP des Servers im lokalen Netzwerk)
7. Modell auswählen aus der Liste verfügbarer Modelle (MyRMS fragt automatisch die installierte Modell-Liste ab)

**Hardware-Anforderungen:**
- 7B-Modelle (z.B. Llama 3.1 8B, Mistral 7B): mind. 8 GB RAM, besser 16 GB
- 13B-Modelle: mind. 16 GB RAM
- 70B-Modelle (beste Qualität): mind. 64 GB RAM oder GPU mit 48 GB VRAM
- Empfehlung für MyRMS auf Synology NAS: Llama 3.1 8B oder Mistral 7B (guter Kompromiss aus Qualität und Ressourcen)

**Empfohlene Modelle für MyRMS:**
- **Llama 3.1 8B:** Bester Allrounder für lokales Hosting, gut für deutsche Sprache
- **Mistral 7B:** Sehr effizient, gute Qualität bei niedrigem Ressourcenverbrauch
- **Gemma 2 9B:** Googles Open-Source-Modell, stark bei Instruktionsbefolgung
- **Phi-3 Mini:** Microsofts kompaktes Modell, ideal für schwache Hardware
- **DeepSeek Coder 7B:** Spezialisiert auf Code-Generierung (z.B. für Report-Queries)

#### vLLM – Produktions-Hosting für lokale Modelle

Für größere Installationen mit höheren Anforderungen an Durchsatz und Zuverlässigkeit bietet vLLM eine professionelle Alternative zu Ollama. vLLM ist optimiert für Production-Workloads mit Features wie Continuous Batching (mehrere Anfragen gleichzeitig verarbeiten), PagedAttention (effizientere GPU-Speichernutzung) und OpenAI-kompatibler API. Die Empfehlung lautet: Ollama für Entwicklung und kleine Instanzen verwenden, für Production-Umgebungen mit hohem Durchsatz auf vLLM migrieren.

---

## I2. Architektur: Provider-agnostisches Adapter-System

### I2.1 Interface-Design (LlmProviderInterface)

Das Herzstück der Multi-KI-Architektur ist ein PHP-Interface, das alle Provider implementieren müssen. Dieses Interface definiert eine einheitliche API, unabhängig davon welcher Provider dahinter steht:

```php
<?php
namespace App\Services\AI;

interface LlmProviderInterface
{
    /**
     * Sendet einen Chat-Completion-Request an den Provider.
     * @param array $messages Array von ['role' => 'user|assistant|system', 'content' => '...']
     * @param array $options Optionale Parameter (temperature, max_tokens, etc.)
     * @return LlmResponse Standardisierte Antwort
     */
    public function chatCompletion(array $messages, array $options = []): LlmResponse;

    /**
     * Prüft ob der Provider konfiguriert und erreichbar ist.
     * @return bool True wenn der Provider funktionsfähig ist
     */
    public function isAvailable(): bool;

    /**
     * Gibt die Liste der verfügbaren Modelle zurück.
     * @return array Liste der Modell-IDs und Namen
     */
    public function listModels(): array;

    /**
     * Gibt den Namen des Providers zurück (für UI-Anzeige).
     */
    public function getProviderName(): string;

    /**
     * Gibt die geschätzten Kosten pro 1K Tokens zurück.
     * @return array ['input' => float, 'output' => float, 'currency' => 'USD']
     */
    public function getEstimatedCost(): array;

    /**
     * Unterstützt der Provider multimodale Eingaben (Bilder)?
     */
    public function supportsVision(): bool;

    /**
     * Unterstützt der Provider Streaming-Responses?
     */
    public function supportsStreaming(): bool;
}
```

### I2.2 Adapter-Implementierungen

Jeder Provider erhält einen eigenen Adapter, der das Interface implementiert und die spezifische API-Kommunikation kapselt. Beispiel für den OpenAI-Adapter:

```php
<?php
namespace App\Services\AI\Providers;

class OpenAiAdapter implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function chatCompletion(array $messages, array $options = []): LlmResponse
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2048,
        ];

        $response = $this->httpPost('/chat/completions', $payload);
        return LlmResponse::fromOpenAiFormat($response);
    }

    // ... weitere Methoden
}
```

Der entscheidende Vorteil dieser Architektur: Der Ollama-Adapter kann die gleiche Codebase wie der OpenAI-Adapter nutzen, da Ollama eine OpenAI-kompatible API bereitstellt. Man ändert lediglich die Base-URL:

```php
class OllamaAdapter extends OpenAiAdapter
{
    public function __construct(string $serverUrl = 'http://localhost:11434', string $model = 'llama3.1:8b')
    {
        parent::__construct('', $model); // Kein API-Key nötig
        $this->baseUrl = $serverUrl . '/v1';
    }
}
```

### I2.3 Provider-Registry und Factory

Eine Provider-Registry verwaltet alle konfigurierten Provider und ermöglicht die Auswahl über die UI:

```php
class AiProviderRegistry
{
    private array $providers = [];

    public function register(string $name, LlmProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function get(string $name): LlmProviderInterface
    {
        return $this->providers[$name]
            ?? throw new \InvalidArgumentException("Provider '$name' nicht registriert");
    }

    public function getDefault(): LlmProviderInterface
    {
        $defaultName = Config::get('ai.default_provider', 'claude');
        return $this->get($defaultName);
    }

    public function listAvailable(): array
    {
        return array_map(fn($p) => [
            'name' => $p->getProviderName(),
            'available' => $p->isAvailable(),
            'models' => $p->listModels(),
            'cost' => $p->getEstimatedCost(),
            'vision' => $p->supportsVision(),
            'streaming' => $p->supportsStreaming(),
        ], $this->providers);
    }
}
```

### I2.4 Task-basiertes Routing

Verschiedene KI-Aufgaben haben unterschiedliche Anforderungen. MyRMS implementiert ein Task-basiertes Routing, das automatisch den optimalen Provider und das optimale Modell für jede Aufgabe auswählt – konfigurierbar über die Settings-UI:

```
Task-Routing-Konfiguration (Settings → KI → Task-Routing):

┌────────────────────────────┬──────────────┬─────────────────┬───────────┐
│ Aufgabe                    │ Provider     │ Modell          │ Priorität │
├────────────────────────────┼──────────────┼─────────────────┼───────────┤
│ E-Mail-Entwürfe            │ Claude       │ Haiku           │ Schnell   │
│ Schadensbericht-Zusammenfassung │ Claude  │ Sonnet          │ Qualität  │
│ Rechnungs-OCR (Bild → Text)│ Gemini      │ Flash           │ Vision    │
│ Demand Forecasting         │ OpenAI       │ o1              │ Reasoning │
│ Chat-Antworten             │ Ollama       │ Llama 3.1 8B    │ Lokal     │
│ Preisoptimierung           │ OpenAI       │ GPT-4o          │ Qualität  │
│ Bulk-Beschreibungen        │ Mistral      │ Small           │ Budget    │
│ Vertragsanalyse            │ Claude       │ Opus            │ Premium   │
└────────────────────────────┴──────────────┴─────────────────┴───────────┘
```

### I2.5 Fallback-Mechanismus

Wenn ein Provider nicht erreichbar ist (API-Ausfall, Rate-Limit erreicht, lokaler Server offline), greift automatisch ein Fallback-Mechanismus:

Die Fallback-Kette wird in der Konfiguration definiert (z.B. Claude → OpenAI → Ollama → Fehler-Meldung). Bei einem Fehler loggt das System den Ausfall, versucht den nächsten Provider in der Kette, und benachrichtigt den Admin per Toast-Notification wenn der Primary Provider ausfällt. Der Benutzer merkt idealerweise nichts vom Fallback – die Antwort kommt einfach von einem anderen Modell. Im Admin-Dashboard wird der aktuelle Provider-Status als Health-Widget angezeigt (Grün/Gelb/Rot pro Provider).

---

## I3. Settings-UI für KI-Konfiguration

### I3.1 Haupt-Settings-Seite (Einstellungen → KI-Konfiguration)

Die KI-Konfigurationsseite ist über die Settings-Sidebar unter der Kategorie „Integrationen" erreichbar und gliedert sich in folgende Bereiche:

**Provider-Übersicht (Dashboard-Karte oben):**
Eine horizontale Karten-Reihe zeigt alle konfigurierten Provider mit Status-Badge (Grün = aktiv, Grau = nicht konfiguriert, Rot = Fehler), dem aktuellen Modell, den geschätzten Kosten pro 1K Tokens, und einem Quick-Test-Button (sendet eine Test-Anfrage und zeigt die Antwortzeit). Der aktuell als Standard gesetzte Provider ist mit einem blauen Stern-Badge markiert.

**Provider-Konfiguration (Tab-basiert):**
Jeder Provider hat einen eigenen Tab mit den jeweiligen Konfigurationsfeldern:

```
┌─────────────────────────────────────────────────────────┐
│ KI-Konfiguration                                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  [OpenAI] [Claude] [Gemini] [Mistral] [Ollama] [+Mehr] │
│                                                         │
│  ┌─ Claude (Anthropic) ──────────────────────────────┐  │
│  │                                                    │  │
│  │  API-Key:  [sk-ant-••••••••••••••] [👁] [🔄 Neu]  │  │
│  │                                                    │  │
│  │  Modell:   [Claude Sonnet 4.6          ▼]         │  │
│  │                                                    │  │
│  │  Temperatur: [0.7] ────●───────── (0.0 - 1.0)    │  │
│  │                                                    │  │
│  │  Max Tokens: [2048] (Maximale Antwortlänge)       │  │
│  │                                                    │  │
│  │  Status:    ● Verbunden (Antwortzeit: 230ms)      │  │
│  │                                                    │  │
│  │  [Verbindung testen]  [Als Standard setzen]       │  │
│  │                                                    │  │
│  │  Nutzung diesen Monat: 245K Tokens (≈ €0.73)     │  │
│  │                                                    │  │
│  └────────────────────────────────────────────────────┘  │
│                                                         │
│  ┌─ Task-Routing ────────────────────────────────────┐  │
│  │                                                    │  │
│  │  E-Mail-Entwürfe:      [Claude Haiku         ▼]  │  │
│  │  Zusammenfassungen:    [Claude Sonnet        ▼]  │  │
│  │  OCR/Bildanalyse:      [Gemini Flash         ▼]  │  │
│  │  Chat-Antworten:       [Standard-Provider    ▼]  │  │
│  │  Analytik/Prognosen:   [OpenAI o1            ▼]  │  │
│  │  Bulk-Operationen:     [Mistral Small        ▼]  │  │
│  │                                                    │  │
│  │  [+ Eigene Regel hinzufügen]                      │  │
│  └────────────────────────────────────────────────────┘  │
│                                                         │
│  ┌─ Fallback-Konfiguration ──────────────────────────┐  │
│  │                                                    │  │
│  │  Reihenfolge (Drag-and-Drop):                     │  │
│  │  1. ≡ Claude (Primary)                            │  │
│  │  2. ≡ OpenAI (Fallback 1)                        │  │
│  │  3. ≡ Ollama (Fallback 2)                        │  │
│  │                                                    │  │
│  │  ☑ Admin benachrichtigen bei Fallback-Aktivierung │  │
│  │  ☑ Fallback-Events im Audit-Log protokollieren    │  │
│  └────────────────────────────────────────────────────┘  │
│                                                         │
│  ┌─ Kosten & Limits ─────────────────────────────────┐  │
│  │                                                    │  │
│  │  Monatliches Budget:   [€50.00        ]           │  │
│  │  ☑ KI-Funktionen deaktivieren wenn Budget erreicht│  │
│  │  ☑ Warnung bei 80% des Budgets                    │  │
│  │                                                    │  │
│  │  Nutzungsübersicht:                               │  │
│  │  ████████░░ 62% (€31.00 / €50.00)                │  │
│  │                                                    │  │
│  │  Top-Verbraucher:                                 │  │
│  │  • E-Mail-Drafts: 145K Tokens (€0.44)            │  │
│  │  • Schadenberichte: 89K Tokens (€2.67)            │  │
│  │  • Chat-Antworten: 234K Tokens (€0.70)            │  │
│  └────────────────────────────────────────────────────┘  │
│                                                         │
│  [Änderungen speichern]  [Auf Standard zurücksetzen]    │
└─────────────────────────────────────────────────────────┘
```

### I3.2 API-Key Sicherheit

API-Keys werden niemals im Klartext gespeichert. In der Datenbank werden sie mit AES-256-GCM verschlüsselt, wobei der Encryption-Key aus einer Umgebungsvariable (`AI_ENCRYPTION_KEY`) abgeleitet wird. In der UI werden Keys maskiert dargestellt (`sk-ant-••••••••xxxx` – nur die letzten 4 Zeichen sichtbar) mit einem optionalen „Anzeigen"-Toggle (Auge-Icon) für kurzzeitige Ansicht. Beim Rotieren eines Keys (🔄-Button) wird der alte Key sofort invalidiert und ein Hinweis angezeigt, den neuen Key beim Provider zu generieren. Die Key-Eingabe validiert das Format automatisch (z.B. muss ein OpenAI-Key mit `sk-` beginnen, ein Claude-Key mit `sk-ant-`) und zeigt sofort eine Fehlermeldung bei ungültigem Format.

### I3.3 Ollama-spezifische Settings

Für Ollama gibt es zusätzliche Konfigurationsoptionen, da es sich um ein lokal gehostetes System handelt:

```
┌─ Ollama (Lokal) ─────────────────────────────────────┐
│                                                       │
│  Server-URL:    [http://localhost:11434    ]          │
│                 (oder IP im Netzwerk, z.B. 192.168..) │
│                                                       │
│  Status:        ● Server erreichbar                   │
│                                                       │
│  Installierte Modelle:                                │
│  ┌───────────────┬──────────┬─────────┬────────────┐ │
│  │ Modell        │ Größe    │ Status  │ Aktion     │ │
│  ├───────────────┼──────────┼─────────┼────────────┤ │
│  │ llama3.1:8b   │ 4.7 GB   │ ● Bereit│ [Entfernen]│ │
│  │ mistral:7b    │ 4.1 GB   │ ● Bereit│ [Entfernen]│ │
│  │ gemma2:9b     │ 5.4 GB   │ ↓ 67%  │ [Abbrechen]│ │
│  └───────────────┴──────────┴─────────┴────────────┘ │
│                                                       │
│  [+ Modell herunterladen ▼]                           │
│   ├── llama3.1:70b (39 GB) - Beste Qualität          │
│   ├── phi3:mini (2.3 GB) - Schnellstes               │
│   ├── deepseek-coder:7b (4 GB) - Code-Spezialist     │
│   └── [Eigenen Modellnamen eingeben...]               │
│                                                       │
│  GPU-Beschleunigung:  [Auto ▼] (CUDA / Metal / CPU)  │
│  Gleichzeitige Anfragen: [2 ▼] (abhängig von RAM)    │
│  Keep-Alive: [5 Minuten ▼] (Modell im RAM halten)    │
│                                                       │
│  Systemressourcen:                                    │
│  RAM:  ████████████░░░░ 12.4 / 16.0 GB               │
│  GPU:  ██████░░░░░░░░░░ 4.2 / 8.0 GB VRAM            │
│  CPU:  ████░░░░░░░░░░░░ 25%                           │
│                                                       │
│  [Verbindung testen] [Server neu starten]             │
└───────────────────────────────────────────────────────┘
```

---

## I4. KI-Features und ihre Provider-Zuordnung

### I4.1 Bestehende KI-Features (bereits implementiert)

Diese Features nutzen aktuell die Claude API und werden auf das Multi-Provider-System umgestellt:

**E-Mail-Drafting Service (ClaudeService):** Generiert professionelle E-Mail-Entwürfe basierend auf Kontext (Kunde, Projekt, vorherige Kommunikation). Wird umgestellt auf den Task-Route „email_drafts", standardmäßig Claude Haiku wegen der guten Balance aus Qualität, Geschwindigkeit und Kosten.

**Schadensbericht-Zusammenfassungen:** Fasst detaillierte Schadensberichte in prägnante Zusammenfassungen zusammen, extrahiert Kernpunkte und empfiehlt Maßnahmen. Bleibt bei Claude Sonnet wegen der Notwendigkeit nuancierter Textanalyse.

**AI Action Queue mit Approval-System:** Autonome KI-gesteuerte Aktionen (z.B. „Mahnungs-E-Mail vorschlagen", „Wiedervorlage erstellen") werden in eine Queue gestellt und vom Benutzer bestätigt oder abgelehnt. Jede Aktion zeigt an, welcher Provider/welches Modell sie generiert hat.

### I4.2 Neue KI-Features (durch Multi-Provider ermöglicht)

**Rechnungs-OCR und Dokumentenanalyse:** Eingehende Rechnungen (PDF, Foto) werden automatisch analysiert und die strukturierten Daten (Rechnungsnummer, Betrag, Positionen, USt-ID) extrahiert. Dieses Feature profitiert besonders von Gemini's multimodaler Fähigkeit (Bild-zu-Text) oder Claude's Vision-Fähigkeit. In der UI wird beim Upload eines Dokuments ein „KI-Analyse"-Button angezeigt, der das Dokument an den konfigurierten Vision-Provider sendet und die erkannten Felder in ein Formular einträgt, das der Benutzer überprüfen und bestätigen kann.

**Smart Pricing / Preisvorschläge:** Basierend auf historischen Buchungsdaten, Saison, Nachfrage und Wettbewerbspreisen schlägt die KI optimale Mietpreise vor. Dieses Feature nutzt Reasoning-Modelle (o1/o3 oder Claude Opus) und wird als Widget im Equipment-Detail angezeigt: „KI-Preisvorschlag: €65/Tag (aktuell: €55/Tag, Begründung: Hohe Nachfrage im April, 3 Konkurrenten bei €70-80)".

**Automatische Asset-Beschreibungen:** Beim Anlegen neuer Assets generiert die KI automatisch eine professionelle Beschreibung basierend auf Kategorie, Hersteller, Modell und technischen Daten. Für Bulk-Import von 100+ Assets wird Mistral Small empfohlen (extrem günstig bei hohen Volumina).

**Intelligente Kundenkorrespondenz:** Die KI analysiert die Kommunikationshistorie eines Kunden und schlägt kontextbezogene Follow-up-Aktionen vor: „Kunde hat seit 3 Monaten nicht gebucht → Vorschlag: Personalisiertes Rückgewinnungs-Angebot senden". Die Vorschläge erscheinen als Info-Card auf der Kunden-Detailseite.

**Demand Forecasting Dashboard-Widget:** Ein Dashboard-Widget zeigt KI-basierte Prognosen für die kommenden 30/60/90 Tage: erwartete Buchungen, Umsatzprognose, Equipment-Engpässe. Nutzt historische Daten und saisonale Muster. Reasoning-Modelle (OpenAI o1 oder Claude Opus) liefern hier die besten Ergebnisse.

**Chat-basierter Assistent (MyRMS Copilot):** Ein Chat-Widget (Bottom-Right, expandierbar) erlaubt natürlichsprachige Fragen an das System: „Welche Assets sind nächste Woche verfügbar?", „Erstelle eine Rechnung für Kunde Müller über das letzte Projekt", „Zeige mir die umsatzstärksten Kunden dieses Quartals". Der Copilot nutzt Function Calling, um direkt mit der MyRMS-Datenbank und den Services zu interagieren. Für lokale Datenschutz-Anforderungen kann der Copilot auch über Ollama laufen.

---

## I5. Kosten-Tracking und Budget-Management

### I5.1 Token-Tracking pro Request

Jeder KI-API-Aufruf wird in der Datenbank protokolliert mit: Timestamp, Provider, Modell, Input-Tokens, Output-Tokens, Latenz (ms), Task-Typ (email_draft, summary, ocr, etc.), Benutzer-ID und geschätzte Kosten in EUR. Diese Daten speisen das Kosten-Dashboard in den Settings und ermöglichen detaillierte Auswertungen pro Provider, pro Task-Typ und pro Benutzer.

### I5.2 Budget-Alerts und Auto-Limiting

Administratoren können ein monatliches Budget setzen (z.B. €50). Bei Erreichen von 80% wird eine Warnung angezeigt (Toast + E-Mail an Admin). Bei 100% werden KI-Funktionen automatisch deaktiviert oder auf den günstigsten Provider (Ollama/Mistral Small) umgeleitet – konfigurierbar pro Instanz. Ein Kosten-Rechner in den Settings zeigt eine Prognose basierend auf dem bisherigen Verbrauch: „Bei aktuellem Verbrauch werden Sie ca. €45 diesen Monat ausgeben."

### I5.3 Reporting-Widget

Im Admin-Dashboard wird ein KI-Kosten-Widget angezeigt:

```
┌─ KI-Nutzung & Kosten (März 2026) ───────────────────┐
│                                                       │
│  Gesamtkosten: €31.40 / €50.00 Budget                │
│  ████████████░░░░░░░░ 63%                             │
│                                                       │
│  Aufrufe gesamt: 1,247                                │
│  Tokens gesamt: 2.3M (Input: 1.8M, Output: 0.5M)    │
│  Ø Antwortzeit: 340ms                                 │
│                                                       │
│  Nach Provider:          Nach Task:                   │
│  Claude: €24.50 (78%)    E-Mails: €8.20 (26%)       │
│  OpenAI: €5.20 (17%)    Berichte: €12.40 (39%)      │
│  Gemini: €1.70 (5%)     OCR: €3.80 (12%)            │
│                          Chat: €7.00 (22%)            │
│                                                       │
│  [Detaillierter Bericht] [Export CSV]                 │
└───────────────────────────────────────────────────────┘
```

---

## I6. Datenschutz, Anonymisierung und DSGVO-Konformität

### I6.1 Pflicht-Anonymisierung: Kundendaten werden IMMER anonymisiert

**Grundprinzip:** Personenbezogene Daten – insbesondere Kundendaten – werden **grundsätzlich anonymisiert**, bevor sie an einen KI-Provider gesendet werden. Das ist keine optionale Einstellung, sondern ein fester Bestandteil der Architektur. Jeder KI-Request durchläuft automatisch die **Anonymisierungs-Pipeline**, die alle personenbezogenen Daten erkennt, durch Platzhalter ersetzt, den anonymisierten Text an die KI sendet, und nach Erhalt der Antwort die Platzhalter wieder durch die echten Daten ersetzt (De-Anonymisierung). Der Benutzer sieht immer die vollständige Ausgabe mit echten Daten – die Anonymisierung geschieht unsichtbar im Hintergrund.

### I6.2 Anonymisierungs-Pipeline: Technischer Ablauf

Die Pipeline arbeitet in drei Phasen, die bei jedem KI-Request automatisch und transparent durchlaufen werden:

**Phase 1: Erkennung und Ersetzung (Pre-Processing)**

Bevor der Prompt an die KI-API gesendet wird, scannt der `AnonymizationService` den gesamten Text und ersetzt alle erkannten personenbezogenen Daten durch nummerierte Platzhalter. Die Erkennung nutzt eine Kombination aus Regex-Patterns (für strukturierte Daten wie IBAN, E-Mail, Telefon), Datenbankabgleich (Kundennamen, Firmennamen, Adressen aus der MyRMS-Datenbank) und Named Entity Recognition (NER) als zusätzliche Sicherheitsschicht.

```
Originaler Prompt (was der Benutzer sieht):
─────────────────────────────────────────────────────────
"Erstelle ein Angebot für Hans Müller von der Firma
EventTech GmbH, Berliner Str. 42, 90513 Zirndorf.
Kontakt: h.mueller@eventtech.de, Tel: 0911-12345678.
IBAN: DE89 3704 0044 0532 0130 00.
Er möchte 12 LED-Scheinwerfer für 3 Tage mieten."

                    │
                    ▼  AnonymizationService::anonymize()

Anonymisierter Prompt (was an die KI gesendet wird):
─────────────────────────────────────────────────────────
"Erstelle ein Angebot für [PERSON_1] von der Firma
[FIRMA_1], [ADRESSE_1].
Kontakt: [EMAIL_1], Tel: [TELEFON_1].
IBAN: [IBAN_1].
Er möchte 12 LED-Scheinwerfer für 3 Tage mieten."
```

Dabei wird eine **Ersetzungs-Map** im Speicher gehalten (nie in die Datenbank geschrieben, nie an die KI gesendet):

```
Ersetzungs-Map (nur im RAM, pro Request):
┌──────────────┬──────────────────────────────────┐
│ Platzhalter  │ Originalwert                     │
├──────────────┼──────────────────────────────────┤
│ [PERSON_1]   │ Hans Müller                      │
│ [FIRMA_1]    │ EventTech GmbH                   │
│ [ADRESSE_1]  │ Berliner Str. 42, 90513 Zirndorf │
│ [EMAIL_1]    │ h.mueller@eventtech.de           │
│ [TELEFON_1]  │ 0911-12345678                    │
│ [IBAN_1]     │ DE89 3704 0044 0532 0130 00      │
└──────────────┴──────────────────────────────────┘
```

**Phase 2: KI-Verarbeitung (unveränderter Ablauf)**

Der anonymisierte Prompt wird ganz normal an den konfigurierten KI-Provider gesendet. Die KI sieht nur Platzhalter – sie hat keinerlei Zugang zu den echten personenbezogenen Daten. Die KI-Antwort enthält ebenfalls nur die Platzhalter:

```
KI-Antwort (anonymisiert):
"Sehr geehrter [PERSON_1],

im Auftrag von [FIRMA_1] freuen wir uns, Ihnen
folgendes Angebot zu unterbreiten:

12× ETC Source Four LED S3 – 3 Tage – €45/Tag = €1.620
zzgl. 19% MwSt: €307,80
Gesamtbetrag: €1.927,80 brutto

Lieferadresse: [ADRESSE_1]
Zahlung per SEPA auf [IBAN_1]..."
```

**Phase 3: De-Anonymisierung (Post-Processing)**

Die KI-Antwort durchläuft die umgekehrte Ersetzung – alle Platzhalter werden durch die echten Daten aus der Ersetzungs-Map zurückersetzt. Das Ergebnis ist eine vollständige, personalisierte Ausgabe, die der Benutzer direkt verwenden kann:

```
Finale Ausgabe (was der Benutzer sieht):
"Sehr geehrter Hans Müller,

im Auftrag von EventTech GmbH freuen wir uns, Ihnen
folgendes Angebot zu unterbreiten:

12× ETC Source Four LED S3 – 3 Tage – €45/Tag = €1.620
zzgl. 19% MwSt: €307,80
Gesamtbetrag: €1.927,80 brutto

Lieferadresse: Berliner Str. 42, 90513 Zirndorf
Zahlung per SEPA auf DE89 3704 0044 0532 0130 00..."
```

### I6.3 Erkennungsregeln: Was wird anonymisiert?

Der AnonymizationService erkennt und anonymisiert folgende Datentypen automatisch:

```
┌─────────────────────┬──────────────┬──────────────────────────────────┐
│ Datentyp            │ Platzhalter  │ Erkennungsmethode                │
├─────────────────────┼──────────────┼──────────────────────────────────┤
│ Personennamen       │ [PERSON_N]   │ DB-Abgleich + NER               │
│ Firmennamen         │ [FIRMA_N]    │ DB-Abgleich (clients.name)      │
│ Straßenadressen     │ [ADRESSE_N]  │ DB-Abgleich + Regex (Str./Weg)  │
│ PLZ + Ort           │ [ORT_N]      │ Regex (\d{5}\s+\w+)            │
│ E-Mail-Adressen     │ [EMAIL_N]    │ Regex (RFC 5322 Pattern)        │
│ Telefonnummern      │ [TELEFON_N]  │ Regex (DE/AT/CH Formate)        │
│ IBAN                │ [IBAN_N]     │ Regex (DE\d{2}\s?\d{4}...)      │
│ BIC/SWIFT           │ [BIC_N]      │ Regex (8-11 alphanumerisch)     │
│ Steuernummer        │ [STEUER_N]   │ Regex (DE\d{9}, \d{2,3}/\d{3}) │
│ USt-IdNr.           │ [USTID_N]    │ Regex (DE\d{9})                 │
│ Geburtsdaten        │ [GEBDAT_N]   │ Regex + Kontextanalyse          │
│ Personalausweis-Nr. │ [AUSWEIS_N]  │ Regex (DE-Ausweis-Format)       │
│ KFZ-Kennzeichen     │ [KFZ_N]      │ Regex (DE-Kennzeichen)          │
│ Kontonummern        │ [KONTO_N]    │ Regex (5-10 Ziffern im Kontext) │
│ IP-Adressen         │ [IP_N]       │ Regex (IPv4/IPv6)               │
│ Seriennummern       │ [SERIE_N]    │ Kontextbasiert (nach "SN:", etc)│
└─────────────────────┴──────────────┴──────────────────────────────────┘
```

**Datenbankabgleich** ist die zuverlässigste Methode: Der Service gleicht den Prompt-Text gegen die Kundendatenbank (clients, contacts), Mitarbeiterdatenbank (users) und Projektdaten (projects mit Adressen) ab. Jeder gefundene Name, jede Adresse, jede E-Mail wird ersetzt. Das ist besonders effektiv, weil MyRMS alle relevanten Personen und Firmen bereits kennt.

**Regex-Patterns** fangen strukturierte Daten ab, die ein klares Format haben (IBAN, E-Mail, Telefon). Diese funktionieren unabhängig davon, ob die Person in der Datenbank bekannt ist.

**Named Entity Recognition (NER)** als dritte Schicht erkennt Personennamen und Firmennamen auch dann, wenn sie nicht in der Datenbank stehen (z.B. bei neuen Kontakten, die noch nicht erfasst wurden). Dafür wird entweder eine leichtgewichtige PHP-NER-Library genutzt oder – bei aktiviertem Ollama – ein lokales NER-Modell, das die Erkennung komplett offline durchführt.

### I6.4 Anonymisierungs-Stufen (konfigurierbar)

Der Administrator kann in den Settings die Anonymisierungs-Intensität anpassen:

```
┌─ Einstellungen → KI → Datenschutz & Anonymisierung ─────┐
│                                                           │
│  ─── Anonymisierungs-Modus ──────────────────────────    │
│                                                           │
│  ◉ Strikt (Empfohlen)                                    │
│    ALLE personenbezogenen Daten werden anonymisiert.      │
│    Inklusive: Namen, Adressen, E-Mails, Telefon, IBAN,  │
│    Steuernummern, IPs, Geburtsdaten.                     │
│    → Kein AVV mit Provider erforderlich                   │
│                                                           │
│  ○ Standard                                               │
│    Finanzdaten (IBAN, Steuernr., Konten) werden           │
│    anonymisiert. Namen und Kontaktdaten werden            │
│    durchgelassen wenn AVV vorliegt.                       │
│    → AVV mit Provider erforderlich                        │
│                                                           │
│  ○ Minimal (nur mit Ollama empfohlen)                     │
│    Nur IBAN, Kreditkarten und Steuer-IDs werden           │
│    anonymisiert. Alle anderen Daten werden durchgelassen. │
│    → Nur für lokale Provider ohne Cloud-Übertragung       │
│                                                           │
│  ○ Aus (nur Ollama/lokale Provider)                       │
│    Keine Anonymisierung. Alle Daten gehen unverändert     │
│    an den Provider. NUR bei rein lokaler Verarbeitung     │
│    (Ollama) verfügbar – bei Cloud-Providern gesperrt.    │
│    ⚠️ Cloud-Provider bei Modus "Aus" automatisch blockiert│
│                                                           │
│  ─── Erweiterte Einstellungen ───────────────────────    │
│                                                           │
│  ☑ Projektnamen anonymisieren (intern/vertraulich)       │
│  ☐ Asset-Seriennummern anonymisieren                     │
│  ☑ Mitarbeiter-Namen anonymisieren                       │
│  ☑ Vertragsnummern anonymisieren                         │
│  ☐ Rechnungsnummern anonymisieren                        │
│                                                           │
│  ─── Eigene Anonymisierungs-Regeln ──────────────────    │
│                                                           │
│  [+ Neue Regel hinzufügen]                               │
│  ┌────────────────────────┬────────────┬────────────────┐│
│  │ Pattern/Begriff        │ Platzhalter│ Aktion         ││
│  ├────────────────────────┼────────────┼────────────────┤│
│  │ "Projekt Geheim-X"     │ [PROJEKT_1]│ [✏️] [🗑]     ││
│  │ "intern: *"            │ [INTERN_N] │ [✏️] [🗑]     ││
│  └────────────────────────┴────────────┴────────────────┘│
│                                                           │
│  ─── Provider-spezifische Regeln ────────────────────    │
│                                                           │
│  Pro Provider überschreiben:                              │
│  • OpenAI:    [Strikt ▼]  AVV: [✅ hochgeladen]         │
│  • Claude:    [Strikt ▼]  AVV: [✅ hochgeladen]         │
│  • Gemini:    [Strikt ▼]  AVV: [✅ Google Cloud DPA]    │
│  • Mistral:   [Standard▼] AVV: [✅ EU-Anbieter]         │
│  • Ollama:    [Aus     ▼] AVV: [─  Lokal, kein AVV nötig]│
│                                                           │
│  [Änderungen speichern]                                   │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### I6.5 Technische Implementierung: AnonymizationService

```php
<?php
namespace App\Services\AI;

class AnonymizationService
{
    private array $replacementMap = [];
    private array $counters = [];

    /**
     * Anonymisiert alle PII im Text und gibt den anonymisierten Text zurück.
     * Die Ersetzungs-Map wird intern gehalten für spätere De-Anonymisierung.
     */
    public function anonymize(string $text, string $mode = 'strict'): string
    {
        $this->replacementMap = [];
        $this->counters = [];

        // 1. DB-Abgleich: Bekannte Kunden, Kontakte, Mitarbeiter
        $text = $this->replaceKnownEntities($text);

        // 2. Regex: Strukturierte Daten (IBAN, E-Mail, Telefon, etc.)
        $text = $this->replaceByRegex($text, $mode);

        // 3. NER: Unbekannte Personennamen (Fallback)
        if ($mode === 'strict') {
            $text = $this->replaceByNER($text);
        }

        return $text;
    }

    /**
     * Ersetzt Platzhalter durch Originaldaten in der KI-Antwort.
     */
    public function deAnonymize(string $text): string
    {
        foreach ($this->replacementMap as $placeholder => $original) {
            $text = str_replace($placeholder, $original, $text);
        }
        return $text;
    }

    /**
     * Gibt die Ersetzungs-Map zurück (für Audit-Logging).
     * ACHTUNG: Nur Platzhalter-Keys loggen, nie die Originalwerte!
     */
    public function getRedactedAuditLog(): array
    {
        return array_map(
            fn($orig) => '[REDACTED:' . mb_strlen($orig) . ' chars]',
            $this->replacementMap
        );
    }

    private function addReplacement(string $type, string $original): string
    {
        $counter = ($this->counters[$type] ?? 0) + 1;
        $this->counters[$type] = $counter;
        $placeholder = "[{$type}_{$counter}]";
        $this->replacementMap[$placeholder] = $original;
        return $placeholder;
    }

    private function replaceKnownEntities(string $text): string
    {
        // Alle Kundennamen aus DB laden (gecacht)
        $clients = $this->db->get('clients', null, ['clients_name', 'clients_email']);
        foreach ($clients as $client) {
            if (str_contains($text, $client['clients_name'])) {
                $placeholder = $this->addReplacement('FIRMA', $client['clients_name']);
                $text = str_replace($client['clients_name'], $placeholder, $text);
            }
        }

        // Kontaktpersonen
        $contacts = $this->db->get('clientContacts', null,
            ['contacts_firstName', 'contacts_lastName', 'contacts_email', 'contacts_phone']);
        foreach ($contacts as $contact) {
            $fullName = $contact['contacts_firstName'] . ' ' . $contact['contacts_lastName'];
            if (str_contains($text, $fullName)) {
                $placeholder = $this->addReplacement('PERSON', $fullName);
                $text = str_replace($fullName, $placeholder, $text);
            }
        }

        return $text;
    }

    private function replaceByRegex(string $text, string $mode): string
    {
        $patterns = [
            // IBAN (immer, in allen Modi)
            '/\b[A-Z]{2}\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{0,2}\b/'
                => 'IBAN',
            // E-Mail
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/'
                => 'EMAIL',
            // Telefon (DE-Formate)
            '/\b(?:\+49|0049|0)\s?[\d\s\-\/]{6,14}\b/'
                => 'TELEFON',
            // USt-IdNr
            '/\bDE\s?\d{9}\b/'
                => 'USTID',
            // Steuernummer
            '/\b\d{2,3}\/\d{3}\/\d{4,5}\b/'
                => 'STEUER',
        ];

        if ($mode === 'strict') {
            // Zusätzlich in Strict-Modus
            $patterns['/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/'] = 'IP';
            $patterns['/\b\d{2}\.\d{2}\.\d{4}\b/'] = 'DATUM'; // Geburtsdaten
        }

        foreach ($patterns as $pattern => $type) {
            $text = preg_replace_callback($pattern, function($match) use ($type) {
                return $this->addReplacement($type, $match[0]);
            }, $text);
        }

        return $text;
    }
}
```

### I6.6 Anonymisierung im KI-Request-Flow (Integration)

Die Anonymisierung ist nahtlos in den bestehenden KI-Flow integriert. Der `AiProviderRegistry`-Aufruf wird durch eine Wrapper-Methode ergänzt, die automatisch anonymisiert und de-anonymisiert:

```php
class AiRequestHandler
{
    public function processRequest(
        string $taskType,
        string $prompt,
        array $context = []
    ): string {
        // 1. Anonymisierungs-Modus laden
        $mode = $this->config->get('ai.anonymization_mode', 'strict');
        $provider = $this->getProviderForTask($taskType);

        // 2. Bei lokalem Provider (Ollama) und Modus "Aus": Skip
        if ($mode === 'off' && $provider->isLocal()) {
            return $provider->chatCompletion($prompt);
        }

        // 3. Cloud-Provider: IMMER anonymisieren (auch bei Modus "Standard")
        if (!$provider->isLocal()) {
            $mode = max($mode, 'standard'); // Mindestens Standard bei Cloud
        }

        // 4. Anonymisieren
        $anonymizer = new AnonymizationService($this->db);
        $anonPrompt = $anonymizer->anonymize($prompt, $mode);

        // 5. An KI senden
        $anonResponse = $provider->chatCompletion($anonPrompt);

        // 6. De-Anonymisieren
        $response = $anonymizer->deAnonymize($anonResponse);

        // 7. Audit-Log (nur Platzhalter, nie Originaldaten)
        $this->auditLog->log('ai_request', [
            'task' => $taskType,
            'provider' => $provider->getProviderName(),
            'anonymization_mode' => $mode,
            'replacements' => $anonymizer->getRedactedAuditLog(),
            'tokens_in' => $provider->getLastTokenCount('input'),
            'tokens_out' => $provider->getLastTokenCount('output'),
        ]);

        return $response;
    }
}
```

### I6.7 Sicherheitsgarantien

Die Anonymisierungs-Pipeline bietet mehrere Sicherheitsgarantien:

**Kein Bypass möglich bei Cloud-Providern:** Wenn ein Cloud-Provider konfiguriert ist, wird die Anonymisierung erzwungen – auch wenn der Admin den Modus auf „Aus" stellt, wird bei Cloud-Providern automatisch mindestens „Standard" aktiviert. Der Modus „Aus" ist hardcoded nur für Provider verfügbar, deren `isLocal()`-Methode `true` zurückgibt.

**Ersetzungs-Map nur im RAM:** Die Zuordnung Platzhalter → Originaldaten wird ausschließlich im Arbeitsspeicher gehalten und nach Abschluss des Requests sofort verworfen. Sie wird nie in die Datenbank geschrieben, nie geloggt und nie an externe Services übermittelt. Im Audit-Log wird nur protokolliert, dass z.B. „[PERSON_1]" ersetzt wurde und wie viele Zeichen der Originalwert hatte, aber nie der Wert selbst.

**Double-Check bei sensiblen Feldern:** IBAN, Steuernummern und Kreditkartennummern werden zusätzlich durch einen zweiten Regex-Pass validiert, um sicherzustellen, dass sie nicht durch eine ungewöhnliche Formatierung durchgerutscht sind.

**Kontextuell intelligente Ersetzung:** Der Service erkennt, ob ein Name als Anrede, als Firmenname oder als Produktbezeichnung verwendet wird. „Müller" als Kundenname wird anonymisiert, aber „Müller Kransysteme" als bekannte Herstellerbezeichnung wird als Asset-Marke erkannt und nicht anonymisiert – vorausgesetzt, der Hersteller ist im System als Manufacturer registriert.

### I6.8 Auftragsverarbeitungsvertrag (AVV) Management

Für jeden Cloud-Provider zeigt die UI an, ob ein AVV (Data Processing Agreement) vorliegt. Der Admin kann das AVV-Dokument hochladen und mit dem Provider verknüpfen. Links zu den Standard-AVVs der großen Provider sind hinterlegt:

- **Anthropic (Claude):** DPA abrufbar unter console.anthropic.com → Legal → Data Processing Addendum
- **OpenAI:** DPA unter platform.openai.com → Settings → Data Processing Addendum
- **Google (Gemini):** Cloud DPA automatisch Teil der Google Cloud Terms of Service
- **Mistral:** EU-basiert, DSGVO-konform by default, DPA unter console.mistral.ai → Legal

Wenn für einen Provider kein AVV hinterlegt ist und der Anonymisierungs-Modus nicht auf „Strikt" steht, zeigt die UI eine Warnung: „⚠️ Kein AVV für OpenAI hinterlegt. Anonymisierung wird auf ‚Strikt' erzwungen." Der Provider wird automatisch auf Strikt-Modus geschaltet, bis ein AVV hochgeladen wird.

### I6.9 Audit-Trail für KI-Entscheidungen

Jede KI-Aktion wird im Audit-Log protokolliert mit folgenden Informationen: Welcher Benutzer hat die Anfrage ausgelöst, an welchen Provider und welches Modell wurde sie gesendet, welcher Anonymisierungs-Modus war aktiv, wie viele Felder wurden anonymisiert (z.B. „3 Personen, 2 E-Mails, 1 IBAN ersetzt"), was war die Antwort (anonymisiert im Log), und wurde die Antwort vom Benutzer akzeptiert oder abgelehnt.

Der Audit-Trail ist einsehbar unter Admin → System → Audit-Log → Filter: „KI-Aktionen". Jeder Eintrag zeigt auf einen Blick: Zeitpunkt, Benutzer, Task-Typ, Provider, Anonymisierungs-Status und Ergebnis. Bei DSGVO-Anfragen von Betroffenen (Art. 15 Auskunftsrecht) kann der Admin filtern: „Zeige alle KI-Verarbeitungen, die Daten von Kunde X betreffen" – das System findet alle Requests, in denen Daten dieses Kunden anonymisiert wurden, und listet auf, was wann an welchen Provider gesendet wurde (nur die anonymisierte Version, nie die Originaldaten).

### I6.10 Transparenz für Endbenutzer

In der UI wird der Anonymisierungsstatus für den Benutzer transparent angezeigt. Neben jeder KI-Ausgabe erscheint ein kleines Schloss-Icon mit Tooltip:

```
🔒 Datenschutz: 5 personenbezogene Daten wurden vor der
KI-Verarbeitung anonymisiert (2 Namen, 1 Adresse,
1 E-Mail, 1 IBAN). Kein Provider hat Zugang zu
Ihren Kundendaten erhalten.
```

Dies schafft Vertrauen beim Benutzer und demonstriert die DSGVO-Konformität des Systems direkt in der täglichen Arbeit.

---

## I7. Erweiterbarkeit: Eigene Provider hinzufügen

### I7.1 Custom Provider Anleitung

MyRMS ermöglicht es Entwicklern, eigene KI-Provider zu integrieren. Ein neuer Provider benötigt lediglich eine PHP-Klasse, die das `LlmProviderInterface` implementiert, und einen Eintrag in der Provider-Registry. Die Dokumentation in den Developer-Docs enthält ein vollständiges Schritt-für-Schritt-Tutorial:

1. Erstelle eine neue PHP-Klasse in `src/services/AI/Providers/` (z.B. `CustomApiAdapter.php`)
2. Implementiere das `LlmProviderInterface` mit allen erforderlichen Methoden
3. Registriere den Provider in `config/ai_providers.php`
4. Der neue Provider erscheint automatisch in der Settings-UI unter dem Tab „+ Mehr"
5. Teste mit dem integrierten Test-Button in den Settings

### I7.2 OpenAI-kompatible APIs

Viele KI-Services bieten eine OpenAI-kompatible API an (z.B. Azure OpenAI, Together AI, Fireworks AI, Groq, Perplexity). Für diese gibt es einen generischen „OpenAI Compatible"-Adapter in der UI, bei dem nur Base-URL und API-Key eingegeben werden müssen. Dies deckt bereits einen Großteil aller verfügbaren KI-Services ab, ohne dass Custom-Code geschrieben werden muss.

```
┌─ OpenAI-kompatible API hinzufügen ───────────────────┐
│                                                       │
│  Name:      [Groq Cloud                  ]           │
│  Base-URL:  [https://api.groq.com/openai/v1]        │
│  API-Key:   [gsk_••••••••••••            ]           │
│  Modell:    [llama-3.1-70b-versatile     ]           │
│                                                       │
│  [Verbindung testen]  [Speichern]                    │
└───────────────────────────────────────────────────────┘
```

---

## I8. Preisvergleich und Empfehlungen

### I8.1 Übersicht Kosten pro 1M Tokens (Stand März 2026)

```
┌──────────────────┬────────────┬─────────────┬──────────────────────────┐
│ Provider/Modell  │ Input/MTok │ Output/MTok │ Empfehlung               │
├──────────────────┼────────────┼─────────────┼──────────────────────────┤
│ Mistral Small    │ $0.20      │ $0.60       │ Budget: Bulk-Tasks       │
│ Gemini Flash     │ $0.10      │ $0.40       │ Budget: Standard-Tasks   │
│ Claude Haiku     │ $0.25      │ $1.25       │ Standard: E-Mails, Chat  │
│ GPT-4o-mini      │ $0.15      │ $0.60       │ Standard: Allrounder     │
│ Claude Sonnet    │ $3.00      │ $15.00      │ Qualität: Berichte       │
│ GPT-4o           │ $2.50      │ $10.00      │ Qualität: Analysen       │
│ Gemini Pro       │ $1.25      │ $5.00       │ Qualität + Vision        │
│ Claude Opus      │ $15.00     │ $75.00      │ Premium: Verträge        │
│ OpenAI o1        │ $15.00     │ $60.00      │ Premium: Reasoning       │
│ Ollama (Lokal)   │ €0.00      │ €0.00       │ Kostenlos: Datenschutz   │
└──────────────────┴────────────┴─────────────┴──────────────────────────┘
```

### I8.2 Kostenbeispiel für eine typische MyRMS-Instanz

Eine mittelgroße Verleih-Firma mit 5 Benutzern, 500 Assets und 50 Projekten/Monat verbraucht typischerweise ca. 2-3 Millionen Tokens pro Monat für KI-Features (E-Mail-Drafts, Zusammenfassungen, Chat, gelegentliche OCR). Mit der empfohlenen Mischung aus Haiku (Standard) + Sonnet (Qualität) + Gemini Flash (OCR) ergibt das geschätzte Kosten von **€15-30 pro Monat**. Mit Ollama als Primary für Standard-Tasks und Cloud nur für Premium-Aufgaben sinken die Kosten auf **€5-10 pro Monat**.

---

## I9. KI-gestützte Asset-Erstellung (Smart Asset Creator)

### I9.1 Überblick und Nutzen

Die Erfassung neuer Equipment-Assets ist einer der zeitaufwändigsten Prozesse im Verleih-Alltag. Ein typisches Asset hat 20-40 Felder (Name, Hersteller, Modell, Kategorie, Gewicht, Maße, Leistungsaufnahme, Anschlusswerte, Preis, Ersatzwert, Beschreibung, technische Daten, Zubehör, Wartungsintervalle, etc.), die manuell recherchiert und eingetragen werden müssen. Bei der Ersteinrichtung eines Systems mit 200-500 Assets bedeutet das mehrere Tage reiner Dateneingabe.

Der **Smart Asset Creator** löst dieses Problem grundlegend: Der Benutzer gibt lediglich den **Herstellernamen und die Modellbezeichnung** ein (z.B. „ETC Source Four LED Series 3" oder „Sennheiser EW-DX SK"), und die KI recherchiert automatisch alle verfügbaren technischen Daten, füllt die Formularfelder aus, generiert eine professionelle Beschreibung und schlägt sogar eine passende Kategorie, einen Mietpreis und ein Produktbild vor. Der Benutzer überprüft die vorausgefüllten Daten, korrigiert bei Bedarf und bestätigt – fertig.

Dieses Feature nutzt eine Kombination aus drei Datenquellen: **Produktdatenbanken** (strukturierte technische Daten), **KI-gestützte Webrecherche** (Herstellerwebsites, Datenblätter) und **LLM-Verarbeitung** (Extraktion, Strukturierung, Beschreibungsgenerierung). Durch diesen mehrstufigen Ansatz erreicht das System eine Datenqualität von 90-95% bei den meisten gängigen Veranstaltungstechnik-, Bau- und Industriegeräten.

### I9.2 Datenquellen und Lookup-Strategie

Die Datenermittlung folgt einer priorisierten Kaskade – das System versucht zunächst die zuverlässigsten Quellen und fällt bei Bedarf auf weniger strukturierte zurück:

**Stufe 1: Produktdatenbanken (Icecat, Open Product Data)**

Icecat ist der weltweit größte offene Produktdaten-Katalog mit über 26 Millionen Datenblättern von mehr als 28.000 Marken. Die Icecat-API liefert strukturierte technische Spezifikationen im JSON-Format, inklusive Produktbilder, Marketing-Texte, Feature-Listen und detaillierter technischer Parameter. MyRMS fragt die Icecat-API mit EAN/GTIN-Code, Herstellername + Modellnummer oder freier Textsuche ab. Die zurückgelieferten Daten werden automatisch in die MyRMS-Felder gemappt (z.B. Icecat „Weight" → MyRMS „Gewicht in kg", Icecat „Power consumption" → MyRMS „Leistungsaufnahme in Watt").

Für den Fall, dass kein Icecat-Eintrag existiert, wird parallel die **Open Product Data** Initiative abgefragt, eine Community-getriebene Datenbank mit Fokus auf europäische Produkte und EAN-Codes. Zusätzlich können branchenspezifische Datenbanken angebunden werden, z.B. für Veranstaltungstechnik die Herstellerkataloge von ETC, Robe, Clay Paky, d&b audiotechnik, Sennheiser, Shure etc., die häufig maschinenlesbare Produktdaten (CSV, XML) für Händler bereitstellen.

**Stufe 2: KI-gestützte Webrecherche (LLM + Web Search)**

Wenn Stufe 1 keine ausreichenden Daten liefert, nutzt das System die KI mit Webzugang (Function Calling mit Web-Search-Tool): Die KI sucht automatisch nach der Herstellerwebsite, findet die Produktseite und extrahiert die technischen Daten. Dieser Ansatz nutzt die Fähigkeit moderner LLMs, unstrukturierte Webseiten zu verstehen und relevante Informationen in strukturierte Felder umzuwandeln – mit einer Genauigkeit von 95-98% auf gut strukturierten Herstellerseiten.

Konkret funktioniert das so: Die KI erhält den Prompt „Finde alle technischen Spezifikationen für [Hersteller] [Modell]. Extrahiere: Gewicht, Maße (L×B×H), Leistungsaufnahme, Anschlüsse, Schutzklasse, Besonderheiten. Formatiere als JSON." und durchsucht die Herstellerwebsite, Thomann.de (für Audio/Licht), Amazon, idealo oder spezialisierte Fachhändler. Die extrahierten Daten werden als Vorschlag angezeigt, nie direkt ohne Bestätigung übernommen.

**Stufe 3: LLM-Wissensbasierte Schätzung (Fallback)**

Für sehr spezielle oder ältere Geräte, bei denen weder Datenbank noch Webrecherche Ergebnisse liefern, nutzt das System das trainierte Wissen des LLM als Fallback. Wenn das Modell beispielsweise „ETC Source Four" kennt (was bei allen großen Modellen der Fall ist), kann es die ungefähren technischen Daten aus seinem Training wiedergeben. Diese Daten werden deutlich als „KI-Schätzung (bitte verifizieren)" markiert mit einem gelben Warn-Badge, um den Benutzer auf die geringere Zuverlässigkeit hinzuweisen.

### I9.3 Benutzeroberfläche: Smart Asset Creator Wizard

Der Smart Asset Creator wird als mehrstufiger Wizard implementiert, der den Benutzer durch den Prozess führt:

**Schritt 1: Eingabe (Minimal-Input)**

```
┌─ Neues Asset anlegen (Smart Mode) ───────────────────────┐
│                                                           │
│  Wie möchten Sie das Asset erfassen?                      │
│                                                           │
│  [🤖 Smart Mode]  [📝 Manuell]  [📷 Foto/Scan]          │
│                                                           │
│  ─── Smart Mode: KI-gestützte Erfassung ───               │
│                                                           │
│  Hersteller:  [ETC                          ] 🔍          │
│               └─ Vorschläge: ETC, Elation, Eurolite...    │
│                                                           │
│  Modell:      [Source Four LED Series 3     ] 🔍          │
│               └─ Vorschläge: Source Four LED S3,           │
│                  Source Four LED Lustr 3, ...              │
│                                                           │
│  Optional:                                                │
│  EAN/GTIN:    [                             ]             │
│  Seriennr.:   [                             ]             │
│                                                           │
│  [🔎 Technische Daten suchen]                             │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

Das Hersteller-Feld bietet Autocomplete aus einer gepflegten Herstellerliste (initial ~500 Hersteller aus der Veranstaltungstechnik, Bau, Industrie). Bei Eingabe eines unbekannten Herstellers wird dieser automatisch der Liste hinzugefügt. Das Modell-Feld bietet ebenfalls Autocomplete, basierend auf bereits im System vorhandenen Assets desselben Herstellers und auf Icecat-Daten.

**Schritt 2: KI-Recherche (Ladeanimation mit Live-Status)**

```
┌─ Technische Daten werden gesucht... ─────────────────────┐
│                                                           │
│  🔄 ETC Source Four LED Series 3                          │
│                                                           │
│  ✅ Icecat-Datenbank durchsucht (3 Treffer)               │
│  ✅ Herstellerwebsite gefunden (etcconnect.com)           │
│  🔄 Technische Daten werden extrahiert...                 │
│  ⏳ Produktbild wird geladen...                           │
│  ⏳ Preisvergleich wird durchgeführt...                   │
│                                                           │
│  Geschätzte Dauer: ~5-10 Sekunden                         │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

**Schritt 3: Ergebnis-Review (Vorausgefülltes Formular)**

```
┌─ KI-Ergebnis: ETC Source Four LED Series 3 ──────────────┐
│                                                           │
│  ┌──────────┐  ETC Source Four LED Series 3               │
│  │  [BILD]  │  Profilscheinwerfer / LED Moving Light      │
│  │          │  ★ Datenqualität: 94% (Icecat + Hersteller) │
│  └──────────┘                                             │
│                                                           │
│  ─── Stammdaten ──────────────────────────────────────    │
│  Name:          [ETC Source Four LED S3     ] ✅ Icecat   │
│  Hersteller:    [ETC                        ] ✅ Icecat   │
│  Kategorie:     [Beleuchtung > Profilscheinwerfer ▼] 🤖  │
│  Unterkategorie:[LED-Scheinwerfer           ▼] 🤖        │
│                                                           │
│  ─── Technische Daten ────────────────────────────────    │
│  Gewicht:       [8.2 kg                     ] ✅ Icecat   │
│  Maße (L×B×H):  [590 × 267 × 406 mm        ] ✅ Herstell.│
│  Leistung:      [170 W                      ] ✅ Herstell.│
│  Lichtquelle:   [LED Array, RGBL            ] ✅ Herstell.│
│  Farbtemperatur: [2700K - 6500K             ] ✅ Herstell.│
│  Lichtstrom:    [9800 lm                    ] ✅ Herstell.│
│  Abstrahlwinkel:[5° - 50° (Zoombereich)     ] ✅ Herstell.│
│  Schutzklasse:  [IP20                       ] ✅ Icecat   │
│  Spannung:      [100-240V, 50/60Hz          ] ✅ Herstell.│
│  DMX-Kanäle:    [7 / 11 / 14 / 18          ] 🌐 Web     │
│  Anschluss:     [PowerCON TRUE1 In/Out      ] 🌐 Web     │
│                                                           │
│  ─── Preise & Werte ──────────────────────────────────    │
│  Neupreis (UVP): [€3.890,00                 ] 🌐 Web     │
│  Ersatzwert:     [€3.500,00                 ] 🤖 KI-Vorschl│
│  Mietpreis/Tag:  [€45,00                    ] 🤖 KI-Vorschl│
│  └─ Berechnung: 1.15% vom Neupreis/Tag (Branchenüblich)  │
│                                                           │
│  ─── Beschreibung ────────────────────────────────────    │
│  ┌────────────────────────────────────────────────────┐   │
│  │ Der ETC Source Four LED Series 3 ist ein hoch-     │   │
│  │ wertiger LED-Profilscheinwerfer der neuesten       │   │
│  │ Generation. Mit seinem RGBL-LED-Array liefert er   │   │
│  │ 9800 Lumen bei nur 170W Leistungsaufnahme und      │   │
│  │ bietet einen Zoombereich von 5° bis 50°. Die       │   │
│  │ Farbtemperatur ist stufenlos von 2700K bis 6500K   │   │
│  │ einstellbar. Ideal für Theater, Veranstaltungen    │   │
│  │ und Festinstallationen.                    🤖 KI   │   │
│  └────────────────────────────────────────────────────┘   │
│                                                           │
│  ─── Zubehör-Vorschläge ─────────────────────────────    │
│  🤖 KI schlägt vor:                                      │
│  ☑ Sicherungsseil (bereits im System: #AS-1042)          │
│  ☑ DMX-Kabel 5m (bereits im System: #KA-0123)           │
│  ☐ Gobo-Set Standard (nicht im System – anlegen?)        │
│  ☐ Farbfilter-Set (nicht im System – anlegen?)           │
│                                                           │
│  Legende: ✅ = verifizierte Quelle  🌐 = Webrecherche    │
│           🤖 = KI-Vorschlag        ⚠️ = bitte prüfen     │
│                                                           │
│  [← Zurück]  [Alle Felder prüfen]  [Asset speichern ✓]  │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

Jedes Feld zeigt über ein kleines Badge an, woher die Daten stammen (✅ Icecat = verifizierte Produktdatenbank, ✅ Hersteller = Herstellerwebsite, 🌐 Web = allgemeine Webrecherche, 🤖 KI = KI-generierter Vorschlag). Felder mit geringerer Konfidenz (< 80%) werden gelb hinterlegt und mit einem ⚠️-Icon markiert. Der Benutzer kann jedes Feld überschreiben – die KI-Daten sind immer nur Vorschläge, nie verbindlich.

### I9.4 Foto-basierte Erfassung (Vision AI)

Neben der textbasierten Suche unterstützt der Smart Asset Creator auch eine **Foto-basierte Erfassung**: Der Benutzer fotografiert das Gerät (oder das Typenschild), und die Vision-KI identifiziert Hersteller und Modell automatisch. Dieser Modus ist besonders nützlich bei der Ersterfassung großer Lagerbestände, wo Geräte physisch vorhanden sind, aber keine digitale Inventarliste existiert.

Der Ablauf funktioniert so: Der Benutzer klickt auf „📷 Foto/Scan" und macht ein Foto mit der Smartphone-Kamera (PWA) oder lädt ein Bild hoch. Die Vision-KI (Gemini Pro Vision, Claude Vision, oder GPT-4o Vision – je nach konfiguriertem Provider) analysiert das Bild und extrahiert sichtbare Informationen: Herstellerlogo, Modellbezeichnung, Typenschilddaten (Seriennummer, Leistungsangaben, CE-Kennzeichnung). Anschließend startet automatisch die Datenrecherche wie in Schritt 2 beschrieben.

Für Typenschilder mit Barcode/QR-Code wird zusätzlich der integrierte Scanner aktiviert, der EAN/GTIN-Codes erkennt und direkt in der Icecat-Datenbank nachschlägt. Die Kombination aus Bilderkennung und Barcode-Scan erreicht bei gängigen Geräten eine Erkennungsrate von über 90%.

### I9.5 Bulk-Import mit KI-Anreicherung

Für die Ersteinrichtung oder den Import großer Gerätemengen bietet der Smart Asset Creator einen **Bulk-Import-Modus**: Der Benutzer lädt eine einfache CSV- oder Excel-Datei hoch, die nur zwei Spalten benötigt (Hersteller + Modell), und die KI reichert automatisch alle Zeilen mit technischen Daten an. Dies läuft als Hintergrund-Job über die Job-Queue (siehe Part C, C4) und der Benutzer wird per Notification benachrichtigt, wenn der Import abgeschlossen ist.

```
┌─ Bulk-Import mit KI-Anreicherung ────────────────────────┐
│                                                           │
│  📁 CSV/Excel hochladen: [Datei wählen...]               │
│                                                           │
│  Vorschau (erste 5 Zeilen):                               │
│  ┌────┬──────────────┬────────────────────────┬──────────┐│
│  │ #  │ Hersteller   │ Modell                 │ Status   ││
│  ├────┼──────────────┼────────────────────────┼──────────┤│
│  │ 1  │ ETC          │ Source Four LED S3      │ ✅ 94%  ││
│  │ 2  │ Sennheiser   │ EW-DX SK               │ ✅ 91%  ││
│  │ 3  │ d&b          │ E8                      │ ✅ 88%  ││
│  │ 4  │ Robe         │ T2 Profile              │ 🔄 ...  ││
│  │ 5  │ Avolites     │ Arena                   │ ⏳      ││
│  └────┴──────────────┴────────────────────────┴──────────┘│
│                                                           │
│  Gesamt: 127 Assets │ Gefunden: 98 │ Manuell: 29         │
│  ████████████████░░░░ 77% abgeschlossen                   │
│                                                           │
│  Geschätzte Kosten: ~€0.85 (Mistral Small für Bulk)      │
│  Geschätzte Dauer: ~3 Minuten                             │
│                                                           │
│  [Import starten]  [Abbrechen]                            │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

Für Bulk-Imports wird standardmäßig Mistral Small verwendet (günstigster Provider), es sei denn der Benutzer konfiguriert einen anderen Provider für Bulk-Operationen im Task-Routing. Bei 100 Assets und durchschnittlich 500 Tokens pro Asset entstehen ca. €0.06 Kosten mit Mistral Small – vernachlässigbar.

### I9.6 KI-Mietpreisvorschlag

Ein besonders wertvolles Sub-Feature des Smart Asset Creators ist der **automatische Mietpreisvorschlag**. Die KI berechnet einen empfohlenen Tagespreis basierend auf mehreren Faktoren:

Der **Neupreis** des Geräts wird aus der Webrecherche ermittelt. Daraus leitet die KI einen Tagesrichtwert ab (Branchenüblich: 1-2% des Neupreises für Veranstaltungstechnik, 0.5-1% für Baugeräte). Zusätzlich werden **interne historische Daten** berücksichtigt: Wenn ähnliche Assets im System bereits vermietet werden, wird deren durchschnittlicher Mietpreis als Referenz herangezogen. Die KI prüft auch **Marktpreise** durch Webrecherche bei Verleiher-Websites (z.B. grover.com, mietpark.com, eventrent.de) für vergleichbare Geräte.

Der Preisvorschlag wird transparent dargestellt: „€45/Tag (Berechnung: Neupreis €3.890 × 1.15% Faktor = €44.74, gerundet. Vergleich: 3 ähnliche Scheinwerfer in Ihrem System Ø €42/Tag, Markt Ø €48/Tag)". Der Benutzer kann den Vorschlag übernehmen, anpassen oder ignorieren.

### I9.7 Auto-Kategorisierung

Die KI schlägt automatisch die passende Kategorie und Unterkategorie vor, basierend auf der Produktbeschreibung und den technischen Daten. Das System lernt aus den Kategorisierungen des Benutzers: Wenn ein Benutzer die KI-Kategorie ändert, wird diese Korrektur als Trainingsignal gespeichert und bei zukünftigen ähnlichen Produkten berücksichtigt. So wird die Kategorisierung über Zeit immer genauer.

Beispiel-Mapping:
- „Source Four LED" → Beleuchtung > Profilscheinwerfer > LED
- „EW-DX SK" → Audio > Funkmikrofon > Taschensender
- „E8" → Audio > Lautsprecher > Line Array Element
- „Arena" → Licht > Steuerpulte > Moving Light Controller

### I9.8 Settings-UI für Smart Asset Creator

```
┌─ Einstellungen → KI → Smart Asset Creator ───────────────┐
│                                                           │
│  ─── Datenquellen ────────────────────────────────────    │
│  ☑ Icecat Produktdatenbank (kostenlos, Open Catalog)      │
│    API-Key: [                              ] (optional)   │
│    └─ Für Full Icecat (alle Marken): Registrierung auf    │
│       icecat.biz → „Sign up as channel partner" (kostenlos)│
│                                                           │
│  ☑ Herstellerwebsites durchsuchen (KI-Webrecherche)       │
│    Provider für Webrecherche: [Standard-Provider ▼]       │
│                                                           │
│  ☑ Preisvergleich-Portale (idealo, Thomann, Amazon)       │
│  ☐ Google Shopping API (API-Key erforderlich)             │
│                                                           │
│  ─── Automatische Felder ─────────────────────────────    │
│  ☑ Technische Daten (Gewicht, Maße, Leistung)            │
│  ☑ Beschreibung generieren                                │
│  ☑ Kategorie vorschlagen                                  │
│  ☑ Mietpreis vorschlagen                                  │
│  ☑ Neupreis/Ersatzwert ermitteln                          │
│  ☑ Produktbild laden                                      │
│  ☑ Zubehör-Vorschläge                                     │
│  ☐ Wartungsintervall vorschlagen                          │
│                                                           │
│  ─── Mietpreis-Kalkulation ──────────────────────────    │
│  Standard-Faktor: [1.15] % vom Neupreis/Tag              │
│  Pro Kategorie überschreiben:                             │
│  • Beleuchtung:    [1.2 ] %                              │
│  • Audio:          [1.0 ] %                              │
│  • Video:          [0.8 ] %                              │
│  • Rigging:        [1.5 ] %                              │
│  [+ Kategorie-Faktor hinzufügen]                         │
│                                                           │
│  ─── Bulk-Import ─────────────────────────────────────    │
│  Provider für Bulk: [Mistral Small (günstigst) ▼]        │
│  Max. gleichzeitige Lookups: [5 ▼]                       │
│  Timeout pro Asset: [30 Sekunden ▼]                       │
│                                                           │
│  [Änderungen speichern]                                   │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### I9.9 Technische Implementierung

**AssetLookupService (PHP)**

Der bestehende `AiAssetLookupService` wird erweitert um einen `SmartAssetLookupService`, der die Kaskade aus Icecat → Webrecherche → LLM-Wissen orchestriert. Der Service gibt ein standardisiertes `AssetDataResult`-Objekt zurück, das für jedes Feld die Quelle und Konfidenz enthält:

```php
class AssetDataResult {
    public string $name;
    public string $manufacturer;
    public string $model;
    public ?string $category;           // KI-Vorschlag
    public ?float  $weight;             // in kg
    public ?string $dimensions;         // L×B×H in mm
    public ?int    $powerConsumption;   // in Watt
    public ?float  $newPrice;           // UVP in EUR
    public ?float  $suggestedRentalPrice; // Tagespreis in EUR
    public ?string $description;        // KI-generiert
    public ?string $imageUrl;           // Produktbild-URL
    public array   $technicalSpecs;     // Key-Value Paare
    public array   $accessories;        // Vorgeschlagenes Zubehör
    public array   $sources;            // Pro Feld: ['field' => 'source', 'confidence' => 0.94]
}
```

**API-Endpunkt:**
```
POST /api/v2/assets/smart-lookup
Body: { "manufacturer": "ETC", "model": "Source Four LED S3", "ean": "" }
Response: AssetDataResult als JSON
```

**Caching:** Lookup-Ergebnisse werden 30 Tage im Redis-Cache gespeichert (Key: `asset_lookup:{manufacturer}:{model}`), um wiederholte API-Aufrufe zu vermeiden. Wenn ein anderer Benutzer oder eine andere Instanz dasselbe Produkt nachschlägt, wird das Cache-Ergebnis innerhalb von Millisekunden zurückgegeben.

---

## I10. KI-Lernsystem: Continuous Learning & Self-Improvement

### I10.1 Überblick und Vision

Das KI-Lernsystem ist das Herzstück einer wirklich intelligenten Rental-Management-Plattform. Statt einer statischen KI, die immer die gleichen Antworten gibt, baut MyRMS ein System auf, das sich **kontinuierlich verbessert** – es lernt aus jeder Benutzerinteraktion, merkt sich Präferenzen, korrigiert eigene Fehler und wird über Zeit immer genauer, schneller und nützlicher. Dieses Konzept folgt dem Prinzip der **Feedback-Driven AI**, bei der Benutzerinteraktionen gesammelt, analysiert und zur Verfeinerung des Systems genutzt werden, sodass sich Genauigkeit, Relevanz und Nutzererlebnis stetig verbessern.

Das Lernsystem arbeitet auf **vier Ebenen**, die sich gegenseitig ergänzen:

1. **Explizites Feedback** – Der Benutzer bewertet KI-Ausgaben aktiv (Daumen hoch/runter, Korrekturen)
2. **Implizites Feedback** – Das System beobachtet, was der Benutzer mit KI-Vorschlägen macht (übernommen, geändert, ignoriert)
3. **Knowledge Base (RAG)** – Unternehmensspezifisches Wissen wird als durchsuchbare Wissensbasis aufgebaut
4. **Few-Shot Learning** – Die besten Beispiele aus der Vergangenheit werden automatisch in Prompts eingebaut

Durch die Kombination dieser vier Ebenen entsteht ein System, das sich nicht nur an einzelne Benutzer anpasst, sondern auch instanzübergreifend lernt – die Erfahrungen aller Benutzer verbessern das System für jeden.

### I10.2 Explizites Feedback-System (Daumen hoch/runter)

Jede KI-generierte Ausgabe in MyRMS – ob E-Mail-Entwurf, Schadensbericht-Zusammenfassung, Asset-Beschreibung, Preisvorschlag oder Chat-Antwort – erhält eine kleine **Feedback-Leiste** direkt unter der Ausgabe:

```
┌─ KI-generierter E-Mail-Entwurf ─────────────────────────┐
│                                                           │
│  Sehr geehrter Herr Müller,                               │
│  vielen Dank für Ihre Anfrage bezüglich der Anmietung     │
│  von 12 LED-Scheinwerfern für Ihr Event am 15. April...   │
│                                                           │
│  ─────────────────────────────────────────────────────    │
│  War diese Antwort hilfreich?                             │
│  [👍 Gut]  [👎 Schlecht]  [✏️ Bearbeitet]  [💬 Feedback] │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

**👍 Gut:** Die Ausgabe wird als positives Beispiel gespeichert. Sie fließt in die Few-Shot-Beispiel-Datenbank ein und wird bei ähnlichen zukünftigen Anfragen als Referenz verwendet. Je mehr positive Bewertungen ein bestimmter Stil oder eine Formulierung erhält, desto wahrscheinlicher wird dieser Stil in Zukunft bevorzugt.

**👎 Schlecht:** Es öffnet sich ein optionales Dropdown mit Gründen: „Zu formal", „Zu lang", „Inhaltlich falsch", „Falscher Ton", „Fehlende Details", „Sonstiges (Freitext)". Diese negativen Beispiele werden als **Anti-Patterns** markiert und aktiv in zukünftigen Prompts vermieden. Wenn z.B. mehrere Benutzer „Zu formal" anklicken, passt das System den Standard-Ton automatisch an.

**✏️ Bearbeitet:** Wenn der Benutzer die KI-Ausgabe vor dem Verwenden bearbeitet (z.B. E-Mail-Text anpasst), speichert das System sowohl den Original-Output als auch die bearbeitete Version. Die Differenz (Diff) wird analysiert: Welche Passagen wurden geändert? Wurden Informationen hinzugefügt oder entfernt? Wurde der Ton geändert? Diese Informationen fließen in die Prompt-Optimierung ein, sodass zukünftige Ausgaben näher an dem sind, was der Benutzer tatsächlich braucht.

**💬 Feedback:** Freitextfeld für detailliertes Feedback. Besonders wertvoll für Fälle, die nicht in die vorgefertigten Kategorien passen. Beispiel: „Bei Angeboten für Lichtequipment bitte immer die DMX-Kanäle erwähnen" – solche Hinweise werden als dauerhafte Präferenz gespeichert.

### I10.3 Implizites Feedback-Tracking

Neben dem aktiven Feedback beobachtet das System auch **passive Signale**, die auf die Qualität der KI-Ausgaben hindeuten:

**Akzeptanz-Rate:** Wird ein KI-Vorschlag unverändert übernommen? Wird er bearbeitet? Wird er komplett verworfen? Für jeden Task-Typ (E-Mail, Beschreibung, Preis, Kategorie) wird eine Akzeptanz-Rate berechnet und im Admin-Dashboard angezeigt. Eine Rate von 80%+ zeigt: Die KI ist gut kalibriert. Eine Rate unter 50% signalisiert: Handlungsbedarf, die Prompts oder das Modell müssen angepasst werden.

**Bearbeitungszeit:** Wie lange braucht der Benutzer, um die KI-Ausgabe zu überprüfen und ggf. anzupassen? Kürzere Zeiten deuten auf höhere Qualität hin. Wenn die Bearbeitungszeit über Zeit sinkt, verbessert sich das System.

**Wiederverwendungsmuster:** Welche Phrasen, Formulierungen oder Strukturen verwendet der Benutzer immer wieder? Wenn ein Benutzer z.B. bei jeder E-Mail die Grußformel ändert, lernt das System die bevorzugte Grußformel.

**Undo-Aktionen:** Wenn ein Benutzer einen KI-Vorschlag übernimmt, dann aber innerhalb von 60 Sekunden die Aktion rückgängig macht, wird das als starkes negatives Signal gewertet.

**Asset-Korrekturen:** Wenn die KI bei der Smart Asset Creation technische Daten vorschlägt und der Benutzer Werte ändert (z.B. Gewicht von 8.2 kg auf 7.9 kg korrigiert), wird die Korrektur als Ground-Truth gespeichert und bei zukünftigen Lookups desselben Produkts berücksichtigt.

### I10.4 Knowledge Base und RAG (Retrieval Augmented Generation)

Das RAG-System ist die Grundlage für kontextbewusstes, unternehmensspezifisches KI-Wissen. Statt sich nur auf das allgemeine Training des LLM zu verlassen, baut MyRMS eine lokale **Wissensbasis** auf, die bei jeder KI-Anfrage durchsucht wird und relevante Kontextinformationen in den Prompt injiziert.

**Automatisch indexierte Datenquellen:**

Die Wissensbasis wird automatisch aus den vorhandenen MyRMS-Daten aufgebaut – der Benutzer muss nichts manuell pflegen. Folgende Quellen werden indexiert:

- **E-Mail-Vorlagen und Korrespondenz:** Alle gesendeten E-Mails, erfolgreiche Angebote, Kundenkommunikation. Die KI kann so den Kommunikationsstil des Unternehmens lernen und bei neuen E-Mails anwenden.
- **Projekthistorie:** Abgeschlossene Projekte mit Equipment-Listen, Preisen, Zeiträumen. Die KI kann bei neuen ähnlichen Anfragen auf historische Projekte verweisen: „Ähnliches Projekt für Firma Müller im Oktober 2025: 15 Scheinwerfer, 3 Tage, €4.200."
- **Asset-Datenbank:** Alle technischen Daten, Beschreibungen, Kategorien, Preise. Die KI kennt den gesamten Bestand und kann bei Anfragen sofort relevante Assets empfehlen.
- **Kundenpräferenzen:** Notizen, bevorzugte Geräte, Rabattvereinbarungen, Besonderheiten pro Kunde.
- **Unternehmens-Richtlinien:** AGB, Mietbedingungen, Versicherungsregelungen, Schadens-Policies – alles, was die KI kennen muss, um korrekte Antworten zu geben.
- **Benutzer-Feedback-Datenbank:** Alle positiven/negativen Bewertungen und Korrekturen aus I10.2 und I10.3.

**Technische Umsetzung:**

Die Texte werden in **Embeddings** umgewandelt (Vektordarstellungen) und in einer Vektordatenbank gespeichert. Bei jeder KI-Anfrage wird die Anfrage ebenfalls in einen Embedding-Vektor umgewandelt und die semantisch ähnlichsten Einträge aus der Wissensbasis abgerufen. Diese relevanten Kontexte werden dem LLM-Prompt vorangestellt, sodass die KI auf unternehmensspezifisches Wissen zugreifen kann, ohne es im LLM-Training gesehen zu haben.

```
Technologie-Optionen:
├── Vektordatenbank: ChromaDB (Self-Hosted, Python) oder pgvector (PostgreSQL Extension)
├── Embedding-Modell: OpenAI text-embedding-3-small ($0.02/MTok) oder lokales Modell
├── Chunk-Größe: 500 Tokens pro Dokument-Segment (mit 50 Token Overlap)
├── Aktualisierung: Echtzeit bei Datenänderung (Event-basiert)
└── Suche: Top-5 relevanteste Chunks pro Anfrage in den Prompt injiziert
```

**RAG-Pipeline im Detail:**

```
Benutzer-Anfrage: "Erstelle ein Angebot für Firma Schneider über Lichttechnik"
        │
        ▼
┌─ Embedding-Suche ─────────────────────────────────────────┐
│  Query → Vector → Similarity Search in Knowledge Base     │
│                                                           │
│  Gefundene relevante Kontexte:                            │
│  1. Kundenprofil Schneider (Rabatt 10%, bevorzugt ETC)    │
│  2. Letztes Projekt Schneider (Okt 2025, €3.800, Licht)  │
│  3. E-Mail-Vorlage Angebote (Firmen-Stil, Grußformel)    │
│  4. Aktuelle Preisliste Beleuchtung (Stand: März 2026)    │
│  5. Positives Feedback: "Angebot im Bullet-Point-Stil"   │
└───────────────────────────────────────────────────────────┘
        │
        ▼
┌─ Prompt-Zusammensetzung ──────────────────────────────────┐
│  System-Prompt (Rolle, Regeln)                            │
│  + RAG-Kontext (5 relevante Chunks)                       │
│  + Few-Shot-Beispiele (2 erfolgreiche Angebote)           │
│  + Benutzer-Präferenzen (Ton: professionell, Format: kurz)│
│  + Aktuelle Anfrage                                       │
└───────────────────────────────────────────────────────────┘
        │
        ▼
  LLM generiert kontextbewusstes, personalisiertes Angebot
```

### I10.5 Few-Shot Learning: Die besten Beispiele automatisch nutzen

Few-Shot Learning bedeutet: Die besten Beispiele aus der Vergangenheit werden automatisch in den Prompt eingebaut, damit die KI weiß, wie eine gute Ausgabe für diesen spezifischen Kontext aussieht. MyRMS baut automatisch eine **Beispiel-Bibliothek** auf:

**Automatische Kuratierung:** Jede KI-Ausgabe, die einen Daumen-Hoch erhält oder unverändert übernommen wird, wird als positives Beispiel in die Bibliothek aufgenommen. Negative Beispiele werden ebenfalls gespeichert. Das System wählt bei jeder neuen Anfrage die 2-3 relevantesten positiven Beispiele aus und fügt sie als Few-Shot-Kontext in den Prompt ein.

**Beispiel-Selektion:** Die Auswahl der besten Beispiele basiert auf mehreren Faktoren: semantische Ähnlichkeit zur aktuellen Anfrage (gleicher Task-Typ, ähnlicher Kontext), Bewertungsqualität (mehr Daumen-Hoch = höhere Priorität), Aktualität (neuere Beispiele werden bevorzugt), und Benutzer-Spezifität (Beispiele vom gleichen Benutzer werden bevorzugt, da dieser möglicherweise einen eigenen Stil hat).

**Dynamische Prompt-Optimierung:** Das System passt die System-Prompts kontinuierlich an, basierend auf aggregiertem Feedback. Wenn z.B. 70% der Benutzer E-Mail-Entwürfe als „zu lang" bewerten, wird der System-Prompt automatisch um die Anweisung „Halte E-Mails kurz und prägnant, max. 5-7 Sätze" ergänzt. Diese Anpassungen werden protokolliert und können vom Admin überprüft und rückgängig gemacht werden.

### I10.6 Instanz-spezifisches Lernprofil

Jede MyRMS-Instanz (jedes Unternehmen) entwickelt über Zeit ein eigenes **Lernprofil**, das die KI auf die spezifischen Bedürfnisse und den Stil des Unternehmens anpasst:

```
┌─ Lernprofil: Event-Technik Müller GmbH ──────────────────┐
│                                                           │
│  Kommunikationsstil:                                      │
│  ├── Anrede: "Hallo [Vorname]" (85% der Korrekturen)    │
│  ├── Ton: Freundlich-professionell (nicht steif)         │
│  ├── Länge: Kurz (Ø 4 Sätze pro E-Mail bevorzugt)      │
│  └── Grußformel: "Viele Grüße, Team Müller Event"       │
│                                                           │
│  Branchenwissen:                                          │
│  ├── Fokus: Veranstaltungstechnik (Licht, Ton, Video)    │
│  ├── Hauptkunden: Agenturen, Corporates, Kommunen        │
│  ├── Preismodell: Tages-/Wochenmiete + Pauschalen        │
│  └── Besonderheiten: Immer Transport inkl., 10% Rabatt   │
│      für Stammkunden, Kaution bei Neukunden              │
│                                                           │
│  Asset-Präferenzen:                                       │
│  ├── Bevorzugte Marken: ETC, d&b, Sennheiser            │
│  ├── Standard-Pakete: "Basis Licht" = 12× S4 + 2× Haze │
│  ├── Mietpreis-Faktor: 1.3% (über Branchendurchschnitt) │
│  └── Kategorie-Struktur: 4 Hauptkategorien, 18 Sub      │
│                                                           │
│  Lernfortschritt:                                         │
│  ├── Gesamt-Interaktionen: 2,847                         │
│  ├── Positive Bewertungen: 2,134 (75%)                   │
│  ├── Akzeptanz-Rate: 68% → 82% (letzte 3 Monate: +14%) │
│  ├── Ø Bearbeitungszeit: 45s → 28s (Verbesserung: 38%) │
│  └── Knowledge Base: 1,247 Einträge, 89 Few-Shot-Bsp.   │
│                                                           │
│  [Profil exportieren]  [Profil zurücksetzen]              │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### I10.7 Benutzer-spezifische Personalisierung

Zusätzlich zum Instanz-Profil merkt sich das System auch **individuelle Benutzer-Präferenzen**. Verschiedene Mitarbeiter haben unterschiedliche Stile, Aufgabenbereiche und Erwartungen. Das System passt sich pro Benutzer an:

Der Buchhalter bevorzugt formal-korrekte E-Mails mit allen rechtlichen Klauseln, der Projektmanager will kurze, direkte Kommunikation, und der Geschäftsführer erwartet professionelle Angebote mit detaillierten Begründungen. Das System lernt diese Unterschiede automatisch und liefert jedem Benutzer passende Ergebnisse – ohne dass jemand explizit etwas konfigurieren muss. Die Personalisierung entsteht organisch aus dem Nutzungsverhalten.

### I10.8 Admin-Dashboard: KI-Lern-Analytics

Im Admin-Bereich zeigt ein dediziertes Dashboard den Lernfortschritt der KI:

```
┌─ KI-Lernsystem Status ───────────────────────────────────┐
│                                                           │
│  Gesamtbewertungen: 2,847  │  Akzeptanz: 82% (+14% QoQ) │
│                                                           │
│  Lernfortschritt über Zeit:                               │
│  100%│                                          ╱──82%   │
│   80%│                              ╱──────────╱         │
│   60%│              ╱──────────────╱                     │
│   40%│  ╱──────────╱                                     │
│   20%│ ╱                                                  │
│    0%│──────────────────────────────────────────          │
│      Jan    Feb    Mär    Apr    Mai    Jun               │
│                                                           │
│  ─── Top-Verbesserungen ──────────────────────────────    │
│  • E-Mail-Ton: 54% → 89% Akzeptanz (+35%)               │
│  • Asset-Kategorisierung: 61% → 91% (+30%)               │
│  • Mietpreis-Vorschläge: 45% → 78% (+33%)               │
│  • Schadensbericht-Qualität: 72% → 88% (+16%)           │
│                                                           │
│  ─── Handlungsbedarf ─────────────────────────────────    │
│  ⚠️ Angebotstexte: nur 52% Akzeptanz (Feedback: "zu     │
│     generisch") → Empfehlung: Mehr Branchen-Beispiele    │
│     in Few-Shot-Bibliothek aufnehmen                     │
│                                                           │
│  ─── Knowledge Base ──────────────────────────────────    │
│  Einträge: 1,247 │ Vektoren: 4,832 │ Letzte Sync: 2min  │
│  Few-Shot-Beispiele: 89 (47 E-Mail, 12 Angebot,         │
│                          18 Beschreibung, 12 Sonstige)   │
│                                                           │
│  ─── Prompt-Anpassungen (automatisch) ────────────────    │
│  #12 Mär 15: "E-Mails max 5 Sätze" (78% neg. "zu lang")│
│  #11 Mär 08: "DU-Form für Stammkunden" (82% Korrekturen)│
│  #10 Feb 28: "Preise immer netto + MwSt" (91% Korrektur)│
│  [Alle anzeigen] [Anpassung rückgängig machen]           │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### I10.9 Prompt-Versioning und A/B-Testing

Um die Prompt-Optimierung messbar zu machen, implementiert MyRMS ein **Prompt-Versioning-System**: Jede Änderung am System-Prompt wird als neue Version gespeichert, mit Timestamp, Auslöser (manuell oder automatisch) und Performance-Metriken. Administratoren können verschiedene Prompt-Versionen per A/B-Test vergleichen: 50% der Anfragen gehen an Prompt v12 (bisherig), 50% an Prompt v13 (optimiert). Nach ausreichend Datenpunkten (z.B. 100 Bewertungen pro Version) wird automatisch die besser bewertete Version zum Standard.

```
┌─ Prompt-Versionen (E-Mail-Drafts) ───────────────────────┐
│                                                           │
│  Version │ Erstellt   │ Akzeptanz │ Status    │ Aktion   │
│  v13     │ 15.03.2026 │ 86%       │ ● A/B-Test│ [Stopp]  │
│  v12     │ 08.03.2026 │ 82%       │ ● Aktiv   │ [Edit]   │
│  v11     │ 28.02.2026 │ 74%       │ ○ Archiv  │ [Restore]│
│  v10     │ 15.02.2026 │ 68%       │ ○ Archiv  │ [Restore]│
│                                                           │
│  [+ Neue Version erstellen]  [A/B-Test starten]          │
│                                                           │
│  Diff v12 → v13:                                          │
│  + "Antworte in maximal 5 Sätzen, sei direkt und klar."  │
│  + "Verwende DU-Form bei Kunden mit >= 3 Projekten."     │
│  - "Formuliere stets höflich und professionell."          │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### I10.10 Konfigurations-UI (Settings → KI → Lernsystem)

```
┌─ Einstellungen → KI → Lernsystem ────────────────────────┐
│                                                           │
│  ─── Feedback-Sammlung ──────────────────────────────    │
│  ☑ Feedback-Buttons unter KI-Ausgaben anzeigen            │
│  ☑ Bearbeitungs-Diffs automatisch erfassen                │
│  ☑ Akzeptanz-Raten tracken                                │
│  ☑ Undo-Aktionen als negatives Signal werten              │
│                                                           │
│  ─── Knowledge Base (RAG) ───────────────────────────    │
│  ☑ E-Mail-Korrespondenz indexieren                        │
│  ☑ Projekthistorie indexieren                             │
│  ☑ Asset-Beschreibungen indexieren                        │
│  ☑ Kundendaten indexieren (Name, Notizen, Präferenzen)   │
│  ☐ Dokumente (Verträge, AGB) indexieren                  │
│  Embedding-Provider: [OpenAI text-embedding-3-small ▼]   │
│  Vektordatenbank: [ChromaDB (lokal) ▼]                   │
│  [Knowledge Base neu aufbauen] [Status: 1,247 Einträge]  │
│                                                           │
│  ─── Few-Shot Learning ──────────────────────────────    │
│  Max. Beispiele pro Prompt: [3 ▼]                        │
│  Nur Beispiele mit Bewertung >= [👍 Gut ▼] nutzen        │
│  Beispiele älter als [90 Tage ▼] archivieren             │
│  [Beispiel-Bibliothek anzeigen: 89 Einträge]            │
│                                                           │
│  ─── Automatische Prompt-Optimierung ────────────────    │
│  ☑ System-Prompts automatisch anpassen                    │
│  Schwellwert: Anpassung wenn [>70%] neg. Feedback         │
│  Min. Datenpunkte: [50] Bewertungen vor Anpassung        │
│  ☑ Admin benachrichtigen vor Anpassung                    │
│  ☐ Anpassung erst nach Admin-Freigabe aktivieren         │
│                                                           │
│  ─── A/B-Testing ────────────────────────────────────    │
│  ☑ A/B-Tests erlauben                                     │
│  Traffic-Split: [50/50 ▼]                                │
│  Min. Datenpunkte pro Variante: [100 ▼]                  │
│  Auto-Winner: [Ja, nach 200 Bewertungen ▼]              │
│                                                           │
│  ─── Datenschutz ────────────────────────────────────    │
│  ☑ Feedback-Daten anonymisieren nach 12 Monaten          │
│  ☑ Benutzer können eigene Feedback-Daten löschen         │
│  ☐ Knowledge Base auf lokale Vektordatenbank beschränken │
│    (kein Embedding an Cloud-Provider senden)             │
│                                                           │
│  [Änderungen speichern]  [Lernsystem zurücksetzen]       │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### I10.11 Technische Architektur

**Datenbank-Tabellen:**

```sql
-- Feedback-Tabelle
CREATE TABLE ai_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    instance_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,       -- 'email_draft', 'asset_description', etc.
    ai_output TEXT NOT NULL,               -- Original KI-Ausgabe
    user_edited TEXT,                       -- Bearbeitete Version (NULL wenn unverändert)
    rating ENUM('positive','negative','neutral'),
    feedback_reason VARCHAR(100),          -- 'too_formal', 'too_long', etc.
    feedback_text TEXT,                    -- Freitext-Feedback
    accepted BOOLEAN DEFAULT FALSE,        -- Wurde der Vorschlag übernommen?
    edit_time_ms INT,                      -- Bearbeitungszeit in Millisekunden
    provider VARCHAR(50),                  -- Welcher KI-Provider wurde genutzt
    model VARCHAR(100),                    -- Welches Modell
    prompt_version INT,                    -- Welche Prompt-Version
    tokens_input INT,
    tokens_output INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_rating (task_type, rating),
    INDEX idx_instance_date (instance_id, created_at)
);

-- Few-Shot-Beispiel-Bibliothek
CREATE TABLE ai_few_shot_examples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    input_context TEXT NOT NULL,           -- Der Kontext/die Anfrage
    output_example TEXT NOT NULL,          -- Die gute Ausgabe
    positive_votes INT DEFAULT 0,
    negative_votes INT DEFAULT 0,
    usage_count INT DEFAULT 0,            -- Wie oft als Beispiel genutzt
    embedding BLOB,                        -- Vektor für Similarity-Suche
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_active (task_type, is_active)
);

-- Prompt-Versionen
CREATE TABLE ai_prompt_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_type VARCHAR(50) NOT NULL,
    version INT NOT NULL,
    system_prompt TEXT NOT NULL,
    change_reason TEXT,                    -- Warum wurde geändert
    change_source ENUM('manual','automatic','ab_test'),
    acceptance_rate DECIMAL(5,2),          -- Ø Akzeptanz-Rate
    total_uses INT DEFAULT 0,
    is_active BOOLEAN DEFAULT FALSE,
    is_ab_test BOOLEAN DEFAULT FALSE,
    ab_test_traffic_pct INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_task_version (task_type, version)
);

-- Instanz-Lernprofil
CREATE TABLE ai_learning_profile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    profile_key VARCHAR(100) NOT NULL,     -- 'email_greeting', 'tone', 'price_factor'
    profile_value TEXT NOT NULL,
    confidence DECIMAL(3,2),               -- 0.00-1.00
    data_points INT DEFAULT 0,            -- Anzahl Beobachtungen
    last_updated TIMESTAMP,
    UNIQUE INDEX idx_instance_key (instance_id, profile_key)
);
```

**FeedbackLearningService (PHP):**

```php
class FeedbackLearningService
{
    /**
     * Speichert Feedback und triggert ggf. Prompt-Optimierung.
     */
    public function recordFeedback(
        int $userId,
        string $taskType,
        string $aiOutput,
        ?string $userEdited,
        string $rating,
        ?string $reason = null
    ): void {
        // 1. Feedback speichern
        $this->db->insert('ai_feedback', [...]);

        // 2. Bei positivem Feedback: Few-Shot-Kandidat prüfen
        if ($rating === 'positive') {
            $this->fewShotService->addCandidate($taskType, $aiOutput);
        }

        // 3. Lernprofil aktualisieren
        $this->profileService->updateFromFeedback($taskType, $rating, $reason, $userEdited);

        // 4. Prüfen ob Prompt-Optimierung nötig
        $this->checkAutoOptimization($taskType);
    }

    /**
     * Baut den optimierten Prompt mit RAG + Few-Shot + Profil.
     */
    public function buildEnrichedPrompt(
        int $userId,
        string $taskType,
        string $userQuery,
        array $context = []
    ): array {
        // 1. RAG: Relevante Kontexte aus Knowledge Base
        $ragChunks = $this->ragService->search($userQuery, limit: 5);

        // 2. Few-Shot: Beste Beispiele für diesen Task-Typ
        $examples = $this->fewShotService->getBest($taskType, $userQuery, limit: 3);

        // 3. Lernprofil: Benutzer- und Instanz-Präferenzen
        $profile = $this->profileService->getForUser($userId);

        // 4. Aktive Prompt-Version
        $systemPrompt = $this->promptService->getActive($taskType);

        // 5. Zusammenbauen
        return $this->assemblePrompt($systemPrompt, $ragChunks, $examples, $profile, $userQuery);
    }
}
```

### I10.12 Datenschutz und Löschrechte

Das Lernsystem respektiert vollständig die DSGVO. Benutzer können unter Einstellungen → Datenschutz → KI-Daten ihre gesamten Feedback-Daten einsehen und löschen (Recht auf Löschung, Art. 17 DSGVO). Feedback-Daten werden nach 12 Monaten automatisch anonymisiert (Benutzer-ID entfernt, nur aggregierte Muster bleiben erhalten). Die Knowledge Base kann so konfiguriert werden, dass Embeddings ausschließlich lokal erstellt werden (kein Versand an Cloud-Provider). Beim Löschen eines Benutzer-Accounts werden alle personenbezogenen Feedback-Daten automatisch gelöscht, während anonymisierte Muster erhalten bleiben.

---

**Document Version:** 3.3 (+ KI-Lernsystem)
**Last Updated:** March 18, 2026
