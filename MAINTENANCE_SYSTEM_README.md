# Wartungs- & Predictive Maintenance System (J2)

## Overview

Das Wartungs- & Predictive Maintenance System ist eine umfassende Lösung für die Verwaltung von Equipment-Wartung im MyRMS. Das System bietet:

- **Wartungspläne (Schedules)**: Automatisierte Wartungen für Assets oder Asset-Typen
- **Wartungsaufträge (Jobs)**: Strukturierte Verwaltung individueller Wartungsarbeiten
- **Checklisten**: Standardisierte Checklisten für konsistente Wartungsprozesse
- **Fotodokumentation**: Beweismaterial und visuelle Dokumentation
- **Kostenüberwachung**: Tracking von geschätzten vs. tatsächlichen Kosten
- **Dashboard & Statistiken**: Überblick über Wartungsstatus und Vorhersagen
- **Automatische Job-Erstellung**: CRON-basierte Job-Erstellung aus Zeitplänen

## Database Schema

### Migration: `20260318130000_maintenance_system.php`

Erstellt 5 neue Tabellen:

#### 1. `maintenance_schedules`
Zeitplanvorlage für wiederholte Wartungen.

Felder:
- `id`: Primary Key
- `asset_type_id`: Foreign Key zu `assetTypes` (nullable, für Typ-basierte Wartung)
- `asset_id`: Foreign Key zu `assets` (nullable, für einzelne Assets)
- `name`: Name der Wartung (z.B. "Ölwechsel", "TÜV")
- `description`: Detaillierte Beschreibung
- `interval_type`: ENUM('days', 'months', 'uses', 'hours')
- `interval_value`: Wert des Intervalls
- `last_performed_at`: Zeitstempel der letzten Wartung
- `next_due_at`: Nächster Fälligkeitszeitpunkt
- `instances_id`: Tenant-ID
- `is_active`: Boolean (aktiv/inaktiv)
- `created_at`, `updated_at`: Zeitstempel

Indizes:
- `idx_schedule_asset_type_id`, `idx_schedule_asset_id`, `idx_schedule_instances_id`, `idx_schedule_next_due_at`

#### 2. `maintenance_jobs`
Einzelne Wartungsaufträge mit vollständiger Struktur.

Felder:
- `id`: Primary Key
- `schedule_id`: FK zu `maintenance_schedules` (nullable, für automatische Jobs)
- `asset_id`: FK zu `assets`
- `title`: Titel des Auftrags
- `description`: Beschreibung
- `status`: ENUM('scheduled', 'in_progress', 'completed', 'cancelled')
- `assigned_to`: FK zu `users` (nullable, zugewiesener Techniker)
- `priority`: ENUM('low', 'medium', 'high', 'critical')
- `estimated_cost`: Geschätzte Kosten (DECIMAL 10,2)
- `actual_cost`: Tatsächliche Kosten (nullable)
- `started_at`, `completed_at`: Zeitstempel
- `completed_by`: FK zu `users` (wer hat abgeschlossen)
- `notes`: Notizen/Erkenntnisse
- `instances_id`: Tenant-ID
- `created_at`, `updated_at`: Zeitstempel

Indizes:
- `idx_job_schedule_id`, `idx_job_asset_id`, `idx_job_assigned_to`, `idx_job_status`, `idx_job_instances_id`, `idx_job_completed_at`

#### 3. `maintenance_job_photos`
Fotos zu Wartungsaufträgen.

Felder:
- `id`: Primary Key
- `job_id`: FK zu `maintenance_jobs`
- `file_path`: S3-Pfad oder lokaler Pfad
- `description`: Foto-Beschreibung (optional)
- `uploaded_by`: FK zu `users`
- `created_at`: Zeitstempel

#### 4. `maintenance_checklists`
Checklisten-Templates für standardisierte Wartungsprozesse.

Felder:
- `id`: Primary Key
- `name`: Name der Checkliste (z.B. "Monatliche Inspektion")
- `asset_type_id`: FK zu `assetTypes` (nullable, für Typ-spezifische Listen)
- `instances_id`: Tenant-ID
- `items`: JSON Array mit Checklistenpunkten
- `created_at`: Zeitstempel

JSON-Format für `items`:
```json
[
  {"item": "Visuelles Inspizieren", "required": true},
  {"item": "Funktionsprüfung", "required": true},
  {"item": "Öl überprüfen", "required": false}
]
```

#### 5. `maintenance_checklist_results`
Ausgefüllte Checklisten für Jobs.

Felder:
- `id`: Primary Key
- `job_id`: FK zu `maintenance_jobs`
- `checklist_id`: FK zu `maintenance_checklists`
- `results`: JSON mit Ergebnissen
- `completed_by`: FK zu `users`
- `completed_at`: Zeitstempel

JSON-Format für `results`:
```json
[
  {"item": "Visuelles Inspizieren", "checked": true, "notes": "OK"},
  {"item": "Funktionsprüfung", "checked": true, "notes": "Alle Parameter im Norm"}
]
```

## Service: MaintenanceService

Pfad: `/src/services/MaintenanceService.php`

### Öffentliche Methoden

#### Schedules

```php
getSchedulesForAsset(int $assetId, int $instanceId): array
```
Ruft alle aktiven Wartungspläne für ein einzelnes Asset ab.

```php
getSchedulesForAssetType(int $assetTypeId, int $instanceId): array
```
Ruft Wartungspläne ab, die für einen Asset-Typ gelten.

```php
createSchedule(array $data): int
```
Erstellt einen neuen Wartungsplan. `$data` sollte enthalten:
- `name` (erforderlich)
- `asset_type_id` oder `asset_id` (mindestens eines)
- `interval_type` (days, months, uses, hours)
- `interval_value` (int)
- `description` (optional)
- `instances_id` (erforderlich)

```php
updateSchedule(int $id, array $data): bool
```
Aktualisiert einen Wartungsplan.

```php
deleteSchedule(int $id): bool
```
Löscht einen Wartungsplan (Soft-Delete nicht implementiert).

#### Jobs

```php
createJob(array $data): int
```
Erstellt einen neuen Wartungsauftrag. `$data`:
- `asset_id` (erforderlich)
- `title` (erforderlich)
- `schedule_id` (optional, für verknüpfte Jobs)
- `description`, `assigned_to`, `priority`, `estimated_cost` (optional)
- `instances_id` (erforderlich)

```php
updateJobStatus(int $jobId, string $status, ?int $userId = null): bool
```
Aktualisiert Status eines Jobs. Setzt automatisch `started_at` (bei 'in_progress') und `completed_at` (bei 'completed').

```php
getJob(int $jobId): ?array
```
Ruft einen Job mit allen Details ab (inklusive Fotos und Checklisten-Ergebnisse).

```php
getJobsForAsset(int $assetId, int $instanceId): array
```
Service-Geschichte: Alle Jobs für ein Asset (absteigend nach Datum).

```php
getOpenJobs(int $instanceId, ?string $priority = null): array
```
Ruft alle offenen (nicht abgeschlossenen, nicht stornierten) Jobs ab. Optional nach Priorität filterbar.

#### Schedules Überschuss/Defizit

```php
getOverdueMaintenances(int $instanceId): array
```
Alle überfälligen (next_due_at <= jetzt) Wartungspläne.

```php
getUpcomingMaintenances(int $instanceId, int $daysAhead = 30): array
```
Wartungen, die in den nächsten X Tagen fällig sind.

#### Checklisten

```php
getChecklists(int $instanceId): array
```
Ruft alle Checklisten-Templates ab.

```php
createChecklist(array $data): int
```
Erstellt Checklisten-Template. `$data`:
- `name` (erforderlich)
- `asset_type_id` (optional)
- `items` (Array oder JSON-String)
- `instances_id` (erforderlich)

```php
completeChecklist(int $jobId, int $checklistId, array $results, int $userId): int
```
Speichert ausgefüllte Checkliste für einen Job.

#### Fotos

```php
addJobPhoto(int $jobId, string $filePath, ?string $description, int $userId): int
```
Fügt Foto zu Wartungsauftrag hinzu.

#### Kosten & Statistiken

```php
getMaintenanceCostsByAsset(int $assetId, int $instanceId): array
```
Ruft Kostenübersicht für ein Asset ab:
```php
[
    'jobs' => [...],
    'total_estimated' => 1250.00,
    'total_actual' => 1180.50
]
```

```php
checkAndCreateScheduledJobs(int $instanceId): int
```
**CRON-Funktion**: Prüft alle überfälligen Schedules und erstellt automatisch Jobs (falls nicht bereits offen). Gibt Anzahl erstellter Jobs zurück.

```php
getMaintenanceStatus(int $assetId): string
```
Gibt Status für UI-Badge zurück: `'ok'` | `'due_soon'` | `'overdue'`

```php
getDashboardStats(int $instanceId): array
```
Ruft Dashboard-Statistiken ab:
```php
[
    'overdue_count' => 3,
    'upcoming_count' => 12,
    'open_jobs_count' => 8,
    'cost_this_month' => 2450.75
]
```

## API Endpoints

Alle Endpunkte befinden sich in `/src/api/maintenance/`

### Authentifizierung

Alle Endpunkte erfordern: `require_once __DIR__ . '/../apiHeadSecure.php'`

### Berechtigungen

- `MAINTENANCE:VIEW` - Lesen
- `MAINTENANCE:CREATE` - Erstellen
- `MAINTENANCE:EDIT` - Bearbeiten

### Endpunkte

#### Schedules

**GET `/api/maintenance/schedules.php`**
- Query-Parameter: `asset_id`, `asset_type_id`
- Antwort: `{'success': true, 'schedules': [...]}`

**POST `/api/maintenance/schedules.php`**
- JSON Body: Alle Schedule-Felder
- Benötigt: `MAINTENANCE:CREATE`
- Antwort: `{'success': true, 'schedule_id': 123}`

**POST `/api/maintenance/schedule_edit.php`**
- JSON Body: `{id, ...fields to update}`
- Benötigt: `MAINTENANCE:EDIT`

**POST `/api/maintenance/schedule_delete.php`**
- JSON Body: `{id}`
- Benötigt: `MAINTENANCE:EDIT`

#### Jobs

**GET `/api/maintenance/jobs.php`**
- Query-Parameter: `status`, `priority`, `asset_id`
- Antwort: `{'success': true, 'jobs': [...]}`

**POST `/api/maintenance/job_create.php`**
- JSON Body: Job-Felder (asset_id, title erforderlich)
- Benötigt: `MAINTENANCE:CREATE`
- Antwort: `{'success': true, 'job_id': 456}`

**POST `/api/maintenance/job_update.php`**
- JSON Body: `{id, status, assigned_to, priority, ...}`
- Benötigt: `MAINTENANCE:EDIT`

**POST `/api/maintenance/job_photo.php`**
- Multipart Form Data: `job_id`, `photo` (file), `description` (optional)
- Benötigt: `MAINTENANCE:EDIT`
- Antwort: `{'success': true, 'photo_id': 789}`

#### Checklisten

**GET `/api/maintenance/checklists.php`**
- Antwort: `{'success': true, 'checklists': [...]}`

**POST `/api/maintenance/checklists.php`**
- JSON Body: `{name, asset_type_id, items}`
- Benötigt: `MAINTENANCE:CREATE`

**POST `/api/maintenance/checklist_complete.php`**
- JSON Body: `{job_id, checklist_id, results: [...]}`
- Benötigt: `MAINTENANCE:EDIT`

#### Dashboard & Reporting

**GET `/api/maintenance/overdue.php`**
- Antwort: `{'success': true, 'count': 3, 'maintenances': [...]}`

**GET `/api/maintenance/dashboard.php`**
- Antwort: `{'success': true, 'stats': {overdue_count, upcoming_count, ...}}`

**GET `/api/maintenance/asset_history.php`**
- Query-Parameter: `asset_id` (erforderlich)
- Antwort: `{'success': true, 'jobs': [...], 'costs': {...}}`

## Twig Templates

### `/src/maintenance/maintenance_dashboard.twig`
Neues Dashboard mit:
- 4 Statistik-Karten (Überblick)
- Tabs für: Offene Aufträge | Wartungspläne | Checklisten | Kostenübersicht
- Filter und Such-Funktionalität
- AJAX-basierte Daten-Aktualisierung

### `/src/maintenance/maintenance_index.twig`
Bestand: Legacy-View für bestehende maintenanceJobs-Tabelle

## Controller

### `/src/maintenance/index.php`
**Routing**:
- Standard: Zeigt `maintenance_dashboard.twig`
- Query `?view=legacy`: Zeigt `maintenance_index.twig` (Legacy-View)

**Berechtigungen**:
- Unterstützt sowohl `MAINTENANCE:VIEW` als auch `MAINTENANCE_JOBS:VIEW`

## Integration mit bestehenden Systemen

### EquipmentLifecycleService
Wartungsmeldungen können mit Lifecycle-Status verknüpft werden:
- Asset in Status `maintenance` oder `repair` kann Wartungsaufträge haben

### DamageReportService
Schadensmeldungen können automatisch Wartungsaufträge erstellen (bestehende Funktionalität bleibt)

### Asset Management
- Assets können Wartungspläne (individuell oder als Typ) haben
- Service-History wird in Wartungsaufträgen dokumentiert

## Berechtigungen definieren

In der Admin-Berechtigungskonfiguration müssen diese Rollen erstellt werden:

```
MAINTENANCE:VIEW      - Wartungen anzeigen
MAINTENANCE:CREATE    - Neue Wartungen/Pläne erstellen
MAINTENANCE:EDIT      - Wartungen bearbeiten/aktualisieren
```

Diese können als Instanz-Berechtigungen konfiguriert werden.

## CRON / Automatische Jobs

Zur automatischen Job-Erstellung aus Zeitplänen, erstellen Sie einen CRON-Job:

```php
<?php
require_once 'vendor/autoload.php';
$db = new MysqliDb(...);
$maintenanceService = new MaintenanceService($db);

// Für jede Instanz
$instances = $db->get('instances');
foreach ($instances as $instance) {
    $created = $maintenanceService->checkAndCreateScheduledJobs($instance['instances_id']);
    echo "Instanz {$instance['instances_id']}: {$created} Jobs erstellt\n";
}
?>
```

Empfohlene Häufigkeit: **Täglich um 06:00 Uhr**

## Beispiel-Workflows

### Workflow 1: Automatische Ölwechsel-Wartung

1. Admin erstellt Schedule: "Ölwechsel alle 6 Monate" für Asset-Typ "Motor"
2. System setzt `next_due_at` automatisch
3. CRON läuft täglich und erstellt Jobs für fällige Schedules
4. Techniker sieht Job in Dashboard
5. Techniker startet Job, führt Checkliste durch, macht Fotos
6. Job wird als 'completed' markiert mit tatsächlichen Kosten
7. System berechnet nächsten `next_due_at`

### Workflow 2: Ad-hoc Reparatur

1. Techniker meldet Schaden via Asset-Interface
2. System erstellt automatisch Maintenance Job
3. (Existierende Funktionalität, integriert mit neuem System)

### Workflow 3: Kostenanalyse

1. Manager öffnet Dashboard → "Kostenübersicht" Tab
2. Sieht Summen: Geschätzt vs. Tatsächlich
3. Kann pro Asset oder Zeitraum filtern
4. Kann Trends identifizieren (Assets mit hohen Kosten)

## Fehlerbehebung

### Schedule erstellt Jobs nicht automatisch

1. Prüfen Sie, dass CRON konfiguriert ist
2. Prüfen Sie `next_due_at` - sollte <= jetzt sein
3. Prüfen Sie `is_active` - sollte true sein
4. Prüfen Sie `instances_id` Match

### Fotos werden nicht gespeichert

1. Standardmäßig wird nur Dateipfad gespeichert
2. Für S3-Integration: Implementieren Sie `$bCMS->s3Upload()` in `job_photo.php`
3. Prüfen Sie Dateirechte und S3-Konfiguration

### Kosten stimmen nicht

1. Prüfen Sie, dass `actual_cost` gespeichert wurde
2. Kosten-Summen nur für Status 'completed'
3. NULL-Werte werden als 0 behandelt

## Zukünftige Erweiterungen

- Benachrichtigungen bei überfälligen Wartungen
- Wartungs-Historie exportieren (PDF/Excel)
- Predictive Maintenance basierend auf Nutzungsdaten
- Mobile App für Feldmitarbeiter
- Integration mit IoT-Sensoren
- Automatische Teile-Bestellungen

## Testing

Siehe `/tests/MaintenanceServiceTest.php` (falls vorhanden)

Manuelle Tests:
1. Erstellen Sie einen Wartungsplan
2. Laufen Sie `checkAndCreateScheduledJobs`
3. Prüfen Sie, dass Job erstellt wurde
4. Aktualisieren Sie Job-Status
5. Prüfen Sie Kosten-Summen

## Unterstützung

Fragen oder Probleme: Siehe Dokumentation oder Admin
