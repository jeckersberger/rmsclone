package com.rms.scanner.ui.screens

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Slider
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.data.preferences.AppPreferences
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary

@Composable
fun SettingsScreen(
    context: Context,
    onBack: () -> Unit
) {
    val prefs = remember { AppPreferences(context) }
    var serverUrl by remember { mutableStateOf(prefs.getServerUrl()) }
    var scannerPower by remember { mutableIntStateOf(prefs.getScannerPower()) }
    var soundEnabled by remember { mutableStateOf(prefs.isSoundEnabled()) }
    var vibrationEnabled by remember { mutableStateOf(prefs.isVibrationEnabled()) }
    var useMockScanner by remember { mutableStateOf(prefs.isUseMockScanner()) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
            .verticalScroll(rememberScrollState())
    ) {
        // Header
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Einstellungen",
                    style = MaterialTheme.typography.displaySmall,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            }
            Button(
                onClick = onBack,
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.SurfaceDark
                )
            ) {
                Text("Zurück")
            }
        }

        Column(modifier = Modifier.padding(16.dp)) {
            // Server URL
            Text(
                text = "Server URL",
                style = MaterialTheme.typography.titleMedium,
                color = TextSecondary,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            OutlinedTextField(
                value = serverUrl,
                onValueChange = { serverUrl = it },
                label = { Text("URL eingeben") },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 16.dp),
                colors = TextFieldDefaults.colors(
                    focusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                    unfocusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                    focusedTextColor = TextPrimary,
                    unfocusedTextColor = TextPrimary
                ),
                singleLine = true
            )

            Button(
                onClick = { prefs.setServerUrl(serverUrl) },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 24.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.Primary,
                    contentColor = TextPrimary
                )
            ) {
                Text("Speichern", style = MaterialTheme.typography.labelLarge)
            }

            // Scanner Power
            Text(
                text = "Scanner Leistung",
                style = MaterialTheme.typography.titleMedium,
                color = TextSecondary,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Slider(
                    value = scannerPower.toFloat(),
                    onValueChange = { scannerPower = it.toInt() },
                    valueRange = 5f..30f,
                    modifier = Modifier.weight(1f),
                    steps = 24
                )
                Text(
                    text = "$scannerPower dBm",
                    style = MaterialTheme.typography.bodyMedium,
                    color = TextPrimary,
                    modifier = Modifier.padding(start = 12.dp)
                )
            }

            Button(
                onClick = { prefs.setScannerPower(scannerPower) },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 24.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.Primary,
                    contentColor = TextPrimary
                )
            ) {
                Text("Speichern", style = MaterialTheme.typography.labelLarge)
            }

            // Sound Toggle
            SettingToggle(
                title = "Ton",
                enabled = soundEnabled,
                onToggle = {
                    soundEnabled = it
                    prefs.setSoundEnabled(it)
                }
            )

            // Vibration Toggle
            SettingToggle(
                title = "Vibration",
                enabled = vibrationEnabled,
                onToggle = {
                    vibrationEnabled = it
                    prefs.setVibrationEnabled(it)
                }
            )

            // Mock Scanner Toggle
            SettingToggle(
                title = "Mock Scanner (Test)",
                enabled = useMockScanner,
                onToggle = {
                    useMockScanner = it
                    prefs.setUseMockScanner(it)
                }
            )

            // Version Info
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(com.rms.scanner.ui.theme.SurfaceDark, androidx.compose.foundation.shape.RoundedCornerShape(8.dp))
                    .padding(16.dp)
            ) {
                Text(
                    text = "Version",
                    style = MaterialTheme.typography.labelMedium,
                    color = TextSecondary
                )
                Text(
                    text = "1.0.0",
                    style = MaterialTheme.typography.bodyLarge,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
fun SettingToggle(
    title: String,
    enabled: Boolean,
    onToggle: (Boolean) -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(com.rms.scanner.ui.theme.SurfaceDark, androidx.compose.foundation.shape.RoundedCornerShape(8.dp))
            .padding(16.dp)
            .padding(bottom = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.bodyMedium,
            color = TextPrimary,
            modifier = Modifier.weight(1f)
        )
        Switch(
            checked = enabled,
            onCheckedChange = onToggle
        )
    }
}

import androidx.compose.foundation.shape.RoundedCornerShape
