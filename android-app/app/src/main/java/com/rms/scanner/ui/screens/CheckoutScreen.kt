package com.rms.scanner.ui.screens

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
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.components.ScanHistoryList
import com.rms.scanner.ui.components.ScanResultCard
import com.rms.scanner.ui.components.StatusIndicator
import com.rms.scanner.ui.theme.Checkout
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.viewmodels.ScanViewModel

@Composable
fun CheckoutScreen(
    viewModel: ScanViewModel,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    var showProjectMenu by remember { mutableStateOf(false) }

    // Activate hardware trigger listener for checkout mode
    LaunchedEffect(Unit) {
        viewModel.startHardwareTriggerListener("checkout")
    }

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
                    text = "Ausleihe",
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

        // Project Selector
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "Projekt",
                style = MaterialTheme.typography.labelMedium,
                color = TextSecondary,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            Button(
                onClick = { showProjectMenu = true },
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.SurfaceDark
                )
            ) {
                Text(
                    text = uiState.projects.find { it.id == uiState.selectedProjectId }?.name
                        ?: "Projekt wählen",
                    modifier = Modifier.weight(1f),
                    style = MaterialTheme.typography.bodyMedium
                )
            }

            DropdownMenu(
                expanded = showProjectMenu,
                onDismissRequest = { showProjectMenu = false }
            ) {
                uiState.projects.forEach { project ->
                    DropdownMenuItem(
                        text = { Text(project.name) },
                        onClick = {
                            viewModel.selectProject(project.id)
                            showProjectMenu = false
                        }
                    )
                }
            }
        }

        // Last Scan Result
        if (uiState.lastScanResult != null) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Text(
                    text = "Letztes Scan-Ergebnis",
                    style = MaterialTheme.typography.titleMedium,
                    color = TextSecondary,
                    modifier = Modifier.padding(bottom = 12.dp)
                )
                ScanResultCard(uiState.lastScanResult!!)
            }
        }

        // Status Messages
        if (uiState.errorMessage != null) {
            Text(
                text = "❌ ${uiState.errorMessage}",
                style = MaterialTheme.typography.bodyMedium,
                color = androidx.compose.material3.tokens.ColorSchemeKeyTokens.Error,
                modifier = Modifier.padding(16.dp)
            )
        }

        if (uiState.successMessage != null) {
            Text(
                text = "✓ ${uiState.successMessage}",
                style = MaterialTheme.typography.bodyMedium,
                color = com.rms.scanner.ui.theme.Success,
                modifier = Modifier.padding(16.dp)
            )
        }

        // Scan History
        if (uiState.scanHistory.isNotEmpty()) {
            ScanHistoryList(
                history = uiState.scanHistory,
                modifier = Modifier.padding(16.dp)
            )
        }

        // Control Buttons
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(8.dp)
        ) {
            Button(
                onClick = { viewModel.startScanning("checkout") },
                modifier = Modifier
                    .weight(1f)
                    .padding(vertical = 12.dp),
                enabled = !uiState.isScanning,
                colors = ButtonDefaults.buttonColors(
                    containerColor = Checkout,
                    contentColor = TextPrimary
                )
            ) {
                Text("Starten", style = MaterialTheme.typography.labelLarge)
            }

            Button(
                onClick = { viewModel.stopScanning() },
                modifier = Modifier
                    .weight(1f)
                    .padding(vertical = 12.dp),
                enabled = uiState.isScanning,
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.Error,
                    contentColor = TextPrimary
                )
            ) {
                Text("Stoppen", style = MaterialTheme.typography.labelLarge)
            }
        }

        StatusIndicator(
            isActive = uiState.isScanning,
            label = "Scanner",
            modifier = Modifier.padding(16.dp)
        )
    }
}
