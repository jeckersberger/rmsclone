# AdamRMS Scanner - Android App

Native Android-App fuer Equipment-Scanning im Lager- und Eventbetrieb.

## Features
- Barcode/QR-Code Scanner (Kamera + Bluetooth-Scanner)
- Equipment Check-in/Check-out mit Foto + Notiz
- Lieferschein-QR scannen und Packauftrag abhaken
- Inventur-Modus: Assets scannen und mit Soll-Bestand abgleichen
- Offline-Faehigkeit mit automatischer Synchronisation
- Push-Benachrichtigungen
- Token-basierte API-Authentifizierung

## Technologie
- Kotlin + Jetpack Compose
- CameraX fuer Barcode-Scanning
- ML Kit Barcode Scanning (Google)
- Room Database (Offline-Cache)
- Retrofit (API-Client)
- Firebase Cloud Messaging (Push)
- WorkManager (Background Sync)

## Setup

```bash
# Android Studio oeffnen
# Projekt: android-scanner/

# API-URL konfigurieren
# app/src/main/res/values/strings.xml -> api_base_url

# Build
./gradlew assembleDebug

# Install
./gradlew installDebug
```

## API-Authentifizierung

Die App verwendet Token-basierte Authentifizierung:

```
POST /api/auth/token.php
Content-Type: application/json

{"username": "...", "password": "..."}

Response: {"token": "eyJ..."}
```

Alle weiteren Requests:
```
Authorization: Bearer <token>
```

## Architektur

```
app/src/main/java/de/adamrms/scanner/
├── MainActivity.kt          # Navigation + Compose Setup
├── api/
│   ├── AdamRmsApi.kt        # Retrofit API Interface
│   └── AuthInterceptor.kt   # Token-Injection
├── scanner/
│   ├── ScannerScreen.kt     # Kamera-Scanner UI
│   └── BarcodeAnalyzer.kt   # ML Kit Integration
├── checkin/
│   ├── CheckInScreen.kt     # Check-in/out Workflow
│   └── PhotoCapture.kt      # Foto-Aufnahme
├── packing/
│   └── PackingScreen.kt     # Packauftrag-Ansicht
├── inventory/
│   └── InventoryScreen.kt   # Inventur-Modus
├── data/
│   ├── AppDatabase.kt       # Room Database
│   ├── ScanDao.kt           # Offline-Scan Cache
│   └── SyncWorker.kt        # Background Sync
└── auth/
    └── LoginScreen.kt       # Token-Login
```
