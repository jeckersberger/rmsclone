package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.data.api.models.Location
import com.rms.scanner.ui.theme.Error
import com.rms.scanner.ui.theme.SurfaceDark
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.Success
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.TextTertiary
import com.rms.scanner.ui.viewmodels.LocationViewModel

@Composable
fun LocationScreen(
    viewModel: LocationViewModel,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
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
                    text = "Umlagern",
                    style = MaterialTheme.typography.displaySmall,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            }
            Button(
                onClick = onBack,
                colors = ButtonDefaults.buttonColors(
                    containerColor = SurfaceDark
                )
            ) {
                Text("Zurück")
            }
        }

        // Main Content
        LazyColumn(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Location Selection Section
            item {
                Column {
                    Text(
                        text = "Lagerort wählen",
                        style = MaterialTheme.typography.titleMedium,
                        color = TextSecondary,
                        modifier = Modifier.padding(bottom = 8.dp)
                    )

                    // Location Dropdown
                    LocationDropdown(
                        locations = uiState.locations,
                        selectedLocationId = uiState.selectedLocationId,
                        onLocationSelected = { viewModel.selectLocation(it) },
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            }

            // Custom Location Section
            item {
                TextField(
                    value = uiState.customLocation,
                    onValueChange = { viewModel.setCustomLocation(it) },
                    modifier = Modifier.fillMaxWidth(),
                    placeholder = {
                        Text("Oder Freitext-Standort eingeben...")
                    },
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = SurfaceDark,
                        unfocusedContainerColor = SurfaceDark,
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedPlaceholderColor = TextTertiary,
                        unfocusedPlaceholderColor = TextTertiary
                    ),
                    singleLine = true
                )
            }

            // Notes Section
            item {
                TextField(
                    value = uiState.notes,
                    onValueChange = { viewModel.setNotes(it) },
                    modifier = Modifier.fillMaxWidth(),
                    placeholder = {
                        Text("Notizen (optional)")
                    },
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = SurfaceDark,
                        unfocusedContainerColor = SurfaceDark,
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedPlaceholderColor = TextTertiary,
                        unfocusedPlaceholderColor = TextTertiary
                    ),
                    singleLine = true
                )
            }

            // Loading indicator
            if (uiState.isLoading) {
                item {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.Center
                    ) {
                        CircularProgressIndicator(
                            color = Color(0xFF10B981)
                        )
                    }
                }
            }

            // Last Scan Result
            if (uiState.lastResult != null) {
                item {
                    ScanResultCard(
                        result = uiState.lastResult!!,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            }

            // Scan Status Counter
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = SurfaceDark
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.SpaceEvenly,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Text(
                                text = uiState.scannedTags.size.toString(),
                                style = MaterialTheme.typography.headlineMedium,
                                color = Color(0xFF10B981),
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                text = "Tags gescannt",
                                style = MaterialTheme.typography.bodySmall,
                                color = TextTertiary
                            )
                        }

                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Text(
                                text = uiState.totalRelocated.toString(),
                                style = MaterialTheme.typography.headlineMedium,
                                color = Success,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                text = "umgelagert",
                                style = MaterialTheme.typography.bodySmall,
                                color = TextTertiary
                            )
                        }

                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Text(
                                text = uiState.totalErrors.toString(),
                                style = MaterialTheme.typography.headlineMedium,
                                color = Error,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                text = "Fehler",
                                style = MaterialTheme.typography.bodySmall,
                                color = TextTertiary
                            )
                        }
                    }
                }
            }

            // Error Message
            if (uiState.errorMessage != null) {
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = Color(0x33DC2626)
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text(
                            text = "❌ ${uiState.errorMessage}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Error,
                            modifier = Modifier.padding(16.dp)
                        )
                    }
                }
            }

            // Scanned Tags List
            if (uiState.scannedTags.isNotEmpty()) {
                item {
                    Text(
                        text = "Gescannte Tags",
                        style = MaterialTheme.typography.titleMedium,
                        color = TextSecondary,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                }

                items(uiState.scannedTags) { tag ->
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(4.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = SurfaceDark
                        ),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = tag,
                                style = MaterialTheme.typography.bodyMedium,
                                color = TextPrimary,
                                modifier = Modifier.weight(1f)
                            )
                            Icon(
                                imageVector = Icons.Default.Check,
                                contentDescription = "Success",
                                tint = Success,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                    }
                }
            }
        }

        // Bottom Control Buttons
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Button(
                onClick = { viewModel.startScanning() },
                modifier = Modifier
                    .weight(1f)
                    .padding(vertical = 12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFF10B981),
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
                colors = ButtonDefaults.buttonColors(
                    containerColor = Error,
                    contentColor = TextPrimary
                )
            ) {
                Text("Stoppen", style = MaterialTheme.typography.labelLarge)
            }

            Button(
                onClick = { viewModel.resetScan() },
                modifier = Modifier
                    .weight(1f)
                    .padding(vertical = 12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Error,
                    contentColor = TextPrimary
                )
            ) {
                Text("Zurücksetzen", style = MaterialTheme.typography.labelLarge)
            }
        }
    }
}

@Composable
fun LocationDropdown(
    locations: List<Location>,
    selectedLocationId: Int?,
    onLocationSelected: (Int?) -> Unit,
    modifier: Modifier = Modifier
) {
    var expanded by remember { mutableStateOf(false) }

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { expanded = !expanded },
        modifier = modifier
    ) {
        TextField(
            readOnly = true,
            value = locations.find { it.id == selectedLocationId }?.name ?: "Wählen Sie einen Lagerort",
            onValueChange = {},
            trailingIcon = {
                ExposedDropdownMenuDefaults.TrailingIcon(
                    expanded = expanded
                )
            },
            colors = TextFieldDefaults.colors(
                focusedContainerColor = SurfaceDark,
                unfocusedContainerColor = SurfaceDark,
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary,
                focusedTrailingIconColor = TextPrimary,
                unfocusedTrailingIconColor = TextPrimary
            ),
            modifier = Modifier
                .menuAnchor()
                .fillMaxWidth()
        )

        ExposedDropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false }
        ) {
            locations.forEach { location ->
                DropdownMenuItem(
                    text = {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(16.dp)
                                    .background(
                                        color = parseColorString(location.color),
                                        shape = CircleShape
                                    )
                            )
                            Text(
                                text = location.name,
                                color = TextPrimary
                            )
                        }
                    },
                    onClick = {
                        onLocationSelected(location.id)
                        expanded = false
                    }
                )
            }
        }
    }
}

@Composable
fun ScanResultCard(
    result: ScanResultInfo,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = if (result.success) Color(0x3310B981) else Color(0x33DC2626)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text(
                    text = result.displayName,
                    style = MaterialTheme.typography.titleMedium,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f)
                )
                Icon(
                    imageVector = if (result.success) Icons.Default.Check else Icons.Default.Close,
                    contentDescription = if (result.success) "Success" else "Error",
                    tint = if (result.success) Success else Error,
                    modifier = Modifier.size(24.dp)
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    text = result.entityType.uppercase(),
                    style = MaterialTheme.typography.labelSmall,
                    color = TextTertiary,
                    modifier = Modifier
                        .background(
                            color = SurfaceDark,
                            shape = RoundedCornerShape(4.dp)
                        )
                        .padding(4.dp)
                )

                Icon(
                    imageVector = Icons.Default.ArrowForward,
                    contentDescription = "Arrow",
                    tint = TextSecondary,
                    modifier = Modifier.size(16.dp)
                )

                Text(
                    text = result.locationName,
                    style = MaterialTheme.typography.bodyMedium,
                    color = TextPrimary,
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

fun parseColorString(colorString: String): Color {
    return try {
        val hexColor = colorString.removePrefix("#")
        when (hexColor.length) {
            6 -> Color(0xFF000000 or hexColor.toLong(16))
            8 -> Color(hexColor.toLong(16))
            else -> Color.Gray
        }
    } catch (e: Exception) {
        Color.Gray
    }
}
