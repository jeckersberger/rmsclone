package com.rms.scanner

import android.os.Build
import android.os.Bundle
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
import com.rms.scanner.ui.navigation.AppNavigation
import com.rms.scanner.ui.theme.RmsScannerTheme
import com.rms.scanner.ui.theme.SurfaceLight

class MainActivity : ComponentActivity() {
    private lateinit var rfidManager: RfidManager
    private lateinit var prefs: AppPreferences

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

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        // Handle physical scanner trigger button
        // The CF-H906 typically maps to F1 or custom keycode
        if (keyCode == KeyEvent.KEYCODE_F1 || keyCode == 131 || keyCode == 133) {
            // Scanner trigger pressed - can be handled per screen via ViewModel
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onDestroy() {
        super.onDestroy()
        // Disconnect RFID manager
        try {
            rfidManager.disconnect()
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
