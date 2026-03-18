package com.rms.scanner.rfid

import android.util.Log
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * ScanTriggerManager - Singleton that broadcasts hardware scan button events and barcode scans.
 *
 * The Chafon CF-H906 has three physical scan triggers:
 *   - Left side button
 *   - Right side button
 *   - Pistol grip trigger
 *
 * These send KeyEvents (typically F1, keyCode 131, 133, or KEYCODE_BUTTON_L1/R1)
 * which are intercepted by MainActivity.onKeyDown() and forwarded here.
 *
 * Additionally, the device has a built-in 2D barcode scanner that broadcasts
 * barcode values via Intent. These are captured in MainActivity and forwarded here.
 *
 * Any active ViewModel/Screen can collect from [triggerEvents] to react to hardware
 * button presses, and from [barcodeEvents] to react to barcode scans.
 */
object ScanTriggerManager {
    private const val TAG = "ScanTriggerManager"

    /**
     * Emitted when a hardware scan button is pressed or released.
     */
    sealed class TriggerEvent {
        object Pressed : TriggerEvent()
        object Released : TriggerEvent()
    }

    /**
     * Emitted when a 2D barcode or QR code is scanned.
     */
    sealed class ScanInputEvent {
        data class BarcodeScanned(val value: String) : ScanInputEvent()
    }

    private val _triggerEvents = MutableSharedFlow<TriggerEvent>(
        extraBufferCapacity = 5
    )
    val triggerEvents: SharedFlow<TriggerEvent> = _triggerEvents.asSharedFlow()

    private val _barcodeEvents = MutableSharedFlow<ScanInputEvent.BarcodeScanned>(
        extraBufferCapacity = 5
    )
    val barcodeEvents: SharedFlow<ScanInputEvent.BarcodeScanned> = _barcodeEvents.asSharedFlow()

    /**
     * Called from MainActivity.onKeyDown when a scan trigger button is pressed.
     */
    fun onTriggerPressed() {
        Log.d(TAG, "Hardware scan trigger PRESSED")
        _triggerEvents.tryEmit(TriggerEvent.Pressed)
    }

    /**
     * Called from MainActivity.onKeyUp when a scan trigger button is released.
     */
    fun onTriggerReleased() {
        Log.d(TAG, "Hardware scan trigger RELEASED")
        _triggerEvents.tryEmit(TriggerEvent.Released)
    }

    /**
     * Called from MainActivity.barcodeReceiver when a 2D barcode or QR code is scanned.
     * The barcode value is passed directly to subscribers without triggering onTriggerPressed.
     */
    fun onBarcodeScanned(barcodeValue: String) {
        Log.d(TAG, "Barcode scanned: $barcodeValue")
        _barcodeEvents.tryEmit(ScanInputEvent.BarcodeScanned(barcodeValue))
    }

    /**
     * Known scan trigger keycodes for the CF-H906 and similar UHF PDA devices.
     * These are checked in MainActivity to decide whether a KeyEvent is a scan trigger.
     */
    val SCAN_TRIGGER_KEYCODES = setOf(
        android.view.KeyEvent.KEYCODE_F1,       // 131 — common Chafon mapping
        android.view.KeyEvent.KEYCODE_F2,       // 132
        android.view.KeyEvent.KEYCODE_F3,       // 133
        android.view.KeyEvent.KEYCODE_F4,       // 134
        android.view.KeyEvent.KEYCODE_F5,       // 135 — some devices use this
        android.view.KeyEvent.KEYCODE_BUTTON_L1,// left trigger
        android.view.KeyEvent.KEYCODE_BUTTON_R1,// right trigger
        280, // Custom keycode used by some Chafon devices for pistol grip
        281, // Alternate custom keycode
        282  // Alternate custom keycode
    )
}
