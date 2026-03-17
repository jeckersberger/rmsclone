package com.rms.scanner

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Bundle
import android.util.Log
import android.view.KeyEvent
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.navigation.compose.rememberNavController
import com.google.accompanist.systemuicontroller.rememberSystemUiController
import com.rms.scanner.data.api.RmsApiClient
import com.rms.scanner.data.preferences.AppPreferences
import com.rms.scanner.rfid.ChafonRfidManager
import com.rms.scanner.rfid.MockRfidManager
import com.rms.scanner.rfid.RfidManager
import com.rms.scanner.rfid.ScanTriggerManager
import com.rms.scanner.ui.navigation.AppNavigation
import com.rms.scanner.ui.theme.RmsScannerTheme
import com.rms.scanner.ui.theme.SurfaceLight

class MainActivity : ComponentActivity() {
    private val tag = "MainActivity"
    private lateinit var rfidManager: RfidManager
    private lateinit var prefs: AppPreferences

    /**
     * BroadcastReceiver for the CF-H906's built-in 2D barcode scanner.
     * Many Chafon/Android PDA devices broadcast barcode results via Intent.
     * Common action: "com.scanner.broadcast" or "android.intent.ACTION_DECODE_DATA"
     */
    private val barcodeReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            val barcode = intent?.getStringExtra("barcode")
                ?: intent?.getStringExtra("decode_data")
                ?: intent?.getStringExtra("SCAN_BARCODE1")
                ?: return

            Log.d(tag, "2D Barcode received: $barcode")
            // Barcode scans are treated like a scan trigger press + immediate tag read.
            // The barcode value will be handled by the active screen's ViewModel
            // through the same ScanTriggerManager flow.
            ScanTriggerManager.onTriggerPressed()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Initialize preferences and API client
        prefs = AppPreferences(this)
        RmsApiClient.setBaseUrl(prefs.getServerUrl())

        // Initialize RFID Manager
        rfidManager = if (prefs.isUseMockScanner()) {
            MockRfidManager()
        } else {
            ChafonRfidManager()
        }

        // Connect to RFID reader
        rfidManager.connect()

        // Register 2D barcode scanner broadcast receiver
        registerBarcodeReceiver()

        setContent {
            RmsScannerTheme {
                val systemUiController = rememberSystemUiController()
                val navController = rememberNavController()
                var isLoggedIn by remember { mutableStateOf(false) }

                // Set system UI colors
                systemUiController.setSystemBarsColor(
                    color = SurfaceLight,
                    darkIcons = false
                )

                Surface(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(MaterialTheme.colorScheme.background),
                    color = MaterialTheme.colorScheme.background
                ) {
                    AppNavigation(
                        navController = navController,
                        context = this@MainActivity,
                        rfidManager = rfidManager,
                        isLoggedIn = isLoggedIn
                    )
                }
            }
        }
    }

    /**
     * Handle physical scan button press.
     * The CF-H906 has left/right side buttons and a pistol grip trigger.
     * All map to KeyEvents that we forward to ScanTriggerManager.
     */
    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode in ScanTriggerManager.SCAN_TRIGGER_KEYCODES) {
            Log.d(tag, "Scan trigger key DOWN: $keyCode")
            ScanTriggerManager.onTriggerPressed()
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    /**
     * Handle physical scan button release.
     * Some workflows (continuous scan) stop when the trigger is released.
     */
    override fun onKeyUp(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode in ScanTriggerManager.SCAN_TRIGGER_KEYCODES) {
            Log.d(tag, "Scan trigger key UP: $keyCode")
            ScanTriggerManager.onTriggerReleased()
            return true
        }
        return super.onKeyUp(keyCode, event)
    }

    /**
     * Register broadcast receivers for common 2D barcode scanner intents.
     * Different PDA manufacturers use different broadcast actions.
     */
    private fun registerBarcodeReceiver() {
        try {
            val filter = IntentFilter().apply {
                // Chafon / common Android PDA barcode broadcast actions
                addAction("com.scanner.broadcast")
                addAction("android.intent.ACTION_DECODE_DATA")
                addAction("com.android.server.scannerservice.broadcast")
                addAction("com.barcode.sendBroadcast")
            }
            registerReceiver(barcodeReceiver, filter)
            Log.d(tag, "2D barcode receiver registered")
        } catch (e: Exception) {
            Log.e(tag, "Failed to register barcode receiver", e)
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        // Unregister barcode receiver
        try {
            unregisterReceiver(barcodeReceiver)
        } catch (e: Exception) {
            // Receiver might not have been registered
        }
        // Disconnect RFID manager
        try {
            rfidManager.disconnect()
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
