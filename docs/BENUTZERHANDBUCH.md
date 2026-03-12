# AdamRMS - Benutzerhandbuch

## Erste Schritte

### Anmeldung
1. Oeffnen Sie die AdamRMS-URL in Ihrem Browser
2. Geben Sie Benutzername und Passwort ein
3. Optional: 2-Faktor-Code eingeben (wenn aktiviert)

### Dashboard
Nach der Anmeldung sehen Sie das Dashboard mit:
- **Heutige Projekte**: Alle Projekte die heute stattfinden
- **Ueberfaellige Rueckgaben**: Equipment das zurueckgegeben werden sollte
- **Offene Rechnungen**: Unbezahlte Rechnungen
- **Benachrichtigungen**: Aktuelle Hinweise (Glocken-Symbol)

---

## Projekte

### Projekt anlegen
1. **Schnellerfassung**: Klicken Sie "Neues Projekt" und geben Sie nur Name, Datum und Kunde ein
2. **Detailliert**: Ueber das Projektformular mit allen Feldern

### Equipment zuweisen
1. Im Projekt auf "Equipment hinzufuegen" klicken
2. Asset-Typ suchen oder Barcode scannen
3. Menge waehlen, Verfuegbarkeit wird automatisch geprueft
4. Bei Konflikten wird eine Warnung angezeigt

### Dokumenten-Workflow
1. **Angebot erstellen** → PDF-Vorschau → An Kunden senden
2. **Kunde bestaetigt** → Angebot wird automatisch zur Auftragsbestaetigung
3. **Rechnung erstellen** → Aus Angebot mit einem Klick
4. **Zahlung erfassen** → Manuell oder ueber Bankimport

### Projekt-Checkliste
- Aufgaben pro Projekt anlegen und abhaken
- Fortschrittsbalken zeigt Erledigungsgrad
- Ideal fuer Aufbau-/Abbau-Checklisten

---

## Equipment

### Inventur
1. **Browser**: Barcode-Scanner ueber Kamera oeffnen
2. **Android-App**: Equipment scannen im Inventur-Modus
3. Soll-Ist-Vergleich wird automatisch berechnet

### Wartung
- Wartungsintervalle pro Asset-Typ definieren
- Automatische Erinnerungen wenn Wartung faellig
- Wartungshistorie einsehen

### RFID
Falls RFID-Hardware vorhanden:
1. RFID-Tag an Asset scannen und zuordnen
2. Gateway registrieren fuer automatische Erfassung
3. Inventur durch Gate-Durchfahrt

### Lebenszyklus
Assets durchlaufen Stufen:
Bestellt → Erhalten → In Betrieb → Aktiv → Wartung/Reparatur → Ausgemustert → Entsorgt/Verkauft

---

## Kunden

### Kunden anlegen
- Name, Adresse, Kontaktdaten
- USt-IdNr. wird automatisch ueber VIES validiert (EU)
- Kreditlimit und Zahlungsbedingungen pro Kunde
- Mehrere Ansprechpartner pro Kunde moeglich

### Kunden-Portal
Kunden koennen per Token-Link (ohne Login):
- Ihre Projekte einsehen
- Rechnungen herunterladen
- Angebote bestaetigen oder ablehnen
- Feedback abgeben

---

## Rechnungswesen

### Rechnungen
- Automatisch aus Projekten generiert
- ZUGFeRD/XRechnung fuer B2G
- Skonto-Bedingungen
- GiroCode auf Rechnung (EPC-QR-Code)

### Mahnwesen
- 3 Mahnstufen mit automatischer Eskalation
- Mahnbriefe als PDF per E-Mail
- Mahngebuehren werden automatisch berechnet
- Mahnsperre bei Teilzahlung

### Zahlungslinks
- Stripe-Zahlungslink auf Rechnung (Kreditkarte, SEPA, Giropay)
- PayPal-Zahlungslink optional

### Rabattcodes
- Codes mit Prozent- oder Festbetrag-Rabatt
- Gueltigkeitszeitraum und Max-Nutzungen
- Einschraenkung auf bestimmte Asset-Typen

---

## Kommunikation

### E-Mail-Vorlagen
Unter Einstellungen > E-Mail-Vorlagen:
- 6 vordefinierte Template-Typen (anpassbar)
- Platzhalter: `{{kunde}}`, `{{projekt}}`, `{{datum}}` etc.
- Vorschau vor dem Speichern

### Automatische E-Mails
- **Projektbestaetigung**: Sofort nach Bestaetigung
- **Erinnerung**: 3 + 1 Tag vor Projektstart
- **Feedback-Anfrage**: 2 Tage nach Projektende

### SMS/WhatsApp
- Erinnerungen per SMS oder WhatsApp
- Erfordert Twilio-Account (konfigurierbar)

---

## Berichte & Auswertungen

### Verfuegbare Berichte
- **Umsatz**: Monatlich, jaehrlich, Vergleich
- **Auslastung**: Equipment-Auslastungsquote
- **ROI**: Anschaffungskosten vs. Mieteinnahmen
- **Top-Kunden**: Ranking nach Umsatz
- **Saisonalitaet**: Welche Monate sind am staerksten
- **BWA**: Betriebswirtschaftliche Auswertung
- **EUeR**: Einnahme-Ueberschuss-Rechnung

### Export
- Excel (XLSX) und PDF Export fuer alle Berichte
- DATEV-Export fuer Steuerberater

---

## Mobile Nutzung

### PWA (Progressive Web App)
- AdamRMS als App auf Homescreen installieren
- Offline-Faehigkeit fuer Basisfunktionen

### Android Scanner-App
- Barcode/QR-Scanner fuer Equipment
- Check-in/Check-out mit Foto
- Packauftrag via QR-Code auf Lieferschein
- Offline-Modus mit automatischer Synchronisation

### Dark Mode
- Unter Benutzer-Einstellungen umschaltbar

---

## Tastenkuerzel

| Kuerzel | Funktion |
|---------|----------|
| `Ctrl+K` | Schnellsuche oeffnen |
| `Ctrl+N` | Neues Projekt |
| `Ctrl+S` | Speichern |

---

## FAQ

**Wie aktiviere ich 2-Faktor-Authentifizierung?**
Unter Mein Account > Sicherheit > 2FA aktivieren. QR-Code mit Authenticator-App scannen.

**Wie erstelle ich einen Rabattcode?**
Unter Einstellungen > Rabattcodes > Neuer Code. Code, Rabattwert und Gueltigkeitszeitraum eingeben.

**Wie sende ich eine Rechnung per E-Mail?**
Im Projekt > Dokumente > Rechnung > "Per E-Mail senden". Oder automatisch via Cronjob.

**Wie importiere ich Kunden?**
Unter Kunden > Import. CSV-Datei hochladen, Spalten zuordnen, importieren.
