# MyRMS – Feature Requests

**Zweck:** Lebendes Dokument für neue Feature-Ideen. Der Nutzer schreibt seine Idee rein (auch ganz kurz und formlos), die KI formuliert sie dann vollständig als Entwicklungsauftrag aus.

**Workflow:**
1. **Nutzer:** Schreibt eine neue Idee rein – reicht ein Satz, z.B. "Kunden sollen Rechnungen online bezahlen können"
2. **KI:** Liest diese Datei am Anfang jeder Session, formuliert offene Ideen vollständig aus (Was/Warum/Wie/DB/API/UI) und setzt Status auf `AUSFORMULIERT`
3. **Nutzer:** Prüft und gibt frei → Status `FREIGEGEBEN`
4. **KI:** Implementiert das Feature, aktualisiert Status auf `IN ARBEIT`, dann `ERLEDIGT` mit Commit-Hash
5. **KI:** Verschiebt erledigte Features nach unten in den "Abgeschlossen"-Bereich
6. **KI:** Aktualisiert gleichzeitig die IMPLEMENTATION_CHECKLIST.md mit den neuen Bausteinen

**Regeln für die KI:**
- Am Anfang jeder Coding-Session: Diese Datei UND IMPLEMENTATION_CHECKLIST.md lesen
- Neue Nutzer-Ideen (Status: IDEE) vollständig ausformulieren
- Nach jedem implementierten Feature: Status hier UND in der Checklist aktualisieren
- Erledigte Features nach unten verschieben, nicht löschen
- FR-Nummern fortlaufend vergeben (FR-001, FR-002, ...)

---

## Template (KI füllt das aus, Nutzer muss nur die Idee beschreiben)

```
### FR-XXX: [Kurzer Feature-Name]

**Status:** IDEE | AUSFORMULIERT | FREIGEGEBEN | IN ARBEIT | ERLEDIGT
**Priorität:** HOCH | MITTEL | NIEDRIG
**Geschätzte Größe:** S (1-2h) | M (halber Tag) | L (1-2 Tage) | XL (3+ Tage)
**Erstellt:** [Datum]
**Erledigt:** [Datum] (wird von KI ausgefüllt)

**Nutzer-Idee (Original):**
[Hier steht was der Nutzer gesagt/geschrieben hat, unverändert]

**Ausformulierung (von KI):**

Was soll es tun?
[2-3 Sätze]

Wie soll es funktionieren?
[Schritt-für-Schritt Ablauf]

Datenbank:
[Neue Tabellen/Spalten, oder "keine Änderung"]

Service:
[Neuer oder erweiterter Service, wichtige Methoden]

API-Endpunkte:
[Neue Endpunkte mit HTTP-Methode und Pfad]

UI/Oberfläche:
[Wo in der Navigation, welche Elemente, wie sieht es aus]

Abhängigkeiten:
[Andere Module die betroffen sind]

Erledigt-Kriterien:
[Wann ist es fertig? Was muss funktionieren?]

**Commit/Dateien:** (von KI nach Implementierung)
[Commit-Hash, neue/geänderte Dateien]
```

---

## Offene Feature Requests

*(Einfach eine neue Idee reinschreiben – die KI formuliert sie aus)*



---

## Abgeschlossene Feature Requests

*(Erledigte Features werden automatisch hierher verschoben)*
