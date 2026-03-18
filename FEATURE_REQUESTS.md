# MyRMS – Feature Requests

**Zweck:** Hier schreibt der Nutzer neue Feature-Ideen rein. Die MyRMS-KI (AiRequestHandler + FeedbackLearningService) liest diese Datei automatisch, formuliert Ideen aus und aktualisiert den Status.

## Wie es funktioniert

1. **Nutzer** schreibt eine Idee rein – reicht ein Satz, z.B. "Kunden sollen Rechnungen online bezahlen können"
2. **MyRMS-KI** liest diese Datei im Rahmen des Lernsystems (I10), erkennt neue IDEE-Einträge und formuliert sie vollständig aus (Was/Warum/Wie/DB/API/UI)
3. **MyRMS-KI** setzt Status auf `AUSFORMULIERT` und schreibt die Ausformulierung in die Datei zurück
4. **Nutzer** prüft und gibt frei → setzt Status auf `FREIGEGEBEN`
5. **Entwicklung** findet statt (manuell oder per Coding-Agent) → Status `IN ARBEIT` → `ERLEDIGT`
6. Erledigte Features werden nach unten verschoben

## Technische Integration in MyRMS

Die KI greift auf diese Datei über den `FeatureRequestService` zu:
- **Lesen:** `FeatureRequestService::getPendingRequests()` – parst die MD-Datei und gibt offene Requests zurück
- **Schreiben:** `FeatureRequestService::updateRequest($id, $data)` – aktualisiert Status und Ausformulierung
- **Trigger:** Nach jedem KI-Task prüft das System ob neue IDEEs vorhanden sind (Hintergrund, nicht blockierend)
- **Speicherort:** Diese Datei liegt im Projekt-Root und wird von der KI gelesen/geschrieben. Alternativ können Feature Requests auch über die Settings-UI (Einstellungen → KI → Feature Requests) eingegeben werden, dann werden sie in der DB gespeichert (`ai_feature_requests`-Tabelle) und diese Datei wird automatisch synchronisiert.

---

## Format

```
### FR-XXX: [Kurzer Name]
**Status:** IDEE | AUSFORMULIERT | FREIGEGEBEN | IN ARBEIT | ERLEDIGT
**Erstellt:** [Datum]

**Nutzer-Idee:** [Was der Nutzer geschrieben hat – unverändert]

**KI-Ausformulierung:** (wird automatisch von der MyRMS-KI ausgefüllt)
- Was: [2-3 Sätze]
- Ablauf: [Schritt für Schritt]
- DB: [Tabellen/Spalten]
- Service: [Methoden]
- API: [Endpunkte]
- UI: [Oberfläche]
- Erledigt wenn: [Kriterien]

**Commit:** [wird nach Implementierung eingetragen]
```

---

## Offene Feature Requests



---

## Abgeschlossene Feature Requests

