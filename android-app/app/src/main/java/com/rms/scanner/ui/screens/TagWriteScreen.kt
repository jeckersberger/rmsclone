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
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.WriteTag
import com.rms.scanner.ui.viewmodels.TagWriteViewModel

@Composable
fun TagWriteScreen(
    viewModel: TagWriteViewModel,
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
                    text = "Tag schreiben",
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
            // Entity Type Selector
            Text(
                text = "Entitätstyp",
                style = MaterialTheme.typography.labelMedium,
                color = TextSecondary,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            Button(
                onClick = { showEntityTypeMenu = true },
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.SurfaceDark
                )
            ) {
                Text(
                    text = if (uiState.entityType == "asset") "Gerät" else "Lagerartikel",
                    modifier = Modifier.weight(1f),
                    style = MaterialTheme.typography.bodyMedium
                )
            }

            DropdownMenu(
                expanded = showEntityTypeMenu,
                onDismissRequest = { showEntityTypeMenu = false }
            ) {
                DropdownMenuItem(
                    text = { Text("Gerät") },
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

            // Asset/Instance Selector
            Text(
                text = if (uiState.entityType == "asset") "Gerät wählen" else "Lagerartikel wählen",
                style = MaterialTheme.typography.labelMedium,
                color = TextSecondary,
                modifier = Modifier.padding(top = 16.dp, bottom = 8.dp)
            )

            if (uiState.entityType == "asset") {
                Button(
                    onClick = { showAssetMenu = true },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = com.rms.scanner.ui.theme.SurfaceDark
                    )
                ) {
                    Text(
                        text = uiState.assets.find { it.id == uiState.selectedAssetId }?.name
                            ?: "Gerät wählen",
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
                    colors = ButtonDefaults.buttonColors(
                        containerColor = com.rms.scanner.ui.theme.SurfaceDark
                    )
                ) {
                    Text(
                        text = uiState.stockInstances.find { it.id == uiState.selectedInstanceId }?.item_name
                            ?: "Lagerartikel wählen",
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

            // EPC Input
            Text(
                text = "EPC-Code",
                style = MaterialTheme.typography.labelMedium,
                color = TextSecondary,
                modifier = Modifier.padding(top = 16.dp, bottom = 8.dp)
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 8.dp),
                horizontalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(8.dp)
            ) {
                OutlinedTextField(
                    value = uiState.newEpc,
                    onValueChange = { viewModel.updateNewEpc(it) },
                    label = { Text("EPC eingeben") },
                    modifier = Modifier.weight(1f),
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                        unfocusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary
                    ),
                    singleLine = true
                )
            }

            Button(
                onClick = { viewModel.generateAutoEpc() },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 8.dp),
                enabled = !uiState.isLoadingCompanyCode,
                colors = ButtonDefaults.buttonColors(
                    containerColor = com.rms.scanner.ui.theme.SurfaceDark,
                    contentColor = TextPrimary
                )
            ) {
                if (uiState.isLoadingCompanyCode) {
                    Text("Lade Firmencode...", style = MaterialTheme.typography.labelMedium)
                } else {
                    Text(
                        text = "Auto-EPC (Firma: ${uiState.companyCode})",
                        style = MaterialTheme.typography.labelMedium
                    )
                }
            }

            // Status Messages
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
                    color = com.rms.scanner.ui.theme.Success,
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            }

            // Write Button
            Button(
                onClick = { viewModel.writeTag() },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 24.dp),
                enabled = !uiState.isWriting && uiState.newEpc.isNotEmpty(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = WriteTag,
                    contentColor = TextPrimary
                )
            ) {
                Text("Tag beschreiben", style = MaterialTheme.typography.labelLarge)
            }
        }
    }
}

import androidx.compose.foundation.layout.Arrangement
