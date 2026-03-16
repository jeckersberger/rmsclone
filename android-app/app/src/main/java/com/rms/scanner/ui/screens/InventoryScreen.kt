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
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.components.StatusIndicator
import com.rms.scanner.ui.theme.Inventory
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.viewmodels.InventoryViewModel

@Composable
fun InventoryScreen(
    viewModel: InventoryViewModel,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

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
                    text = "Inventur",
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

        if (!uiState.isInventoryActive) {
            // Not started yet
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "Inventur nicht gestartet",
                    style = MaterialTheme.typography.titleMedium,
                    color = TextSecondary,
                    modifier = Modifier.padding(vertical = 24.dp)
                )

                Button(
                    onClick = { viewModel.startInventory() },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Inventory,
                        contentColor = TextPrimary
                    )
                ) {
                    Text("Inventur starten", style = MaterialTheme.typography.labelLarge)
                }
            }
        } else {
            // Inventory is active
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = "Scan-Zähler",
                    style = MaterialTheme.typography.titleMedium,
                    color = TextSecondary,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                Text(
                    text = "${uiState.scanCount} Tags gescannt",
                    style = MaterialTheme.typography.displaySmall,
                    color = Inventory,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(vertical = 24.dp)
                )

                Button(
                    onClick = { viewModel.completeInventory() },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 12.dp),
                    enabled = !uiState.isProcessing,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = com.rms.scanner.ui.theme.Checkin,
                        contentColor = TextPrimary
                    )
                ) {
                    Text("Inventur abschließen", style = MaterialTheme.typography.labelLarge)
                }

                Button(
                    onClick = { viewModel.cancelInventory() },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = com.rms.scanner.ui.theme.Error,
                        contentColor = TextPrimary
                    )
                ) {
                    Text("Abbrechen", style = MaterialTheme.typography.labelLarge)
                }

                StatusIndicator(
                    isActive = true,
                    label = "Inventur läuft",
                    modifier = Modifier.padding(top = 24.dp)
                )
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

        // Results
        if (uiState.foundCount > 0 || uiState.missingCount > 0 || uiState.unknownCount > 0) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = "Ergebnisse",
                    style = MaterialTheme.typography.titleMedium,
                    color = TextSecondary,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                ResultCounter(
                    label = "Gefunden",
                    count = uiState.foundCount,
                    color = com.rms.scanner.ui.theme.Success
                )

                ResultCounter(
                    label = "Vermisst",
                    count = uiState.missingCount,
                    color = com.rms.scanner.ui.theme.Error
                )

                ResultCounter(
                    label = "Unbekannt",
                    count = uiState.unknownCount,
                    color = com.rms.scanner.ui.theme.Warning
                )
            }
        }
    }
}

@Composable
fun ResultCounter(
    label: String,
    count: Int,
    color: androidx.compose.ui.graphics.Color
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(com.rms.scanner.ui.theme.SurfaceDark, androidx.compose.foundation.shape.RoundedCornerShape(8.dp))
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = TextSecondary,
            modifier = Modifier.weight(1f)
        )
        Text(
            text = count.toString(),
            style = MaterialTheme.typography.headlineMedium,
            color = color,
            fontWeight = FontWeight.Bold
        )
    }
}

import com.rms.scanner.ui.theme.TextSecondary
import androidx.compose.foundation.shape.RoundedCornerShape
