package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.Nfc
import androidx.compose.material.icons.filled.Save
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.components.StatusIndicator
import com.rms.scanner.ui.theme.*
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
    ) {
        // Header
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(SurfaceDark)
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(onClick = onBack) {
                Icon(Icons.Filled.ArrowBack, "Zurueck", tint = TextSecondary)
            }
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(RoundedCornerShape(8.dp))
                    .background(TagPair.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.Nfc, "Tag zuordnen", tint = TagPair, modifier = Modifier.size(20.dp))
            }
            Spacer(modifier = Modifier.width(10.dp))
            Column {
                Text(
                    text = "Tag zuordnen",
                    style = MaterialTheme.typography.headlineMedium,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "RFID-Tag scannen und zuordnen",
                    style = MaterialTheme.typography.bodySmall,
                    color = TextSecondary
                )
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Step 1: Scan Tag
            Card(
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = SurfaceCard),
                elevation = CardDefaults.cardElevation(1.dp)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(24.dp)
                                .clip(RoundedCornerShape(6.dp))
                                .background(Accent.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("1", style = MaterialTheme.typography.labelSmall, color = Accent, fontWeight = FontWeight.Bold)
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("RFID-Tag scannen", style = MaterialTheme.typography.titleMedium, color = TextPrimary, fontWeight = FontWeight.Bold)
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    OutlinedTextField(
                        value = uiState.scannedTid,
                        onValueChange = {},
                        label = { Text("Gescannte TID") },
                        modifier = Modifier.fillMaxWidth(),
                        readOnly = true,
                        colors = TextFieldDefaults.colors(
                            focusedContainerColor = SurfaceVariant,
                            unfocusedContainerColor = SurfaceVariant,
                            focusedTextColor = TextPrimary,
                            unfocusedTextColor = TextPrimary,
                            focusedIndicatorColor = Accent,
                            unfocusedIndicatorColor = SurfaceBorder
                        ),
                        singleLine = true
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    Button(
                        onClick = { if (uiState.isScanning) viewModel.stopTidScan() else viewModel.startTidScan() },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (uiState.isScanning) Error else TagPair,
                            contentColor = TextOnAccent
                        )
                    ) {
                        Icon(Icons.Filled.Nfc, null, modifier = Modifier.size(20.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(if (uiState.isScanning) "Stop" else "Tag scannen")
                    }

                    if (uiState.tidLookupResult != null) {
                        Text(
                            text = uiState.tidLookupResult!!,
                            style = MaterialTheme.typography.bodySmall,
                            color = if (uiState.tidLookupResult!!.startsWith("TID frei")) Success else Warning,
                            modifier = Modifier.padding(top = 6.dp)
                        )
                    }
                }
            }

            // Step 2: Select Entity
            Card(
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = SurfaceCard),
                elevation = CardDefaults.cardElevation(1.dp)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier.size(24.dp).clip(RoundedCornerShape(6.dp)).background(Accent.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("2", style = MaterialTheme.typography.labelSmall, color = Accent, fontWeight = FontWeight.Bold)
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Geraet / Artikel waehlen", style = MaterialTheme.typography.titleMedium, color = TextPrimary, fontWeight = FontWeight.Bold)
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    Button(
                        onClick = { showEntityTypeMenu = true },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = SurfaceVariant)
                    ) {
                        Text(
                            text = if (uiState.entityType == "asset") "Geraet" else "Lagerartikel",
                            modifier = Modifier.weight(1f),
                            style = MaterialTheme.typography.bodyMedium,
                            color = TextPrimary
                        )
                    }
                    DropdownMenu(expanded = showEntityTypeMenu, onDismissRequest = { showEntityTypeMenu = false }) {
                        DropdownMenuItem(text = { Text("Geraet") }, onClick = { viewModel.selectEntityType("asset"); showEntityTypeMenu = false })
                        DropdownMenuItem(text = { Text("Lagerartikel") }, onClick = { viewModel.selectEntityType("stock"); showEntityTypeMenu = false })
                    }

                    Spacer(modifier = Modifier.height(8.dp))

                    if (uiState.entityType == "asset") {
                        Button(
                            onClick = { showAssetMenu = true },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SurfaceVariant)
                        ) {
                            Text(
                                text = uiState.assets.find { it.id == uiState.selectedAssetId }?.name ?: "Geraet waehlen",
                                modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium, color = TextPrimary
                            )
                        }
                        DropdownMenu(expanded = showAssetMenu, onDismissRequest = { showAssetMenu = false }) {
                            uiState.assets.forEach { asset ->
                                DropdownMenuItem(text = { Text(asset.name) }, onClick = { viewModel.selectAsset(asset.id); showAssetMenu = false })
                            }
                        }
                    } else {
                        Button(
                            onClick = { showInstanceMenu = true },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SurfaceVariant)
                        ) {
                            Text(
                                text = uiState.stockInstances.find { it.id == uiState.selectedInstanceId }?.item_name ?: "Lagerartikel waehlen",
                                modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium, color = TextPrimary
                            )
                        }
                        DropdownMenu(expanded = showInstanceMenu, onDismissRequest = { showInstanceMenu = false }) {
                            uiState.stockInstances.forEach { instance ->
                                DropdownMenuItem(text = { Text(instance.item_name ?: "Unbekannt") }, onClick = { viewModel.selectInstance(instance.id); showInstanceMenu = false })
                            }
                        }
                    }
                }
            }

            // Step 3: Pair/Unpair
            Card(
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = SurfaceCard),
                elevation = CardDefaults.cardElevation(1.dp)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier.size(24.dp).clip(RoundedCornerShape(6.dp)).background(Accent.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("3", style = MaterialTheme.typography.labelSmall, color = Accent, fontWeight = FontWeight.Bold)
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Zuordnen", style = MaterialTheme.typography.titleMedium, color = TextPrimary, fontWeight = FontWeight.Bold)
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Button(
                            onClick = { viewModel.pairTid() },
                            modifier = Modifier.weight(1f).height(48.dp),
                            enabled = !uiState.isPairing && uiState.scannedTid.isNotEmpty(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Success, contentColor = TextOnAccent)
                        ) {
                            Icon(Icons.Filled.Save, null, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("Zuordnen", style = MaterialTheme.typography.labelLarge)
                        }

                        Button(
                            onClick = { viewModel.unpairTid() },
                            modifier = Modifier.height(48.dp),
                            enabled = !uiState.isPairing,
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Error, contentColor = TextOnAccent)
                        ) {
                            Icon(Icons.Filled.LinkOff, null, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(4.dp))
                            Text("Entfernen", style = MaterialTheme.typography.labelLarge)
                        }
                    }
                }
            }

            // Status Messages
            if (uiState.successMessage != null) {
                Card(shape = RoundedCornerShape(8.dp), colors = CardDefaults.cardColors(containerColor = Success.copy(alpha = 0.12f))) {
                    Text(text = uiState.successMessage!!, style = MaterialTheme.typography.bodyMedium, color = Success, fontWeight = FontWeight.Medium, modifier = Modifier.padding(12.dp))
                }
            }
            if (uiState.errorMessage != null) {
                Card(shape = RoundedCornerShape(8.dp), colors = CardDefaults.cardColors(containerColor = Error.copy(alpha = 0.12f))) {
                    Text(text = uiState.errorMessage!!, style = MaterialTheme.typography.bodyMedium, color = Error, fontWeight = FontWeight.Medium, modifier = Modifier.padding(12.dp))
                }
            }

            StatusIndicator(isActive = uiState.isScanning, label = "RFID Scanner")
        }
    }
}
