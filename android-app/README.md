# RMS Scanner - Android App

A complete native Android application for the Chafon CF-H906 Android 11 UHF RFID handheld scanner, designed to interface with the Rental Management System (RMS) PHP backend.

## Features

- **Ausleihe (Checkout)**: Scan items to check them out from the system
- **Rückgabe (Checkin)**: Scan items to return them to the system
- **Kisten-Scan (Box Scan)**: Scan multiple items and categorize by type
- **Inventur (Inventory)**: Conduct physical inventories with automatic tracking
- **Tag schreiben (Write Tag)**: Assign RFID tags to assets and stock items
- **Einstellungen (Settings)**: Configure scanner power, server URL, and preferences

## Technology Stack

- **Language**: Kotlin 1.9.22
- **UI Framework**: Jetpack Compose with Material3
- **Build System**: Gradle with Kotlin DSL
- **Android Versions**: Minimum SDK 30 (Android 11), Target SDK 34 (Android 14)
- **API Communication**: Retrofit2 + OkHttp3 + Gson
- **Async**: Kotlin Coroutines
- **State Management**: StateFlow + ViewModel

## Project Structure

```
android-app/
├── build.gradle.kts              (project-level configuration)
├── settings.gradle.kts
├── gradle.properties
├── gradle/wrapper/               (Gradle 8.4)
├── app/
│   ├── build.gradle.kts          (app-level dependencies & config)
│   ├── src/main/
│   │   ├── AndroidManifest.xml
│   │   ├── java/com/rms/scanner/
│   │   │   ├── MainActivity.kt   (Entry point)
│   │   │   ├── RmsApp.kt         (Application class)
│   │   │   ├── data/
│   │   │   │   ├── api/
│   │   │   │   │   ├── RmsApiService.kt
│   │   │   │   │   ├── RmsApiClient.kt
│   │   │   │   │   └── models/   (Request/response DTOs)
│   │   │   │   ├── preferences/
│   │   │   │   │   └── AppPreferences.kt
│   │   │   │   └── repository/
│   │   │   │       └── RmsRepository.kt
│   │   │   ├── rfid/
│   │   │   │   ├── RfidManager.kt
│   │   │   │   ├── ChafonRfidManager.kt
│   │   │   │   ├── MockRfidManager.kt
│   │   │   │   └── RfidEvent.kt
│   │   │   ├── ui/
│   │   │   │   ├── theme/
│   │   │   │   ├── screens/
│   │   │   │   ├── components/
│   │   │   │   ├── viewmodels/
│   │   │   │   └── navigation/
│   │   │   └── service/
│   │   └── res/
│   │       ├── values/
│   │       ├── drawable/
│   │       └── mipmap-hdpi/
│   └── proguard-rules.pro
└── README.md
```

## Building the Project

### Prerequisites

- Android Studio Hedgehog or later
- Java Development Kit (JDK) 11+
- Gradle 8.4 (included via wrapper)

### Build Steps

1. **Clone/Open Project**
   ```bash
   cd android-app
   ```

2. **Build with Gradle**
   ```bash
   ./gradlew build          # Build all variants
   ./gradlew assembleDebug  # Build debug APK
   ```

3. **Run on Device/Emulator**
   ```bash
   ./gradlew installDebug   # Install debug APK
   adb shell am start -n com.rms.scanner/.MainActivity
   ```

## Configuration

### Server Connection

The app connects to the RMS PHP backend via HTTP POST requests. Configure the server URL in the Settings screen or directly in `AppPreferences`:

```kotlin
val prefs = AppPreferences(context)
prefs.setServerUrl("http://192.168.1.100/")
```

### API Endpoints

The app communicates with these backend endpoints:

**RFID Operations** (`POST /rfid/scan.php`)
- `?action=universal_scan` - Scan single item
- `?action=universal_lookup` - Look up item by tag
- `?action=box_scan` - Scan multiple items
- `?action=box_scan_action` - Apply action to scanned items
- `?action=start_inventory` - Start inventory session
- `?action=inventory_scan` - Record tag during inventory
- `?action=complete_inventory` - Finish inventory session
- `?action=assign_tag` - Assign RFID to asset
- `?action=list_assets` - List all assets

**Stock Operations** (`POST /stock/items.php`)
- `?action=list_items` - List stock item types
- `?action=list_instances` - List stock instances
- `?action=assign_rfid` - Assign RFID to stock instance

**Projects** (`POST /assets/projects.php`)
- `?action=list` - List all projects

## RFID Scanner Integration

### Chafon CF-H906

The app includes a `ChafonRfidManager` class that provides a template for integrating the Chafon SDK. The implementation is stubbed with TODO comments indicating where actual SDK calls should be inserted.

**TODO Items in ChafonRfidManager:**
1. Replace connection logic with actual Chafon SDK
2. Implement inventory start/stop callbacks
3. Add tag read event handling
4. Implement EPC write operations
5. Add power level control

**Chafon SDK Download:**
Visit https://www.chafon.com/Download to obtain the Android SDK JAR and documentation.

### Mock Implementation

For testing without hardware, the app includes `MockRfidManager` which generates simulated tag reads every 2 seconds. Enable this in Settings or programmatically:

```kotlin
prefs.setUseMockScanner(true)
```

## UI Screens

### Login Screen
- Server URL configuration
- Optional username/password
- Connection test button
- Status indicator

### Main Menu
- 6 large tiles (2x3 grid)
- Each tile maps to a feature
- Large touch targets for gloved operation

### Checkout/Checkin Screens
- Project selector
- Real-time scan results
- Scan history
- Start/Stop controls

### Box Scan Screen
- Tag counter
- Results grouped by type (Asset/Stock/Unknown)
- Batch action buttons (Checkout All/Checkin All)
- Reset button

### Inventory Screen
- Start/Complete controls
- Running tag counter
- Final results (Found/Missing/Unknown counts)

### Tag Write Screen
- Entity type selector (Asset or Stock)
- Entity selector dropdown
- EPC input with auto-generation
- Write button

### Settings Screen
- Server URL configuration
- Scanner power slider (5-30 dBm)
- Sound/Vibration toggles
- Mock scanner toggle
- App version display

## Physical Scanner Button Handling

The CF-H906 has a physical trigger button that can be mapped to:
- KeyEvent.KEYCODE_F1
- Custom keycodes (check Chafon documentation)

The `MainActivity` includes handling for these keycodes in `onKeyDown()`. Customize the routing based on your implementation.

## Permissions

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.WAKE_LOCK" />
<uses-permission android:name="android.permission.VIBRATE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
```

## Theme

The app uses a dark theme optimized for warehouse environments:
- **Primary**: Blue (#2563EB)
- **Success**: Green (#16A34A)
- **Error**: Red (#DC2626)
- **Warning**: Amber (#D97706)
- **Large touch targets**: 56dp minimum
- **High contrast**: Text on dark backgrounds
- **All UI in German**

## Error Handling

Network errors, validation failures, and RFID issues are displayed to the user with:
- Error toast messages
- In-screen error text
- Graceful fallbacks
- Retry mechanisms

## Testing

### Mock Mode

Enable mock scanner for UI testing without hardware:

```kotlin
val prefs = AppPreferences(context)
prefs.setUseMockScanner(true)
```

This generates realistic test data that cycles through predefined tag patterns.

### Network Simulation

Use Charles Proxy or similar tools to simulate API responses during development.

## Performance Considerations

- Debouncing of rapid tag reads
- Batch processing for box scans
- Efficient state management with StateFlow
- Coroutines for non-blocking network calls
- Image/view recycling in lists

## Logging

All components use Android's standard logging framework. View logs with:

```bash
adb logcat -s "RmsApp|RmsApiClient|ScanViewModel" | grep -E "^.*INFO|WARN|ERROR"
```

## Future Enhancements

- [ ] Biometric authentication
- [ ] Offline mode with sync
- [ ] Barcode scanning support
- [ ] Multi-language support
- [ ] Sound/vibration feedback customization
- [ ] Statistics and reporting dashboard
- [ ] Chafon SDK full integration
- [ ] Bluetooth connectivity options

## Troubleshooting

### App Won't Connect to Server
- Verify server URL in Settings (format: http://192.168.x.x/)
- Check device network connectivity
- Review Logcat for HTTP errors
- Test with mock scanner enabled

### RFID Scanner Not Responding
- Ensure CF-H906 is connected and powered
- Check Chafon SDK installation
- Verify app permissions (INTERNET, ACCESS_NETWORK_STATE)
- Review ChafonRfidManager implementation
- Test with MockRfidManager to isolate issues

### UI Rendering Issues
- Clear app cache: `adb shell pm clear com.rms.scanner`
- Update Compose and Material3 versions
- Test on different Android versions (30+)

## License

Internal use only for Rental Management System

## Support

For questions about Chafon SDK integration, refer to:
- Chafon Technical Documentation
- CF-H906 User Manual
- Android SDK Documentation: https://developer.android.com/docs

For RMS API documentation, contact system administrator.
