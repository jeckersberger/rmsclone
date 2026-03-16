package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.rfid.RfidManager
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.Success
import com.rms.scanner.ui.theme.SurfaceDark
import com.rms.scanner.ui.viewmodels.CaseVerifyViewModel

@Composable
fun CaseVerifyScreen(
    viewModel: CaseVerifyViewModel,
    caseAssetId: Int,
    projectId: Int,
    rfidManager: RfidManager,
    onBack: () -> Unit,
    onSuccess: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    // Load case contents on first launch
    LaunchedEffect(caseAssetId) {
        viewModel.loadCaseContents(caseAssetId)
    }

    if (uiState.isLoading) {
        LoadingScreen()
    } else {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .background(SurfaceLight)
        ) {
            // Header with case name
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Kisten-Inhalt prüfen",
                        style = MaterialTheme.typography.displaySmall,
                        color = TextPrimary,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = uiState.caseInfo?.asset_tag ?: "Kiste",
                        style = MaterialTheme.typography.bodyMedium,
                        color = TextSecondary
                    )
                }
                Button(
                    onClick = onBack,
                    colors = ButtonDefaults.buttonColors(containerColor = SurfaceDark)
                ) {
                    Text("Zurück")
                }
            }

            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Progress bar
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    LinearProgressIndicator(
                        progress = { uiState.progress.coerceIn(0f, 1f) },
                        modifier = Modifier
                            .weight(1f)
                            .padding(vertical = 8.dp),
                        color = Success
                    )
                    Text(
                        text = "${(uiState.scannedItems.size)}/${uiState.expectedContents.size}",
                        style = MaterialTheme.typography.labelMedium,
                        color = TextPrimary,
                        fontWeight = FontWeight.Bold
                    )
                }

                // Expected contents list
                if (uiState.expectedContents.isNotEmpty()) {
                    Text(
                        text = "Erwartete Inhalte",
                        style = MaterialTheme.typography.labelMedium,
                        color = TextSecondary,
                        modifier = Modifier.padding(top = 8.dp)
                    )

                    uiState.expectedContents.forEach { item ->
                        val isScanned = item.id in uiState.scannedItems
                        ContentItemRow(
                            itemName = item.entity_name,
                            quantity = item.quantity,
                            isScanned = isScanned,
                            isRequired = item.is_required,
                            notes = item.notes
                        )
                    }
                }

                // Status messages
                if (uiState.errorMessage != null) {
                    Text(
                        text = "❌ ${uiState.errorMessage}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = androidx.compose.material3.tokens.ColorSchemeKeyTokens.Error,
                        modifier = Modifier.padding(vertical = 8.dp)
                    )
                }

                if (uiState.successMessage != null) {
                    Text(
                        text = "✓ ${uiState.successMessage}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Success,
                        modifier = Modifier.padding(vertical = 8.dp)
                    )
                }
            }

            // Action buttons
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Button(
                    onClick = { viewModel.resetScanning() },
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(containerColor = SurfaceDark)
                ) {
                    Text("Zurücksetzen")
                }

                Button(
                    onClick = { viewModel.completeVerification(projectId) },
                    modifier = Modifier.weight(1f),
                    enabled = !uiState.isSaving && uiState.scannedItems.isNotEmpty(),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = if (uiState.progress >= 1f) Success else SurfaceDark,
                        contentColor = TextPrimary
                    )
                ) {
                    if (uiState.isSaving) {
                        CircularProgressIndicator(
                            modifier = Modifier.then(androidx.compose.ui.Modifier.padding(4.dp)),
                            strokeWidth = 2.dp,
                            color = TextPrimary
                        )
                    } else {
                        Text("Prüfung abschließen")
                    }
                }
            }

            // Discrepancy dialog
            if (uiState.showDiscrepancyDialog && uiState.verificationResult != null) {
                AlertDialog(
                    onDismissRequest = { viewModel.continueScan() },
                    title = {
                        Text(
                            text = "⚠️ Abweichungen erkannt",
                            color = TextPrimary
                        )
                    },
                    text = {
                        Column(
                            modifier = Modifier.verticalScroll(rememberScrollState()),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            if (uiState.missingItems.isNotEmpty()) {
                                Text(
                                    text = "Fehlend (${uiState.missingItems.size}):",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = androidx.compose.material3.tokens.ColorSchemeKeyTokens.Error,
                                    fontWeight = FontWeight.Bold
                                )
                                uiState.missingItems.forEach { item ->
                                    Text(
                                        text = "• ${item.entity_name} (Menge: ${item.quantity})",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = TextPrimary,
                                        modifier = Modifier.padding(start = 8.dp)
                                    )
                                }
                            }

                            if (uiState.extraItems.isNotEmpty()) {
                                Text(
                                    text = "Zusätzliche (${uiState.extraItems.size}):",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = Color(0xFFFFB74D),
                                    fontWeight = FontWeight.Bold
                                )
                                uiState.extraItems.forEach { item ->
                                    Text(
                                        text = "• ${item["name"] ?: "Unbekannt"}",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = TextPrimary,
                                        modifier = Modifier.padding(start = 8.dp)
                                    )
                                }
                            }

                            if (uiState.swappedItems.isNotEmpty()) {
                                Text(
                                    text = "Vertauscht (${uiState.swappedItems.size}):",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = Color(0xFF42A5F5),
                                    fontWeight = FontWeight.Bold
                                )
                                uiState.swappedItems.forEach { item ->
                                    Text(
                                        text = "• ${item["name"] ?: "Unbekannt"}",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = TextPrimary,
                                        modifier = Modifier.padding(start = 8.dp)
                                    )
                                }
                            }
                        }
                    },
                    confirmButton = {
                        Button(
                            onClick = {
                                viewModel.acknowledgeDiscrepancy()
                                onSuccess()
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Success)
                        ) {
                            Text("Trotzdem bestätigen")
                        }
                    },
                    dismissButton = {
                        TextButton(
                            onClick = { viewModel.continueScan() }
                        ) {
                            Text("Weiter scannen")
                        }
                    },
                    modifier = Modifier.background(SurfaceDark)
                )
            }
        }
    }
}

@Composable
private fun ContentItemRow(
    itemName: String,
    quantity: Int,
    isScanned: Boolean,
    isRequired: Boolean,
    notes: String?
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(SurfaceDark)
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            // Status checkbox
            Checkbox(
                checked = isScanned,
                onCheckedChange = { },
                enabled = false,
                colors = CheckboxDefaults.colors(
                    checkedColor = Success,
                    uncheckedColor = TextSecondary,
                    disabledCheckedColor = Success,
                    disabledUncheckedColor = TextSecondary
                )
            )

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = itemName,
                    style = MaterialTheme.typography.bodyMedium,
                    color = TextPrimary,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = "Menge: $quantity${if (isRequired) " (erforderlich)" else ""}",
                    style = MaterialTheme.typography.bodySmall,
                    color = TextSecondary
                )
            }

            // Status indicator
            Text(
                text = if (isScanned) "✓" else "○",
                style = MaterialTheme.typography.labelLarge,
                color = if (isScanned) Success else TextSecondary,
                fontWeight = FontWeight.Bold
            )
        }

        // Notes if present
        if (!notes.isNullOrBlank()) {
            Text(
                text = "Notiz: $notes",
                style = MaterialTheme.typography.labelSmall,
                color = TextSecondary,
                modifier = Modifier.padding(start = 40.dp)
            )
        }
    }
}

@Composable
private fun LoadingScreen() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        CircularProgressIndicator(color = Success)
        Text(
            text = "Kisten-Inhalt wird geladen...",
            style = MaterialTheme.typography.bodyMedium,
            color = TextPrimary,
            modifier = Modifier.padding(top = 16.dp)
        )
    }
}
