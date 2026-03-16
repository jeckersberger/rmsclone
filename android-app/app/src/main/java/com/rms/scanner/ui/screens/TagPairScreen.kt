package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.SurfaceDark
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.Success
import com.rms.scanner.ui.viewmodels.TagPairViewModel

@Composable
fun TagPairScreen(
    viewModel: TagPairViewModel,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    var showEntityTypeMenu by remember { mutableStateOf(false) }
    var showAssetMenu by remember { mutableStateOf(false) }
    var showInstanceMenu by remember { mutableStateOf(false) }

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
                    text = "Tag zuordnen",
                    style = MaterialTheme.typography.displaySmall,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "RFID-Tag scannen und einem Geraet zuordnen",
                    style = MaterialTheme.typography.bodySmall,
                    color = TextSecondary
                )
            }
            Button(
                onClick = onBack,
                colors = ButtonDefaults.buttonColors(containerColor = SurfaceDark)
            ) {
                Text("Zurueck")
            }
        }

        Column(modifier = Modifier.padding(16.dp)) {
            // ── Step 1: Scan Tag ──
            Text(
                text = "1. RFID-Tag scannen",
                style = MaterialTheme.typography.titleMedium,
                color = TextPrimary,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedTextField(
                    value = uiState.scannedTid,
                    onValueChange = { /* read-only, filled by scanner */ },
                    label = { Text("Gescannte TID") },
                    modifier = Modifier.weight(1f),
                    readOnly = true,
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = SurfaceDark,
                        unfocusedContainerColor = SurfaceDark,
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary
                    ),
                    singleLine = true
                )

                Button(
                    onClick = {
                        if (uiState.isScanning) viewModel.stopTidScan()
                        else viewModel.startTidScan()
                    },
                    colors = ButtonDefaults.buttonColors(
                        containerColor = if (uiState.isScanning)
                            androidx.compose.ui.graphics.Color(0xFFDC3545)
                        else
                            androidx.compose.ui.graphics.Color(0xFF007BFF)
                    )
                ) {
                    Text(if (uiState.isScanning) "Stop" else "Scannen")
                }
            }

            // TID lookup info
            if (uiState.tidLookupResult != null) {
                Text(
                    text = uiState.tidLookupResult!!,
                    style = MaterialTheme.typography.bodySmall,
                    color = TextSecondary,
                    modifier = Modifier.padding(top = 4.dp, bottom = 8.dp)
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // ── Step 2: Select Entity ──
            Text(
                text = "2. Geraet / Artikel waehlen",
                style = MaterialTheme.typography.titleMedium,
                color = TextPrimary,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            // Entity Type Selector
            Button(
                onClick = { showEntityTypeMenu = true },
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(containerColor = SurfaceDark)
            ) {
                Text(
                    text = if (uiState.entityType == "asset") "Geraet" else "Lagerartikel",
                    modifier = Modifier.weight(1f),
                    style = MaterialTheme.typography.bodyMedium
                )
            }

            DropdownMenu(
                expanded = showEntityTypeMenu,
                onDismissRequest = { showEntityTypeMenu = false }
            ) {
                DropdownMenuItem(
                    text = { Text("Geraet") },
                    onClick = {
                        viewModel.selectEntityType("asset")
                        showEntityTypeMenu = false
                    }
                )
                DropdownMenuItem(
                    text = { Text("Lagerartikel") },
                    onClick = {
                        viewModel.selectEntityType("stock")
                        showEntityTypeMenu = false
                    }
                )
            }

            Spacer(modifier = Modifier.height(8.dp))

            // Asset/Instance Selector
            if (uiState.entityType == "asset") {
                Button(
                    onClick = { showAssetMenu = true },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = SurfaceDark)
                ) {
                    Text(
                        text = uiState.assets.find { it.id == uiState.selectedAssetId }?.name
                            ?: "Geraet waehlen",
                        modifier = Modifier.weight(1f),
                        style = MaterialTheme.typography.bodyMedium
                    )
                }

                DropdownMenu(
                    expanded = showAssetMenu,
                    onDismissRequest = { showAssetMenu = false }
                ) {
                    uiState.assets.forEach { asset ->
                        DropdownMenuItem(
                            text = { Text(asset.name) },
                            onClick = {
                                viewModel.selectAsset(asset.id)
                                showAssetMenu = false
                            }
                        )
                    }
                }
            } else {
                Button(
                    onClick = { showInstanceMenu = true },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = SurfaceDark)
                ) {
                    Text(
                        text = uiState.stockInstances.find { it.id == uiState.selectedInstanceId }?.item_name
                            ?: "Lagerartikel waehlen",
                        modifier = Modifier.weight(1f),
                        style = MaterialTheme.typography.bodyMedium
                    )
                }

                DropdownMenu(
                    expanded = showInstanceMenu,
                    onDismissRequest = { showInstanceMenu = false }
                ) {
                    uiState.stockInstances.forEach { instance ->
                        DropdownMenuItem(
                            text = { Text(instance.item_name ?: "Unbekannt") },
                            onClick = {
                                viewModel.selectInstance(instance.id)
                                showInstanceMenu = false
                            }
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Status Messages
            if (uiState.errorMessage != null) {
                Text(
                    text = "Fehler: ${uiState.errorMessage}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = androidx.compose.ui.graphics.Color(0xFFDC3545),
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            }

            if (uiState.successMessage != null) {
                Text(
                    text = uiState.successMessage!!,
                    style = MaterialTheme.typography.bodyMedium,
                    color = Success,
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            }

            // ── Step 3: Pair / Unpair ──
            Text(
                text = "3. Zuordnen",
                style = MaterialTheme.typography.titleMedium,
                color = TextPrimary,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Button(
                    onClick = { viewModel.pairTid() },
                    modifier = Modifier.weight(1f),
                    enabled = !uiState.isPairing && uiState.scannedTid.isNotEmpty(),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Success,
                        contentColor = TextPrimary
                    )
                ) {
                    Text(
                        "Tag zuordnen",
                        style = MaterialTheme.typography.labelLarge
                    )
                }

                Button(
                    onClick = { viewModel.unpairTid() },
                    enabled = !uiState.isPairing,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = androidx.compose.ui.graphics.Color(0xFFDC3545),
                        contentColor = TextPrimary
                    )
                ) {
                    Text(
                        "Entfernen",
                        style = MaterialTheme.typography.labelLarge
                    )
                }
            }
        }
    }
}
