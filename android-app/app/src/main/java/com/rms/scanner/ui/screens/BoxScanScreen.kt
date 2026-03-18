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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.components.BoxResultGroup
import com.rms.scanner.ui.components.StatusIndicator
import com.rms.scanner.ui.theme.AssetColor
import com.rms.scanner.ui.theme.BoxScan
import com.rms.scanner.ui.theme.StockColor
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.UnknownColor
import com.rms.scanner.ui.viewmodels.BoxScanViewModel

@Composable
fun BoxScanScreen(
    viewModel: BoxScanViewModel,
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
                    text = "Kisten-Scan",
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

        // Tag Counter
        Text(
            text = "Gescannte Tags: ${uiState.scannedTags.size}",
            style = MaterialTheme.typography.headlineSmall,
            color = BoxScan,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(16.dp)
        )

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

        // Scan Results
        if (uiState.scanResults.isNotEmpty()) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = "Bewertungsergebnisse",
                    style = MaterialTheme.typography.titleMedium,
                    color = TextSecondary,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                val assetResults = uiState.scanResults
                    .filter { it.entity_type == "asset" }
                    .mapNotNull { it.entity_name }

                val stockResults = uiState.scanResults
                    .filter { it.entity_type == "stock" || it.entity_type == "stock_item" }
                    .mapNotNull { it.entity_name }

                val unknownResults = uiState.scanResults
                    .filter { it.entity_type == "unknown" }
                    .mapNotNull { it.tag }

                val partnerResults = uiState.partnerItems
                    .mapNotNull { it.entity_name ?: it.tag }

                BoxResultGroup(
                    title = "Geräte",
                    results = assetResults,
                    color = AssetColor,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                BoxResultGroup(
                    title = "Lagerartikel",
                    results = stockResults,
                    color = StockColor,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                BoxResultGroup(
                    title = "Unbekannt",
                    results = unknownResults,
                    color = UnknownColor,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                // Partner/foreign items section
                BoxResultGroup(
                    title = "Fremdgeräte",
                    results = partnerResults,
                    color = Color(0xFFE65100), // orange for partner items
                    modifier = Modifier.padding(bottom = 12.dp)
                )
            }
        }

        // Action Buttons
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(8.dp)
        ) {
            Button(
                onClick = { viewModel.startScanning() },
                modifier = Modifier
                    .weight(1f)
                    .padding(vertical = 12.dp),
                enabled = !uiState.isScanning,
                colors = ButtonDefaults.buttonColors(
                    containerColor = BoxScan,
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

        // Evaluate Button
        Button(
            onClick = { viewModel.evaluateBoxScan() },
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp)
                .padding(vertical = 12.dp),
            enabled = uiState.scannedTags.isNotEmpty() && !uiState.isProcessing,
            colors = ButtonDefaults.buttonColors(
                containerColor = com.rms.scanner.ui.theme.Primary,
                contentColor = TextPrimary
            )
        ) {
            Text("Auswerten", style = MaterialTheme.typography.labelLarge)
        }

        // Action Buttons for Results
        if (uiState.scanResults.isNotEmpty()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(8.dp)
            ) {
                Button(
                    onClick = { viewModel.applyCheckoutToAll() },
                    modifier = Modifier
                        .weight(1f)
                        .padding(vertical = 12.dp),
                    enabled = !uiState.isProcessing,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = com.rms.scanner.ui.theme.Checkout,
                        contentColor = TextPrimary
                    )
                ) {
                    Text("Alle ausleihen", style = MaterialTheme.typography.labelSmall)
                }

                Button(
                    onClick = { viewModel.applyCheckinToAll() },
                    modifier = Modifier
                        .weight(1f)
                        .padding(vertical = 12.dp),
                    enabled = !uiState.isProcessing,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = com.rms.scanner.ui.theme.Checkin,
                        contentColor = TextPrimary
                    )
                ) {
                    Text("Alle zurückgeben", style = MaterialTheme.typography.labelSmall)
                }
            }
        }

        // Reset Button
        Button(
            onClick = { viewModel.reset() },
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp)
                .padding(vertical = 12.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = com.rms.scanner.ui.theme.SurfaceDark,
                contentColor = TextPrimary
            )
        ) {
            Text("Zurücksetzen", style = MaterialTheme.typography.labelLarge)
        }

        StatusIndicator(
            isActive = uiState.isScanning,
            label = "Scanner",
            modifier = Modifier.padding(16.dp)
        )
    }
}
