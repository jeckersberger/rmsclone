# MyRMS – Implementierungs-Checkliste

**Stand:** 18. März 2026
**Branch:** `feature/rfid-cases-invoices-android`

Diese Datei dokumentiert den Implementierungsstatus aller Features. Jedes Feature hat eine Checkliste mit den einzelnen Bausteinen (Migration, Service, API, UI). So ist jederzeit nachvollziehbar, was fertig ist, was noch getestet werden muss und was fehlt.

## Technische Integration in MyRMS

Die MyRMS-KI (AiRequestHandler + FeedbackLearningService) liest und schreibt diese Datei automatisch:
- **Lesen:** `ImplementationTrackerService::getChecklist()` – parst diese MD-Datei und gibt den Status aller Bausteine zurück
- **Schreiben:** `ImplementationTrackerService::updateStatus($module, $baustein, $status)` – aktualisiert den Status eines Bausteins
- **Trigger:** Nach jedem abgeschlossenen KI-Task (z.B. Wartungsplan erstellt, Vertrag generiert, Schaden bewertet) prüft das System ob ein 🔧-Baustein in ✅ umgewandelt werden kann
- **Dashboard-Widget:** Das Admin-Dashboard zeigt ein "Implementierungsstatus"-Widget das aus dieser Datei gespeist wird (Fortschrittsbalken pro Modul)
- **Sync:** Die Datei wird bidirektional mit der `ai_implementation_status`-Tabelle synchronisiert. Änderungen in der Datei werden beim nächsten Service-Aufruf in die DB geschrieben und umgekehrt.

**Legende:**
- ✅ = Implementiert und committed
- 🔧 = Implementiert, braucht Review/Anpassung
- ⬜ = Noch nicht implementiert
- 🧪 = Braucht Tests

---

## L1 – Flexible Lagerorte

Frei definierbare Lagerorte (Auto, Anhänger, Büro, Garage, Keller etc.). Assets bleiben frei buchbar, Lagerort wird überall angezeigt.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: emoji_icon + instances_id auf locations, bulk_move_log | ✅ | `db/migrations/20260318110000_flexible_lagerorte.php` |
| 2 | LocationService erweitert: getLocationSummary() | ✅ | `src/services/LocationService.php` |
| 3 | LocationService: bulkMoveAssets(), bulkMoveStockInstances() | ✅ | `src/services/LocationService.php` |
| 4 | LocationService: getAssetsAtLocation(), createLocationInline() | ✅ | `src/services/LocationService.php` |
| 5 | API: Location Summary Endpoint | ✅ | `src/api/locations/` |
| 6 | API: Bulk Move Endpoint | ✅ | `src/api/locations/` |
| 7 | API: Assets at Location Endpoint | ✅ | `src/api/locations/` |
| 8 | API: Inline Create Endpoint | ✅ | `src/api/locations/` |
| 9 | UI: manage.twig mit Emoji-Icons und Asset-Counts | ✅ | `src/locations/manage.twig` |
| 10 | Lagerort-Spalte in Asset-Listen | 🔧 | Muss in bestehenden Asset-Tabellen ergänzt werden |
| 11 | Lagerort-Abfrage bei Check-In | 🔧 | Hook in CheckInOutService nötig |
| 12 | Dashboard-Widget: Lagerort-Zusammenfassung | ⬜ | Noch nicht im Dashboard integriert |
| 13 | Tests | 🧪 | Unit-Tests für LocationService |

---

## L2 – Preiskalkulations-Engine

Staffelpreise (Tag/Woche/Monat), Mengenrabatte, Saisonzuschläge, Bundle-Pricing, kundenspezifische Preislisten.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 7 Tabellen (pricing_tiers, volume_discounts, seasonal_surcharges, bundles, bundle_items, customer_lists, list_items) | ✅ | `db/migrations/20260318120000_pricing_engine.php` |
| 2 | PricingEngineService: calculatePrice() mit Kaskade | ✅ | `src/services/PricingEngineService.php` |
| 3 | PricingEngineService: Staffelpreis-CRUD | ✅ | `src/services/PricingEngineService.php` |
| 4 | PricingEngineService: Mengenrabatte CRUD | ✅ | `src/services/PricingEngineService.php` |
| 5 | PricingEngineService: Saisonzuschläge CRUD | ✅ | `src/services/PricingEngineService.php` |
| 6 | PricingEngineService: Bundle-Verwaltung | ✅ | `src/services/PricingEngineService.php` |
| 7 | PricingEngineService: Kundenpreislisten | ✅ | `src/services/PricingEngineService.php` |
| 8 | API: 10 Endpunkte in /api/pricing/ | ✅ | `src/api/pricing/` |
| 9 | UI: pricing_manage.twig mit 5-Tab Layout | ✅ | `src/pricing/pricing_manage.twig` |
| 10 | JS: Echtzeit-Preisrechner | ✅ | `src/static-assets/js/pricing_engine.js` |
| 11 | Integration in Projektanlage: Preisberechnung automatisch | 🔧 | Hook in Projektservice nötig |
| 12 | Integration in Rechnungserstellung | 🔧 | Hook in InvoiceService nötig |
| 13 | Tests | 🧪 | Unit-Tests für calculatePrice() Kaskade |

---

## J2 – Wartung & Predictive Maintenance

Wartungspläne pro Asset, Service-Historie, automatische Erinnerungen, Checklisten.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 5 Tabellen (schedules, jobs, photos, checklists, results) | ✅ | `db/migrations/20260318130000_maintenance_system.php` |
| 2 | MaintenanceService: Schedule-CRUD | ✅ | `src/services/MaintenanceService.php` |
| 3 | MaintenanceService: Job-Lifecycle (create, update, complete) | ✅ | `src/services/MaintenanceService.php` |
| 4 | MaintenanceService: getOverdueMaintenances(), getUpcoming() | ✅ | `src/services/MaintenanceService.php` |
| 5 | MaintenanceService: checkAndCreateScheduledJobs() (CRON) | ✅ | `src/services/MaintenanceService.php` |
| 6 | MaintenanceService: Checklisten + Foto-Upload | ✅ | `src/services/MaintenanceService.php` |
| 7 | MaintenanceService: Kosten-Tracking + Dashboard-Stats | ✅ | `src/services/MaintenanceService.php` |
| 8 | API: 12 Endpunkte in /api/maintenance/ | ✅ | `src/api/maintenance/` |
| 9 | UI: maintenance_dashboard.twig (4 Status-Cards + 4 Tabs) | ✅ | `src/maintenance/maintenance_dashboard.twig` |
| 10 | Controller: index.php | ✅ | `src/maintenance/index.php` |
| 11 | Wartungsstatus-Badge auf Asset-Karte (ok/due_soon/overdue) | 🔧 | Muss in Asset-Ansichten integriert werden |
| 12 | CRON-Einrichtung für automatische Job-Erstellung | 🔧 | Muss in CRON-Config eingetragen werden |
| 13 | Tests | 🧪 | Unit-Tests für MaintenanceService |

---

## K1 – Automatisierte Workflow-Engine

No-Code WENN→DANN Logik, Event/CRON/Manuelle Trigger, 7 Aktionstypen, vorgefertigte Templates.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 5 Tabellen (workflows, steps, executions, logs, templates) | ✅ | `db/migrations/20260318140000_workflow_engine.php` |
| 2 | WorkflowEngineService: Workflow-CRUD | ✅ | `src/services/WorkflowEngineService.php` |
| 3 | WorkflowEngineService: executeWorkflow() mit Schritt-für-Schritt | ✅ | `src/services/WorkflowEngineService.php` |
| 4 | WorkflowEngineService: 7 Aktionstypen (Email, Task, Status, Notify, Webhook, Delay, Condition) | ✅ | `src/services/WorkflowEngineService.php` |
| 5 | WorkflowEngineService: 4 Templates (Mahnwesen, Wartung, Onboarding, Erinnerung) | ✅ | `src/services/WorkflowEngineService.php` |
| 6 | WorkflowEngineService: Execution-Logging | ✅ | `src/services/WorkflowEngineService.php` |
| 7 | API: 11 Endpunkte in /api/workflows/ | ✅ | `src/api/workflows/` |
| 8 | UI: workflows_index.twig (visueller Editor) | ✅ | `src/workflows/workflows_index.twig` |
| 9 | Controller: index.php | ✅ | `src/workflows/index.php` |
| 10 | Event-Hooks: Trigger bei Rechnung erstellt, Asset zurück, etc. | 🔧 | Muss in bestehende Services eingehängt werden |
| 11 | CRON-Integration für zeitbasierte Trigger | 🔧 | Muss in CRON-Config eingetragen werden |
| 12 | Tests | 🧪 | Unit-Tests für executeWorkflow() |

---

## J1 – Digitales Vertragsmanagement

Template-Engine, Platzhalter, Versionierung, digitale Signatur, GoBD-Archivierung.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 5 Tabellen (contracts, versions, templates, agb_sets, audit) | ✅ | `db/migrations/20260318150000_contract_management.php` |
| 2 | ContractService: Template-Engine mit Platzhaltern | ✅ | `src/services/ContractService.php` |
| 3 | ContractService: Versionierung | ✅ | `src/services/ContractService.php` |
| 4 | ContractService: Auto-Generierung aus Projektdaten | ✅ | `src/services/ContractService.php` |
| 5 | ContractService: PDF-Generierung (dompdf) | ✅ | `src/services/ContractService.php` |
| 6 | ContractService: Canvas-basierte Signatur | ✅ | `src/services/ContractService.php` |
| 7 | ContractService: Status-Workflow (Entwurf→Gesendet→Unterschrieben→Aktiv) | ✅ | `src/services/ContractService.php` |
| 8 | ContractService: AGB-Verwaltung | ✅ | `src/services/ContractService.php` |
| 9 | API: 12 Endpunkte in /api/contracts/ | ✅ | `src/api/contracts/` |
| 10 | Öffentliche Signatur-Seite (kein Login nötig) | ✅ | `src/contracts/sign.php` |
| 11 | UI: contracts_index.twig | ✅ | `src/templates/contracts_index.twig` |
| 12 | Controller: index.php | ✅ | `src/contracts/index.php` |
| 13 | GoBD-Audit-Trail (IP, User-Agent) | ✅ | In ContractService integriert |
| 14 | Tests | 🧪 | Unit-Tests für ContractService |

---

## K3 – Schadenmanagement-Workflow

8-Schritte-Prozess, 11 Status-Typen, Kostenvoranschläge, Fotos, Versicherungsmeldung.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 4 Tabellen (workflows, log, cost_estimates, photos) | ✅ | `db/migrations/20260318160000_damage_workflow.php` |
| 2 | DamageWorkflowService: 11 Status mit erzwungenen Übergängen | ✅ | `src/services/DamageWorkflowService.php` |
| 3 | DamageWorkflowService: Multi-Vendor Kostenvoranschläge | ✅ | `src/services/DamageWorkflowService.php` |
| 4 | DamageWorkflowService: Foto-Dokumentation (vorher/während/nachher) | ✅ | `src/services/DamageWorkflowService.php` |
| 5 | DamageWorkflowService: Versicherungsmeldung | ✅ | `src/services/DamageWorkflowService.php` |
| 6 | DamageWorkflowService: Kunden-Weiterbelastung + Kautionsabzug | ✅ | `src/services/DamageWorkflowService.php` |
| 7 | API: 13 Endpunkte in /api/damage/ | ✅ | `src/api/damage/` |
| 8 | UI: Kanban-Board (damage_dashboard.twig) | ✅ | `src/damage/damage_dashboard.twig` |
| 9 | UI: Detail-View mit Timeline (damage_detail.twig) | ✅ | `src/damage/damage_detail.twig` |
| 10 | Controller: index.php | ✅ | `src/damage/index.php` |
| 11 | Integration mit bestehendem DamageReportService | ✅ | Erweitert, nicht ersetzt |
| 12 | Tests | 🧪 | Unit-Tests für DamageWorkflowService |

---

## K2 – Versicherungsmanagement

Policen-Verwaltung, Deckungsprüfung, Ablauf-Warnungen, Schadenmeldungen.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 4 Tabellen (policies, asset_coverage, client_certificates, claims) | ✅ | `db/migrations/20260318170000_insurance_management.php` |
| 2 | InsuranceService: Policen-CRUD | ✅ | `src/services/InsuranceService.php` |
| 3 | InsuranceService: Deckungsprüfung + Deckungslücken | ✅ | `src/services/InsuranceService.php` |
| 4 | InsuranceService: Kundennachweise mit Verifizierung | ✅ | `src/services/InsuranceService.php` |
| 5 | InsuranceService: Schadenmeldungen (8 Status) | ✅ | `src/services/InsuranceService.php` |
| 6 | InsuranceService: Dashboard-Stats | ✅ | `src/services/InsuranceService.php` |
| 7 | API: 12 Endpunkte in /api/insurance/ | ✅ | `src/api/insurance/` |
| 8 | UI: insurance_index.twig | ✅ | `src/insurance/insurance_index.twig` |
| 9 | Controller: index.php | ✅ | `src/insurance/index.php` |
| 10 | Integration mit K3 Schadenmanagement | ✅ | Verknüpfung über damage_workflows |
| 11 | Tests | 🧪 | Unit-Tests für InsuranceService |

---

## J3 – Transport & Logistik

Fahrzeug-Flotte, Fahrer, Tourenplanung, Kapazitätsprüfung, QR-Übergabe, Kosten-Tracking.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 5 Tabellen (vehicles, drivers, tours, tour_stops, costs) | ✅ | `db/migrations/20260318180000_transport_logistics.php` |
| 2 | TransportLogisticsService: Fahrzeug- + Fahrer-Verwaltung | ✅ | `src/services/TransportLogisticsService.php` |
| 3 | TransportLogisticsService: Multi-Stopp Tourenplanung | ✅ | `src/services/TransportLogisticsService.php` |
| 4 | TransportLogisticsService: Kapazitätsprüfung (Gewicht + Volumen) | ✅ | `src/services/TransportLogisticsService.php` |
| 5 | TransportLogisticsService: Übergabe-Bestätigung mit Unterschrift | ✅ | `src/services/TransportLogisticsService.php` |
| 6 | TransportLogisticsService: Kosten-Tracking | ✅ | `src/services/TransportLogisticsService.php` |
| 7 | API: 11 Endpunkte in /api/transport/ | ✅ | `src/api/transport/` |
| 8 | UI: transport_index.twig mit Kalender | ✅ | `src/transport/transport_index.twig` |
| 9 | Controller: index.php | ✅ | `src/transport/index.php` |
| 10 | Tests | 🧪 | Unit-Tests für TransportLogisticsService |

---

## I1-I3 – Multi-KI-Provider System

6 Provider-Adapter, Task-Routing, Fallback-Kette, AES-256 verschlüsselte Keys, Usage-Tracking.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 4 Tabellen (providers, task_routing, usage_log, fallback_chain) | ✅ | `db/migrations/20260318190000_multi_ai_providers.php` |
| 2 | LlmProviderInterface + LlmResponse | ✅ | `src/services/AI/LlmProviderInterface.php`, `LlmResponse.php` |
| 3 | Adapter: ClaudeAdapter | ✅ | `src/services/AI/Providers/ClaudeAdapter.php` |
| 4 | Adapter: OpenAiAdapter | ✅ | `src/services/AI/Providers/OpenAiAdapter.php` |
| 5 | Adapter: GeminiAdapter | ✅ | `src/services/AI/Providers/GeminiAdapter.php` |
| 6 | Adapter: MistralAdapter | ✅ | `src/services/AI/Providers/MistralAdapter.php` |
| 7 | Adapter: OllamaAdapter | ✅ | `src/services/AI/Providers/OllamaAdapter.php` |
| 8 | Adapter: OpenAiCompatibleAdapter (generisch) | ✅ | `src/services/AI/Providers/OpenAiCompatibleAdapter.php` |
| 9 | AiProviderRegistry: Registrierung, Task-Routing, Fallback | ✅ | `src/services/AI/AiProviderRegistry.php` |
| 10 | AiRequestHandler: Zentraler Einstiegspunkt für alle KI-Calls | ✅ | `src/services/AI/AiRequestHandler.php` |
| 11 | AiUsageTracker: Token-Logging + Kosten-Schätzung | ✅ | `src/services/AI/AiUsageTracker.php` |
| 12 | AiInitializer: Bootstrap-Helper | ✅ | `src/services/AI/AiInitializer.php` |
| 13 | API-Key Verschlüsselung (AES-256-GCM) | ✅ | In AiProviderRegistry |
| 14 | API: 6 Endpunkte in /api/ai/ | ✅ | `src/api/ai/` |
| 15 | UI: ai_settings.twig mit Provider-Cards | ✅ | `src/templates/ai/ai_settings.twig` |
| 16 | Config: ai_bootstrap.php | ✅ | `config/ai_bootstrap.php` |
| 17 | Migration bestehender ClaudeService auf AiRequestHandler | 🔧 | ClaudeService muss umgestellt werden |
| 18 | Tests | 🧪 | Unit-Tests für Adapter + Registry |

---

## I6 – KI-Anonymisierung

Pflicht-Anonymisierung für alle Cloud-KI-Requests. 3-Phasen-Pipeline, 7 Regex-Patterns, DB-Abgleich.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 2 Tabellen (config, audit_log) | ✅ | `db/migrations/20260318200000_ai_anonymization.php` |
| 2 | AnonymizationService: anonymize() + deAnonymize() | ✅ | `src/services/AI/AnonymizationService.php` |
| 3 | AnonymizationService: 7 Regex-Patterns (IBAN, E-Mail, Telefon, USt-IdNr, Steuer, IP, Datum) | ✅ | `src/services/AI/AnonymizationService.php` |
| 4 | AnonymizationService: DB-Abgleich (Kunden, Kontakte, Mitarbeiter) | ✅ | `src/services/AI/AnonymizationService.php` |
| 5 | AnonymizationService: 4 Modi (Strikt/Standard/Minimal/Aus) | ✅ | `src/services/AI/AnonymizationService.php` |
| 6 | Integration in AiRequestHandler (automatisch) | ✅ | `src/services/AI/AiRequestHandler.php` |
| 7 | Cloud-Provider min. Standard erzwungen | ✅ | Hardcoded in AiRequestHandler |
| 8 | API: anonymization_config + anonymization_test | ✅ | `src/api/ai/` |
| 9 | Audit-Log: nur Counts/Typen, nie echte Daten | ✅ | In AnonymizationService |
| 10 | Tests | 🧪 | Unit-Tests für Regex-Patterns + Roundtrip |

---

## I9 – Smart Asset Creator

Hersteller + Modell eingeben → KI füllt technische Daten automatisch. Foto-Erkennung, Bulk-Import.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 3 Tabellen (cache, corrections, bulk_jobs) | ✅ | `db/migrations/20260318210000_smart_asset_creator.php` |
| 2 | AssetLookupResult (Value Object) | ✅ | `src/services/AssetLookupResult.php` |
| 3 | SmartAssetLookupService: lookup() mit Cache + AI-Fallback | ✅ | `src/services/SmartAssetLookupService.php` |
| 4 | SmartAssetLookupService: lookupFromPhoto() (Vision-KI) | ✅ | `src/services/SmartAssetLookupService.php` |
| 5 | SmartAssetLookupService: bulkLookup() (Background-Job) | ✅ | `src/services/SmartAssetLookupService.php` |
| 6 | SmartAssetLookupService: suggestRentalPrice() | ✅ | `src/services/SmartAssetLookupService.php` |
| 7 | SmartAssetLookupService: suggestCategory() | ✅ | `src/services/SmartAssetLookupService.php` |
| 8 | SmartAssetLookupService: recordCorrection() (Lern-Feedback) | ✅ | `src/services/SmartAssetLookupService.php` |
| 9 | SmartAssetLookupService: Hersteller/Modell-Autocomplete | ✅ | `src/services/SmartAssetLookupService.php` |
| 10 | API: 7 Endpunkte in /api/assets/ | ✅ | `src/api/assets/smart_*.php` |
| 11 | UI: 3-Step Wizard Modal (smart_create_modal.twig) | ✅ | `src/assets/smart_create_modal.twig` |
| 12 | Integration in Asset-Neuanlage-Formular | 🔧 | Modal muss in bestehende Seite eingebunden werden |
| 13 | Tests | 🧪 | Unit-Tests für SmartAssetLookupService |

---

## I10 – KI-Lernsystem

Feedback (👍/👎), implizites Tracking, Few-Shot-Bibliothek, Prompt-Versioning, A/B-Testing.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 4 Tabellen (feedback, few_shot_examples, prompt_versions, learning_profile) | ✅ | `db/migrations/20260318220000_ai_learning_system.php` |
| 2 | FeedbackLearningService: recordFeedback() | ✅ | `src/services/AI/FeedbackLearningService.php` |
| 3 | FeedbackLearningService: buildEnrichedPrompt() (RAG + Few-Shot + Profil) | ✅ | `src/services/AI/FeedbackLearningService.php` |
| 4 | FeedbackLearningService: Auto-Prompt-Optimierung | ✅ | `src/services/AI/FeedbackLearningService.php` |
| 5 | FeedbackLearningService: A/B-Testing + Prompt-Versioning | ✅ | `src/services/AI/FeedbackLearningService.php` |
| 6 | FeedbackLearningService: Lernprofil pro Instanz/Benutzer | ✅ | `src/services/AI/FeedbackLearningService.php` |
| 7 | API: 7 Endpunkte in /api/ai/ (feedback, stats, few_shot, prompts) | ✅ | `src/api/ai/` |
| 8 | UI: Feedback-Widget (Twig-Partial, überall einbindbar) | ✅ | `src/templates/ai/feedback_widget.twig` |
| 9 | UI: KI-Lernsystem Dashboard (ai_learning.twig) | ✅ | `src/templates/ai/ai_learning.twig` |
| 10 | Controller: learning.php | ✅ | `src/ai/learning.php` |
| 11 | Feedback-Widget in bestehende KI-Ausgaben einbinden | 🔧 | Muss in E-Mail-Drafts, Chat etc. integriert werden |
| 12 | RAG/Vektordatenbank-Integration | ⬜ | ChromaDB/pgvector noch nicht angebunden |
| 13 | Tests | 🧪 | Unit-Tests für FeedbackLearningService |

---

## I11 – KI-Transportkostenberechnung

KI-gestützte Kostenprognose für Touren, Fahrzeug-TCO-Analyse, Budget-Forecasting und Anomalie-Erkennung im Fuhrpark.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 2 Tabellen (ai_transport_cost_predictions, ai_vehicle_tco_cache) | ⬜ | `db/migrations/YYYYMMDD_ai_transport_costs.php` |
| 2 | AiTransportCostService: Kraftstoffkosten-Prognose (Strecke × Fahrzeugtyp × Beladung × Spritpreise) | ⬜ | `src/services/AiTransportCostService.php` |
| 3 | AiTransportCostService: Tourkosten-Vorhersage (Maut, Sprit, Zeit, Verschleiß) | ⬜ | `src/services/AiTransportCostService.php` |
| 4 | AiTransportCostService: Fahrzeug-Empfehlung (optimales Fahrzeug pro Tour nach Kosten/Effizienz) | ⬜ | `src/services/AiTransportCostService.php` |
| 5 | AiTransportCostService: TCO-Analyse pro Fahrzeug (Sprit + Wartung + Versicherung + AfA) | ⬜ | `src/services/AiTransportCostService.php` |
| 6 | AiTransportCostService: Budget-Forecasting (Transport-Kosten nächster Monat/Quartal) | ⬜ | `src/services/AiTransportCostService.php` |
| 7 | AiTransportCostService: Anomalie-Erkennung (ungewöhnlich hohe Kosten pro Tour/Fahrzeug flaggen) | ⬜ | `src/services/AiTransportCostService.php` |
| 8 | API: 5 Endpunkte (transportCostPredict, vehicleRecommend, tcoAnalysis, transportBudget, transportAnomalies) | ⬜ | `src/api/ai/transportCost*.php` |
| 9 | UI: KI-Kosten-Widget in Transport-Dashboard | ⬜ | `src/transport/transport_index.twig` |
| 10 | Integration: Automatische Kostenschätzung bei Touren-Erstellung | ⬜ | `src/services/TransportLogisticsService.php` |
| 11 | Tests | 🧪 | Unit-Tests für AiTransportCostService |

---

## I12 – KI-Versicherungsanalyse

KI-gestützte Schadensrisiko-Bewertung, Deckungslücken-Analyse, Schadenmeldung-Assistent und Prämien-Optimierung.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | AiInsuranceService: Schadensrisiko-Bewertung pro Asset/Projekt (basierend auf Historie) | ⬜ | `src/services/AiInsuranceService.php` |
| 2 | AiInsuranceService: Deckungslücken-Analyse mit Empfehlungen | ⬜ | `src/services/AiInsuranceService.php` |
| 3 | AiInsuranceService: Schadenmeldung-Assistent (automatische Formulierung für Versicherung) | ⬜ | `src/services/AiInsuranceService.php` |
| 4 | AiInsuranceService: Prämien-Optimierung (Vergleich und Empfehlung) | ⬜ | `src/services/AiInsuranceService.php` |
| 5 | API: 3 Endpunkte (insuranceRisk, coverageAnalysis, claimAssist) | ⬜ | `src/api/ai/insurance*.php` |
| 6 | UI: Risiko-Badge auf Asset-Karten + Empfehlungs-Panel | ⬜ | `src/insurance/insurance_index.twig` |
| 7 | Tests | 🧪 | Unit-Tests für AiInsuranceService |

---

## I13 – KI-Bestandsoptimierung

KI-gestützte Nachfrage-Prognose, optimale Lagermengen, Nachbestellungs-Empfehlung und saisonale Muster-Erkennung.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | AiInventoryService: Nachfrage-Prognose (welche Assets werden wann gebraucht) | ⬜ | `src/services/AiInventoryService.php` |
| 2 | AiInventoryService: Optimale Lagermengen berechnen | ⬜ | `src/services/AiInventoryService.php` |
| 3 | AiInventoryService: Nachbestellungs-Empfehlung (Zeitpunkt + Menge) | ⬜ | `src/services/AiInventoryService.php` |
| 4 | AiInventoryService: Saisonale Muster-Erkennung aus historischen Buchungen | ⬜ | `src/services/AiInventoryService.php` |
| 5 | API: 3 Endpunkte (demandForecast, stockOptimize, reorderSuggest) | ⬜ | `src/api/ai/inventory*.php` |
| 6 | UI: Prognose-Widget im Inventar-Dashboard | ⬜ | `src/inventory/inventory_index.twig` |
| 7 | Tests | 🧪 | Unit-Tests für AiInventoryService |

---

## I14 – KI-Mahnwesen & Zahlungsprognose

KI-gestützte Zahlungswahrscheinlichkeit, optimale Mahnstufen-Empfehlung, Zahlungsdatum-Vorhersage und Inkasso-Scoring.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | AiDunningService: Zahlungswahrscheinlichkeit pro Kunde/Rechnung | ⬜ | `src/services/AiDunningService.php` |
| 2 | AiDunningService: Optimale Mahnstufe und Zeitpunkt-Empfehlung | ⬜ | `src/services/AiDunningService.php` |
| 3 | AiDunningService: Zahlungsdatum-Vorhersage | ⬜ | `src/services/AiDunningService.php` |
| 4 | AiDunningService: Inkasso-Erfolgswahrscheinlichkeit | ⬜ | `src/services/AiDunningService.php` |
| 5 | API: 2 Endpunkte (paymentPredict, dunningOptimize) | ⬜ | `src/api/ai/dunning*.php` |
| 6 | UI: Zahlungsprognose-Ampel in Mahnübersicht | ⬜ | `src/dunning/dunning_index.twig` |
| 7 | Tests | 🧪 | Unit-Tests für AiDunningService |

---

## I15 – KI-Reporting & Anomalie-Erkennung

KI-gestützte Anomalie-Erkennung in Finanzdaten, Trend-Vorhersage, natürlichsprachliche Report-Zusammenfassungen und KPI-Warnungen.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | AiReportingService: Automatische Anomalie-Erkennung in Finanzdaten | ⬜ | `src/services/AiReportingService.php` |
| 2 | AiReportingService: Trend-Vorhersage (Umsatz, Kosten, Auslastung) | ⬜ | `src/services/AiReportingService.php` |
| 3 | AiReportingService: Natürlichsprachliche Report-Zusammenfassung | ⬜ | `src/services/AiReportingService.php` |
| 4 | AiReportingService: KPI-Abweichungs-Warnung mit Ursachenanalyse | ⬜ | `src/services/AiReportingService.php` |
| 5 | API: 3 Endpunkte (detectAnomalies, forecastTrend, reportSummary) | ⬜ | `src/api/ai/reporting*.php` |
| 6 | UI: Anomalie-Alerts + Trend-Charts im Dashboard | ⬜ | `src/dashboard/index.twig` |
| 7 | Tests | 🧪 | Unit-Tests für AiReportingService |

---

## I16 – KI-Workflow-Optimierung

KI-gestützte Workflow-Vorschläge, Engpass-Erkennung und automatische Trigger-Optimierung.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | AiWorkflowService: Workflow-Vorschläge basierend auf Nutzungsmuster | ⬜ | `src/services/AiWorkflowService.php` |
| 2 | AiWorkflowService: Engpass-Erkennung in bestehenden Workflows | ⬜ | `src/services/AiWorkflowService.php` |
| 3 | AiWorkflowService: Automatische Trigger-Optimierung | ⬜ | `src/services/AiWorkflowService.php` |
| 4 | API: 2 Endpunkte (suggestWorkflow, analyzeBottleneck) | ⬜ | `src/api/ai/workflow*.php` |
| 5 | UI: Vorschlags-Panel im Workflow-Editor | ⬜ | `src/workflows/workflows_index.twig` |
| 6 | Tests | 🧪 | Unit-Tests für AiWorkflowService |

---

## I17 – KI-Packoptimierung

KI-gestützte Packsequenz-Optimierung, Gewichtsverteilung, Container-Zuordnung und Fehlteile-Vorhersage.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | AiPackingService: Optimale Packsequenz + Gewichtsverteilung | ⬜ | `src/services/AiPackingService.php` |
| 2 | AiPackingService: Container/Fahrzeug-Zuordnung (Tetris-Optimierung) | ⬜ | `src/services/AiPackingService.php` |
| 3 | AiPackingService: Fehlende-Teile-Vorhersage basierend auf historischen Packlisten | ⬜ | `src/services/AiPackingService.php` |
| 4 | API: 2 Endpunkte (optimizePacking, suggestContainer) | ⬜ | `src/api/ai/packing*.php` |
| 5 | UI: Optimierungs-Button in Packliste | ⬜ | `src/packing/packing_index.twig` |
| 6 | Tests | 🧪 | Unit-Tests für AiPackingService |

---

## L3 – Online-Buchungsportal

Öffentlicher Equipment-Katalog, Verfügbarkeitsprüfung, Warenkorb, Kunden-Registrierung.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 3 Tabellen (portal_config, inquiries, sessions) | ✅ | `db/migrations/20260318230000_booking_portal.php` |
| 2 | BookingPortalService: Katalog + Suche + Verfügbarkeit | ✅ | `src/services/BookingPortalService.php` |
| 3 | BookingPortalService: Anfrage-Management (submit, list, update) | ✅ | `src/services/BookingPortalService.php` |
| 4 | BookingPortalService: Kunden-Registrierung + Login + Sessions | ✅ | `src/services/BookingPortalService.php` |
| 5 | BookingPortalService: Projekt-Konvertierung | ✅ | `src/services/BookingPortalService.php` |
| 6 | Öffentliche API: 7 Endpunkte (kein Auth für Katalog) | ✅ | `src/api/portal/` |
| 7 | Admin API: 4 Endpunkte | ✅ | `src/api/portal/admin_*.php` |
| 8 | Portal UI: 7 Twig-Templates (eigenständiges CSS) | ✅ | `src/portal/public/*.twig` |
| 9 | Portal Router: index.php | ✅ | `src/portal/public/index.php` |
| 10 | Admin UI: portal_admin.twig | ✅ | `src/portal/portal_admin.twig` |
| 11 | Admin Controller: admin.php | ✅ | `src/portal/admin.php` |
| 12 | Stripe/PayPal Integration | ⬜ | Noch nicht angebunden |
| 13 | SEO-Optimierung (Meta-Tags, Sitemap) | ⬜ | Noch nicht implementiert |
| 14 | Tests | 🧪 | Unit-Tests für BookingPortalService |

---

## L4 – Backup & Disaster Recovery

Automatisierte DB-Backups, Verschlüsselung, Multi-Destination, Retention-Policy, Restore-Test.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 3 Tabellen (configs, jobs, restore_log) | ✅ | `db/migrations/20260318240000_backup_system.php` |
| 2 | BackupService: mysqldump mit Verschlüsselung (AES-256-CBC) | ✅ | `src/services/BackupService.php` |
| 3 | BackupService: Multi-Destination (Lokal, S3, SFTP) | ✅ | `src/services/BackupService.php` |
| 4 | BackupService: Retention-Policy (täglich/wöchentlich/monatlich) | ✅ | `src/services/BackupService.php` |
| 5 | BackupService: Restore + Integritätstest | ✅ | `src/services/BackupService.php` |
| 6 | BackupService: Health-Monitoring | ✅ | `src/services/BackupService.php` |
| 7 | API: 7 Endpunkte in /api/backup/ | ✅ | `src/api/backup/` |
| 8 | CRON-Script: backup_cron.php | ✅ | `scripts/backup_cron.php` |
| 9 | UI: backup_index.twig | ✅ | `src/backup/backup_index.twig` |
| 10 | Controller: index.php | ✅ | `src/backup/index.php` |
| 11 | Automatischer monatlicher Restore-Test | 🔧 | CRON muss konfiguriert werden |
| 12 | Tests | 🧪 | Unit-Tests für BackupService |

---

## L5 – Nachhaltigkeitsreporting & ESG

CO₂-Tracking pro Transport, Energieverbrauch pro Projekt, Kunden-Reports, EU-CSRD.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 4 Tabellen (config, transport_log, energy_log, reports) | ✅ | `db/migrations/20260318250000_sustainability_reporting.php` |
| 2 | SustainabilityService: CO₂-Berechnung (Fahrzeugtyp × km × Faktor) | ✅ | `src/services/SustainabilityService.php` |
| 3 | SustainabilityService: Energieverbrauch pro Projekt | ✅ | `src/services/SustainabilityService.php` |
| 4 | SustainabilityService: 12-Monats-Trend + YoY-Vergleich | ✅ | `src/services/SustainabilityService.php` |
| 5 | SustainabilityService: Kunden-Reports generieren | ✅ | `src/services/SustainabilityService.php` |
| 6 | API: 9 Endpunkte in /api/sustainability/ | ✅ | `src/api/sustainability/` |
| 7 | UI: sustainability_index.twig (5-Tab Dashboard, Chart.js) | ✅ | `src/sustainability/sustainability_index.twig` |
| 8 | Controller: index.php | ✅ | `src/sustainability/index.php` |
| 9 | Auto-Logging: Hook in Transport-Modul | 🔧 | Example vorhanden, muss integriert werden |
| 10 | Auto-Logging: Hook in Projekt-Modul | 🔧 | Example vorhanden, muss integriert werden |
| 11 | Tests | 🧪 | Unit-Tests für SustainabilityService |

---

## A1 – Interne Artikelnummern & QR-Labels

Automatisch generierte, softwareinterne Artikelnummern pro Asset. Freies Nummernformat mit konfigurierbaren Platzhaltern (`{PREFIX}-{KATEGORIE}-{NR}`). Die Artikelnummer ist der zentrale Identifier für die Gossen Metrawatt IZYTRON.IQ Synchronisation: gleiche Nummer in RMS und Messgerät → Prüfprotokolle werden nach E-Check automatisch per Artikelnummer zugeordnet. QR-Labels mit Artikelnummer für Geräte-Aufkleber druckbar.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: `assets_articleNumber` Spalte (VARCHAR 20, unique pro Instanz), Instanz-Einstellungen (Prefix, Padding, Kategoriekürzel, Auto-Generate), Sequence-Eintrag in `document_sequences` | ⬜ | `db/migrations/20260320100000_article_numbers.php` |
| 2 | ArticleNumberService: Nächste Nummer generieren (atomisch via SequenceService, Row-Level-Locking) | ⬜ | `src/services/ArticleNumberService.php` |
| 3 | ArticleNumberService: Konfigurierbares Format-System ({PREFIX}-{KATEGORIE}-{NR}, frei definierbar pro Instanz) | ⬜ | `src/services/ArticleNumberService.php` |
| 4 | ArticleNumberService: Validierung (max 20 Zeichen, alphanumerisch+Bindestrich, Uniqueness pro Instanz, IZYTRON.IQ-kompatibel) | ⬜ | `src/services/ArticleNumberService.php` |
| 5 | ArticleNumberService: Manuelles Überschreiben (Admin kann Artikelnummer ändern, Uniqueness-Check) | ⬜ | `src/services/ArticleNumberService.php` |
| 6 | ArticleNumberService: Backfill (alle bestehenden Assets ohne Artikelnummer bekommen automatisch eine zugewiesen) | ⬜ | `src/services/ArticleNumberService.php` |
| 7 | ArticleNumberService: Lookup (Asset finden per Artikelnummer, Bulk-Lookup für Import-Matching) | ⬜ | `src/services/ArticleNumberService.php` |
| 8 | Integration: Asset-Erstellung (auto-generate bei `newAssetFromType.php`, Allowlist erweitern) | ⬜ | `src/api/assets/newAssetFromType.php` |
| 9 | Integration: Asset-Bearbeitung (Allowlist in `editAsset.php` erweitern, Validierung) | ⬜ | `src/api/assets/editAsset.php` |
| 10 | Integration: BarcodeScannerService (Artikelnummer als Lookup-Pfad beim Scannen) | ⬜ | `src/services/BarcodeScannerService.php` |
| 11 | QR-Label: Artikelnummer als Plain-Text im QR-Code (nicht RMS://-Format, für Metrawatt-Kompatibilität) | ⬜ | `src/services/QrCodeGenerator.php` |
| 12 | QR-Label: ZPL-Generation mit Artikelnummer + Code128-Barcode für Zebra-Drucker | ⬜ | `src/api/rfid/label.php` |
| 13 | QR-Label: Druckbares Label-Template (Artikelnummer prominent + Asset-Typ + QR-Code) | ⬜ | `src/api/rfid/label.php` |
| 14 | Metrawatt-Export: CSV-Export aller Assets mit Artikelnummer im IZYTRON.IQ-Import-Format | ⬜ | `src/api/assets/metrawatt_export.php` |
| 15 | Metrawatt-Import: IZYTRON.IQ Prüfprotokolle importieren, Matching per Artikelnummer → Asset | ⬜ | `src/services/MetrawattImportService.php` |
| 16 | API: Einstellungen lesen/schreiben (Prefix, Padding, Kategorie-Kürzel, Auto-Generate) | ⬜ | `src/api/assets/articleNumber/settings.php` |
| 17 | API: Backfill auslösen + Lookup-Endpunkt | ⬜ | `src/api/assets/articleNumber/backfill.php` |
| 18 | UI: Asset-Detail – Artikelnummer anzeigen/bearbeiten | ⬜ | `src/asset.twig` |
| 19 | UI: Asset-Liste – Artikelnummer als Spalte | ⬜ | `src/assets.twig` |
| 20 | UI: Instanz-Einstellungen – Artikelnummer-Format konfigurieren + Backfill-Button | ⬜ | `src/instances/configuration/` |
| 21 | UI: Metrawatt-Export/Import-Bereich im Maintenance-Dashboard | ⬜ | `src/maintenance/maintenance_dashboard.twig` |
| 22 | Integration: CSV/XLSX-Export um Artikelnummer erweitern | ⬜ | `src/api/assets/export.php` |
| 23 | Tests | 🧪 | Unit-Tests für ArticleNumberService |

---

## E1 – Elektro-Check / DGUV V3 Prüfdokumentation

DGUV V3 / VDE 0701-0702 / DIN EN 50699 konforme Prüfdokumentation für ortsveränderliche elektrische Betriebsmittel. RFID-Scan startet Prüf-Workflow, Messwerte werden dokumentiert, Prüfprotokoll-PDF wird generiert. Nicht bestandene Geräte werden automatisch gesperrt. Messgeräte-Integration: Gossen Metrawatt SECUTEST (IZYTRON.IQ Format) als primäres Import-Format, generischer CSV-Import für andere Hersteller (Benning, Fluke, Beha-Amprobe), optionale Bluetooth-Kopplung mit Android-App.

| # | Baustein | Status | Datei |
|---|----------|--------|-------|
| 1 | DB-Migration: 3 Tabellen (echeck_protocols, echeck_tester_qualifications, echeck_measurement_devices) | ⬜ | `db/migrations/YYYYMMDD_echeck_dguv_v3.php` |
| 2 | ECheckService: Prüfung erfassen per RFID-Scan (Asset scannen → Prüfformular öffnen) | ⬜ | `src/services/ECheckService.php` |
| 3 | ECheckService: Messwerte dokumentieren (Schutzleiterwiderstand ≤0,3Ω, Isolationswiderstand ≥1MΩ, Ableitstrom, Berührungsstrom, Differenzstrom) | ⬜ | `src/services/ECheckService.php` |
| 4 | ECheckService: Prüfergebnis (bestanden/nicht bestanden/bedingt bestanden) mit automatischer Grenzwert-Validierung nach VDE 0701-0702 | ⬜ | `src/services/ECheckService.php` |
| 5 | ECheckService: Prüfplakette-Daten (nächster Prüftermin, Prüfer-ID, Prüfgeräte-Nr.) | ⬜ | `src/services/ECheckService.php` |
| 6 | ECheckService: Automatische Prüfintervall-Berechnung (6/12/24 Monate nach Schutzklasse + Beanspruchung) | ⬜ | `src/services/ECheckService.php` |
| 7 | ECheckService: Gerätesperre bei Nicht-Bestehen (Asset-Status → "gesperrt", nicht verleihbar bis Nachprüfung) | ⬜ | `src/services/ECheckService.php` |
| 8 | ECheckService: Prüfprotokoll-PDF (VDE 0701-0702 / DIN EN 50699 konform, Dompdf) | ⬜ | `src/services/ECheckService.php` |
| 9 | ECheckService: Prüfer-Qualifikations-Verwaltung (EFK/EuP, Gültigkeit, Nachweis-Upload) | ⬜ | `src/services/ECheckService.php` |
| 10 | ECheckService: Prüfgeräte-Verwaltung (Seriennr., Kalibrierungsstatus, nächste Kalibrierung) | ⬜ | `src/services/ECheckService.php` |
| 11 | ECheckService: Prüfhistorie pro Asset (alle Prüfungen chronologisch mit Messwerten) | ⬜ | `src/services/ECheckService.php` |
| 12 | ECheckService: Dashboard (überfällige Prüfungen, ablaufende Qualifikationen, Prüfstatistik) | ⬜ | `src/services/ECheckService.php` |
| 13 | API: 8+ Endpunkte (startECheck, recordResults, getProtocol, generatePdf, testerQualifications, measurementDevices, dashboard, overdue) | ⬜ | `src/api/echeck/` |
| 14 | UI: E-Check Dashboard mit Ampelsystem (grün=bestanden, gelb=bald fällig, rot=überfällig/gesperrt) | ⬜ | `src/echeck/echeck_dashboard.twig` |
| 15 | UI: Prüfformular mit Messwert-Eingabe + Grenzwert-Anzeige + Sofort-Validierung | ⬜ | `src/echeck/echeck_form.twig` |
| 16 | Android-App: RFID scannen → E-Check starten (neuer Screen im Chafon-App) | ⬜ | `android-app/` |
| 17 | Android-App: Manuelle Messwert-Eingabe im E-Check-Formular (Fallback ohne Geräte-Kopplung) | ⬜ | `android-app/` |
| 18 | Messgeräte-Integration: Gossen Metrawatt IZYTRON.IQ Format als primäres Import-Format (SECUTEST BASE10/PRO/ST) | ⬜ | `src/services/ECheckImportService.php` |
| 19 | Messgeräte-Integration: CSV/XML-Import in Web-UI (Datei hochladen → automatische Zuordnung per Seriennummer/Asset-ID) | ⬜ | `src/api/echeck/import.php` |
| 20 | Messgeräte-Integration: Generischer CSV-Import für andere Hersteller (Benning, Fluke, Beha-Amprobe) | ⬜ | `src/services/ECheckImportService.php` |
| 21 | Messgeräte-Integration: Bluetooth-Kopplung Android-App ↔ Messgerät (optional, für SECUTEST PRO/ST mit Bluetooth) | ⬜ | `android-app/` |
| 22 | Integration: Wartungssystem (MaintenanceService als Basis für Prüf-Scheduling) | ⬜ | `src/services/MaintenanceService.php` |
| 23 | Integration: Workflow-Engine (automatische Erinnerung bei fälligen Prüfungen) | ⬜ | `src/services/WorkflowEngineService.php` |
| 24 | Integration: RFID-System (Scan-Aktion "echeck" im UniversalScanHandler) | ⬜ | `src/services/RfidService.php` |
| 25 | KI-Erweiterung (I18): Prüfergebnisse analysieren, Ausfallrisiko vorhersagen, Prüfintervall-Empfehlung | ⬜ | `src/services/AiECheckService.php` |
| 26 | Tests | 🧪 | Unit-Tests für ECheckService |

---

## Zusammenfassung

| Status | Anzahl |
|--------|--------|
| ✅ Implementiert | 162 |
| 🔧 Braucht Integration/Review | 18 |
| ⬜ Noch nicht implementiert | 95 |
| 🧪 Braucht Tests | 24 |

**Migrationen:** 19 neue Phinx-Migrationen (68+ neue DB-Tabellen)
**Services:** 26 neue PHP-Services + 6 AI-Adapter (davon 7 KI-Services I11-I17, 1 ECheckService, 1 AiECheckService, 1 ArticleNumberService, 1 MetrawattImportService)
**API-Endpunkte:** 172+ neue Endpunkte
**Twig-Templates:** 25 neue Templates
**CRON-Scripts:** 1 (backup_cron.php)
