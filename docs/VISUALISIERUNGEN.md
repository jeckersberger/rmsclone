# AdamRMS - Programmvisualisierungen

## 1. Gesamtübersicht - Seitenstruktur

```
┌──────────────────────────────────────────────────────────────────────┐
│  NAVBAR                                        [🔍 Live-Suche...]   │
│  AdamRMS    Instanz: Mein Unternehmen ▼    👤 Max Mustermann ▼     │
├──────────┬───────────────────────────────────────────────────────────┤
│ SIDEBAR  │                                                           │
│          │  HAUPTBEREICH (Content)                                    │
│ 📊 Start │                                                           │
│          │  ┌─────────────────────────────────────────────────────┐  │
│ 📁 Proj. │  │  Hier wird der jeweilige Seiteninhalt angezeigt    │  │
│  ├ Neu   │  │                                                     │  │
│  └ Liste │  │  (Dashboard, Projekt, Kunden, Equipment, etc.)      │  │
│          │  │                                                     │  │
│ 📦 Equip.│  └─────────────────────────────────────────────────────┘  │
│          │                                                           │
│ 📅 Verfüg│                                                           │
│          │                                                           │
│ 📍 Orte  │                                                           │
│ 🏭 Herst.│                                                           │
│ 👥 Kunden│                                                           │
│ 💰 Zahlung│                                                          │
│          │                                                           │
│ ⚠ Mahnung│                                                           │
│ 📈 Berich│                                                           │
│ 📤 Export│                                                           │
│          │                                                           │
│ ⚙ Einst. │                                                           │
│  ├ Basis │                                                           │
│  ├ Nutzer│                                                           │
│  ├ Rechte│                                                           │
│  ├ Proj. │                                                           │
│  ├ Equip.│                                                           │
│  └ DE    │                                                           │
│          │                                                           │
│ 🔧 Wartu.│                                                           │
│ 📄 CMS   │                                                           │
└──────────┴───────────────────────────────────────────────────────────┘
```

## 2. Dashboard (Tagesübersicht)

```
┌──────────────────────────────────────────────────────────────────────┐
│ 📊 DASHBOARD                                                         │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐           │
│  │ 🔵  3    │  │ 🔴  1    │  │ 🟡  2    │  │ 🟢  12   │           │
│  │ Heutige  │  │ Überfällige│  │ Unbezahlte│  │ Aktive   │           │
│  │ Projekte │  │ Rückgaben │  │ Projekte │  │ Projekte │           │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘           │
│                                                                      │
│  ┌───────────────────────────┐  ┌───────────────────────────┐      │
│  │ 📅 Heutige Projekte       │  │ ⚠ Überfällige Rückgaben   │      │
│  ├───────────────────────────┤  ├───────────────────────────┤      │
│  │ Projekt    │ Kunde │Status│  │ Projekt  │ Kunde │ Tage  │      │
│  │────────────┼───────┼──────│  │──────────┼───────┼───────│      │
│  │ Hochzeit M.│ Meyer │ 🟢  │  │ DJ-Set   │ Club X│ 5 Tage│      │
│  │ Konferenz  │ AG X  │ 🔵  │  │          │       │       │      │
│  │ Messe Setup│ GmbH  │ 🟡  │  │          │       │       │      │
│  └───────────────────────────┘  └───────────────────────────┘      │
│                                                                      │
│  ┌───────────────────────────┐  ┌───────────────────────────┐      │
│  │ 🔔 Mahnvorschläge         │  │ 💰 Unbezahlte Projekte    │      │
│  ├───────────────────────────┤  ├───────────────────────────┤      │
│  │ Projekt │ Tage │Empfehlung│  │ Projekt │ Ende  │ Betrag │      │
│  │─────────┼──────┼─────────│  │─────────┼───────┼────────│      │
│  │ Event X │32 T. │1. Mahnung│  │ Event X │15.01. │ 850 €  │      │
│  │ Party Y │18 T. │Erinnerung│  │ Party Y │22.01. │ 320 €  │      │
│  └───────────────────────────┘  └───────────────────────────┘      │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ 📆 Nächste 7 Tage                                            │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │ Projekt     │ Kunde  │Status│ Lieferung  │ Nutzung          │  │
│  │─────────────┼────────┼──────┼────────────┼─────────────────│  │
│  │ Festival    │ Verein │ 🟢  │ 03.03-05.03│ 04.03-04.03      │  │
│  │ Firmenfeier │ Corp.  │ 🔵  │ 06.03-07.03│ 06.03-06.03      │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ 🗓️ KALENDER (FullCalendar)                                   │  │
│  │ ◄  Februar 2026  ►          [Monat] [Woche] [Liste]          │  │
│  │ Mo │ Di │ Mi │ Do │ Fr │ Sa │ So                              │  │
│  │    │    │    │    │    │    │                                  │  │
│  │    │ ██ Hochzeit M. ███████ │                                  │  │
│  │    │    │    │ ████ Messe ██│                                  │  │
│  └──────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

## 3. Projektansicht (Tabs)

```
┌──────────────────────────────────────────────────────────────────────┐
│ Hochzeit Meyer                                                       │
├──────────────────────────────────────────────────────────────────────┤
│ [Details] [+ Assets] [📋 Liste] [🚚 Dispatch] [📄 Dateien/Rech.]   │
│ [💰 Finanzen] [👥 Crew] [📑 Dokumente] [📦 Packliste]              │
│ [↔ Ein/Ausgabe] [📜 Log]                                            │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────────────┐  ┌──────────────────────────────────────┐ │
│  │ 🏷️ Vermietung        │  │ 💬 Kommentare                       │ │
│  │                      │  │                                      │ │
│  │ Kunde: Meyer GmbH   │  │ 📅 28.02.2026 - Max M.               │ │
│  │ Status: [🟢 Bestät.] │  │ "Lieferung auf 14:00 verschoben"    │ │
│  │ Ort:    Stadthalle   │  │                                      │ │
│  │ Manager: Max M.      │  │ 📅 27.02.2026 - Max M.               │ │
│  │                      │  │ "Kunde hat Zusatz-PA bestellt"       │ │
│  │ Nutzung:             │  └──────────────────────────────────────┘ │
│  │  01.03 - 01.03.2026  │                                           │
│  │ Lieferung:           │  ┌──────────────────────────────────────┐ │
│  │  28.02 - 02.03.2026  │  │ 📊 Equipment-Zusammenfassung         │ │
│  │                      │  │                                      │ │
│  │ Beschreibung:        │  │  5x PA-Boxen        │ 200€/Tag      │ │
│  │ Hochzeitsfeier für   │  │  2x Mikrofon         │  20€/Tag      │ │
│  │ 150 Gäste, Live-     │  │  1x Mischpult        │  80€/Tag      │ │
│  │ Band + DJ            │  │  ─────────────────────────────       │ │
│  │                      │  │  Gesamt: 520,00 €                    │ │
│  │ ⚙ [Bearbeiten ▼]     │  └──────────────────────────────────────┘ │
│  │   Archivieren        │                                           │
│  │   Löschen            │                                           │
│  │   📋 Projekt klonen   │                                           │
│  └─────────────────────┘                                            │
└──────────────────────────────────────────────────────────────────────┘
```

## 4. Dokumentenverlauf (Lifecycle)

```
┌──────────────────────────────────────────────────────────────────────┐
│ 📑 Dokumentenverlauf                            [+ Dokument erstellen]│
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Dok.-Nr.  │ Typ               │ Status    │ Netto   │ Brutto │ Akt.│
│  ──────────┼───────────────────┼───────────┼─────────┼────────┼─────│
│  ANG-2026  │ Angebot           │ 🔵Versend.│ 520,00€ │ 520,00€│     │
│  -0042     │                   │           │         │        │     │
│            │                   │           │ [✅ Annehmen &   ]     │
│            │                   │           │ [   Auftr.best.  ]     │
│  ──────────┼───────────────────┼───────────┼─────────┼────────┼─────│
│  AB-2026   │ Auftragsbestät.   │ 🟢Akzept. │ 520,00€ │ 520,00€│     │
│  -0038     │                   │           │         │        │     │
│            │                   │           │ [➡ Rechnung     ]     │
│            │                   │           │ [  erstellen    ]     │
│  ──────────┼───────────────────┼───────────┼─────────┼────────┼─────│
│  RE-2026   │ Rechnung          │ 🟡Überfäll│ 520,00€ │ 520,00€│     │
│  -0051     │                   │           │         │        │     │
│            │ [📥] [📧] [↔ Status] [➡ Umwandeln]                     │
│                                                                      │
│  Dokumentenkette: Angebot ──► Auftr.best. ──► Rechnung              │
│                   ANG-0042    AB-0038         RE-0051                │
└──────────────────────────────────────────────────────────────────────┘
```

## 5. Mahnwesen (Vorschläge)

```
┌──────────────────────────────────────────────────────────────────────┐
│ ⚠ Mahnwesen                                                         │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐           │
│  │ 🟡  3    │  │ 🔴 1.560€│  │ 🔵  2    │  │ 🟢  5    │           │
│  │ Überfäll.│  │ Offener  │  │ Mahnungen│  │ Erledigt │           │
│  │ Rechnung.│  │ Betrag   │  │ (Monat)  │  │ (Monat)  │           │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘           │
│                                                                      │
│  Dok.-Nr.│ Projekt  │ Kunde   │ Brutto │ Fällig  │Tage│Stufe │Akt. │
│  ────────┼──────────┼─────────┼────────┼─────────┼────┼──────┼─────│
│  RE-0051 │ Event X  │ Firma A │ 850€   │01.02.26 │ 28 │🟡 1.M│[📧] │
│  RE-0049 │ Party Y  │ Club B  │ 320€   │15.01.26 │ 44 │🔴 2.M│[📧] │
│  RE-0045 │ Setup Z  │ GmbH C  │ 390€   │01.01.26 │ 58 │⬛ Let│[📧] │
│                                                                      │
│  ┌─────────────────────────────────────────┐                        │
│  │ 🔔 Mahnvorschlag                        │  ← Dialog bei Klick    │
│  │                                         │     auf 📧              │
│  │ ℹ Das System schlägt eine Mahnung vor.  │                        │
│  │   Sie entscheiden, ob diese versendet   │                        │
│  │   wird.                                  │                        │
│  │                                         │                        │
│  │ Dok.-Nr.:    RE-2026-0051               │                        │
│  │ Kunde:       Firma A                     │                        │
│  │ Brutto:      850,00 €                   │                        │
│  │ Überfällig:  28 Tage                    │                        │
│  │ Mahnstufe:   1. Mahnung                 │                        │
│  │                                         │                        │
│  │    [Abbrechen]  [📧 Mahnung erstellen]  │                        │
│  └─────────────────────────────────────────┘                        │
└──────────────────────────────────────────────────────────────────────┘
```

## 6. Verfügbarkeitskalender

```
┌──────────────────────────────────────────────────────────────────────┐
│ 📅 Verfügbarkeitskalender          Filter: [Alle Equipment-Typen ▼] │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ◄  März 2026  ►                   [Monat] [Woche] [Liste]          │
│  ┌─────┬─────┬─────┬─────┬─────┬─────┬─────┐                      │
│  │ Mo  │ Di  │ Mi  │ Do  │ Fr  │ Sa  │ So  │                      │
│  │  2  │  3  │  4  │  5  │  6  │  7  │  8  │                      │
│  │     │ ██████████████████████████████     │                      │
│  │     │ PA-Box #1 - Hochzeit Meyer        │                      │
│  │     │     │     │ ████████████████│     │                      │
│  │     │     │     │ Mischpult #3    │     │                      │
│  │     │     │     │     │ ████████████████│                      │
│  │     │     │     │     │ PA #2 - Messe  │                      │
│  └─────┴─────┴─────┴─────┴─────┴─────┴─────┘                      │
│                                                                      │
│  📋 Belegungsliste                                                   │
│  ────────────────────────────────────────────────────────────────── │
│  Typ       │ Tag │ Projekt         │ Kunde   │ Von    │ Bis       │
│  ──────────┼─────┼─────────────────┼─────────┼────────┼──────────│
│  PA-Box    │ #1  │ Hochzeit Meyer  │ Meyer   │ 03.03. │ 05.03.   │
│  PA-Box    │ #2  │ Firmen-Messe    │ Corp.   │ 06.03. │ 08.03.   │
│  Mischpult │ #3  │ Hochzeit Meyer  │ Meyer   │ 04.03. │ 05.03.   │
└──────────────────────────────────────────────────────────────────────┘
```

## 7. Live-Suche (Navbar)

```
┌──────────────────────────────────────────────────────────────────────┐
│ AdamRMS    [🔍 PA-Box____________]                                   │
│            ┌──────────────────────────────────────┐                  │
│            │ 📁 PA-Box Setup Hochzeit              │  ← Projekt     │
│            │    Meyer GmbH                project  │                  │
│            │ 📁 PA-Box Messe Hannover              │  ← Projekt     │
│            │    Messe AG                 project  │                  │
│            │ 📦 PA-Box QSC K12           #001      │  ← Equipment   │
│            │                              asset   │                  │
│            │ 📦 PA-Box QSC K12           #002      │  ← Equipment   │
│            │                              asset   │                  │
│            │ 📦 PA-Box JBL EON           #003      │  ← Equipment   │
│            │                              asset   │                  │
│            │──────────────────────────────────────│                  │
│            │ 🔍 Alle Ergebnisse anzeigen           │                  │
│            └──────────────────────────────────────┘                  │
└──────────────────────────────────────────────────────────────────────┘
```

## 8. Projekt klonen (Dialog)

```
┌──────────────────────────────────────────────┐
│ 📋 Projekt klonen                       [✕]  │
├──────────────────────────────────────────────┤
│                                              │
│  Projektname:                                │
│  ┌──────────────────────────────────────┐   │
│  │ Hochzeit Meyer (Kopie)               │   │
│  └──────────────────────────────────────┘   │
│                                              │
│  ☑ Equipment-Zuweisungen übernehmen         │
│  ☐ Crew-Zuweisungen übernehmen              │
│                                              │
│  ℹ Daten (Start/Ende) werden NICHT kopiert  │
│    - bitte im neuen Projekt setzen.          │
│                                              │
│  [Abbrechen]         [📋 Projekt klonen]    │
└──────────────────────────────────────────────┘
```

## 9. Finanzen (Projektansicht)

```
┌──────────────────────────────────────────────────────────────────────┐
│ 💰 Finanzen                                                          │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────────────────────┐  ┌────────────────────────────────┐│
│  │ Übersicht                    │  │ Rechnungsnotizen               ││
│  │                              │  │                                ││
│  │ Equipment Zwischens. │520,00€│  │ Gemäß § 19 UStG wird keine   ││
│  │ Rabatte              │   -   │  │ Umsatzsteuer berechnet.       ││
│  │ Equipment Gesamt     │520,00€│  │                                ││
│  │                      │       │  │ [Bearbeiten]                   ││
│  │ Verkäufe             │  0,00€│  └────────────────────────────────┘│
│  │ Personal             │  0,00€│                                    │
│  │ Fremdleistungen      │  0,00€│  ┌────────────────────────────────┐│
│  │ ─────────────────────────── │  │ Lieferschein-Notizen           ││
│  │ Zwischensumme        │520,00€│  │                                ││
│  │ Zahlungen eingeg.    │  0,00€│  │ (Nicht festgelegt)             ││
│  │ ══════════════════════════ │  │ [Bearbeiten]                   ││
│  │ GESAMT AUSSTEHEND    │520,00€│  └────────────────────────────────┘│
│  │                              │                                    │
│  │ [Hilfe] [+ Zahlung]         │                                    │
│  └─────────────────────────────┘                                    │
└──────────────────────────────────────────────────────────────────────┘
```

## 10. Geschäftsdaten / KUR-Einstellungen

```
┌──────────────────────────────────────────────────────────────────────┐
│ ⚙ Geschäftsdaten (§19 UStG Kleinunternehmer)                        │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Firmenname:     [Mein Verleih                              ]       │
│  Adresse:        [Musterstraße 1, 12345 Musterstadt         ]       │
│  Steuernummer:   [123/456/78901                             ]       │
│  USt-IdNr.:      [(optional)                                ]       │
│                                                                      │
│  ☑ Kleinunternehmerregelung (§19 UStG) aktiv                        │
│     → Keine MwSt. auf Rechnungen                                    │
│                                                                      │
│  Bankverbindung:                                                     │
│  IBAN:           [DE89 3704 0044 0532 0130 00               ]       │
│  BIC:            [COBADEFFXXX                                ]       │
│  Bankname:       [Commerzbank                                ]       │
│                                                                      │
│  Zahlungsziel:   [14] Tage                                          │
│                                                                      │
│  Rechnungs-Fußzeile:                                                │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Bitte überweisen Sie den Betrag innerhalb von 14 Tagen.     │  │
│  │ Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.         │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  [💾 Speichern]                                                      │
└──────────────────────────────────────────────────────────────────────┘
```
