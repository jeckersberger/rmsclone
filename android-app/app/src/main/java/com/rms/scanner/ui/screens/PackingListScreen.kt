package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.RadioButtonUnchecked
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.foundation.layout.height
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.input.pointer.PointerEventPass
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.rfid.RfidManager
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.TextTertiary
import com.rms.scanner.ui.viewmodels.PackingItem
import com.rms.scanner.ui.viewmodels.PackingListViewModel
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.IconButton
import androidx.compose.ui.graphics.Color

@Composable
fun PackingListScreen(
    viewModel: PackingListViewModel,
    rfidManager: RfidManager,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
            .padding(16.dp)
    ) {
        // Header with back button
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(onClick = onBack) {
                Icon(
                    imageVector = Icons.Filled.ArrowBack,
                    contentDescription = "Zurück",
                    tint = TextPrimary
                )
            }
            Text(
                text = "Packliste",
                style = MaterialTheme.typography.displaySmall,
                color = TextPrimary,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.weight(1f)
            )
        }

        // Project selector
        if (uiState.isLoading && uiState.projects.isEmpty()) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 24.dp),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator(color = TextPrimary)
            }
        } else {
            ProjectSelector(
                projects = uiState.projects,
                selectedProjectId = uiState.selectedProjectId,
                onProjectSelected = { projectId ->
                    viewModel.selectProject(projectId)
                }
            )

            // Progress bar
            if (uiState.selectedProjectId != null) {
                ProgressSection(
                    totalItems = uiState.totalItems,
                    checkedItems = uiState.checkedItems
                )

                // Scan input field
                ScanInputField { scanValue ->
                    viewModel.handleRfidScan(scanValue)
                }

                // Items list
                PackingItemsList(
                    assets = uiState.assets,
                    stockInstances = uiState.stockInstances,
                    externalItems = uiState.externalItems,
                    onItemCheck = { item ->
                        viewModel.checkItemManual(item)
                    }
                )
            }
        }

        // Error message
        if (!uiState.errorMessage.isNullOrEmpty()) {
            Text(
                text = uiState.errorMessage ?: "",
                color = Color(0xFFDC2626),
                style = MaterialTheme.typography.bodySmall,
                modifier = Modifier.padding(top = 8.dp)
            )
        }

        // Last checked message
        if (!uiState.lastCheckedItem.isNullOrEmpty()) {
            Text(
                text = uiState.lastCheckedItem ?: "",
                color = Color(0xFF16A34A),
                style = MaterialTheme.typography.bodySmall,
                modifier = Modifier.padding(top = 8.dp)
            )
        }
    }
}

@Composable
private fun ProjectSelector(
    projects: List<com.rms.scanner.data.api.models.ProjectItem>,
    selectedProjectId: Int?,
    onProjectSelected: (Int) -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedProject = projects.find { it.id == selectedProjectId }

    Box(modifier = Modifier.fillMaxWidth()) {
        TextButton(
            onClick = { expanded = true },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
                .background(Color(0xFF374151))
        ) {
            Text(
                text = selectedProject?.name ?: "Projekt wählen",
                color = TextPrimary,
                style = MaterialTheme.typography.bodyLarge
            )
        }

        DropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
            modifier = Modifier
                .fillMaxWidth()
                .background(Color(0xFF1F2937))
        ) {
            projects.forEach { project ->
                DropdownMenuItem(
                    text = { Text(project.name) },
                    onClick = {
                        onProjectSelected(project.id)
                        expanded = false
                    }
                )
            }
        }
    }
}

@Composable
private fun ProgressSection(
    totalItems: Int,
    checkedItems: Int
) {
    val percentage = if (totalItems > 0) (checkedItems * 100) / totalItems else 0

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(bottom = 16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Fortschritt",
                style = MaterialTheme.typography.titleMedium,
                color = TextPrimary,
                fontWeight = FontWeight.Bold
            )
            Text(
                text = "$checkedItems / $totalItems ($percentage%)",
                style = MaterialTheme.typography.titleMedium,
                color = Color(0xFF06B6D4),
                fontWeight = FontWeight.Bold
            )
        }

        LinearProgressIndicator(
            progress = { if (totalItems > 0) checkedItems.toFloat() / totalItems else 0f },
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp),
            color = Color(0xFF06B6D4),
            trackColor = Color(0xFF374151)
        )
    }
}

@Composable
private fun ScanInputField(
    onScan: (String) -> Unit
) {
    var scanValue by remember { mutableStateOf("") }

    TextField(
        value = scanValue,
        onValueChange = { newValue ->
            scanValue = newValue
            if (newValue.isNotEmpty()) {
                onScan(newValue)
                scanValue = ""
            }
        },
        placeholder = { Text("Scannen zum Abhaken", color = TextTertiary) },
        modifier = Modifier
            .fillMaxWidth()
            .padding(bottom = 16.dp),
        colors = TextFieldDefaults.colors(
            focusedContainerColor = Color(0xFF1F2937),
            unfocusedContainerColor = Color(0xFF1F2937),
            focusedTextColor = TextPrimary,
            unfocusedTextColor = TextPrimary,
            cursorColor = Color(0xFF06B6D4)
        ),
        textStyle = MaterialTheme.typography.bodyLarge
    )
}

@Composable
private fun PackingItemsList(
    assets: List<PackingItem>,
    stockInstances: List<PackingItem>,
    externalItems: List<PackingItem>,
    onItemCheck: (PackingItem) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        if (assets.isNotEmpty()) {
            item {
                SectionHeader(
                    title = "Geräte",
                    checkedCount = assets.count { it.checked },
                    totalCount = assets.size
                )
            }
            items(assets) { item ->
                PackingItemRow(item = item, onItemCheck = onItemCheck)
            }
        }

        if (stockInstances.isNotEmpty()) {
            item {
                SectionHeader(
                    title = "Artikel",
                    checkedCount = stockInstances.count { it.checked },
                    totalCount = stockInstances.size
                )
            }
            items(stockInstances) { item ->
                PackingItemRow(item = item, onItemCheck = onItemCheck)
            }
        }

        if (externalItems.isNotEmpty()) {
            item {
                SectionHeader(
                    title = "Fremdmaterial",
                    checkedCount = externalItems.count { it.checked },
                    totalCount = externalItems.size
                )
            }
            items(externalItems) { item ->
                PackingItemRow(item = item, onItemCheck = onItemCheck)
            }
        }
    }
}

@Composable
private fun SectionHeader(
    title: String,
    checkedCount: Int,
    totalCount: Int
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(Color(0xFF374151))
            .padding(12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
            color = TextPrimary,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "$checkedCount / $totalCount",
            style = MaterialTheme.typography.bodySmall,
            color = TextSecondary
        )
    }
}

@Composable
private fun PackingItemRow(
    item: PackingItem,
    onItemCheck: (PackingItem) -> Unit
) {
    var isPressed by remember { mutableStateOf(false) }
    var pressProgress by remember { mutableStateOf(0f) }
    val LONG_PRESS_DURATION = 3000L

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                if (item.checked) Color(0xFF065F46) else Color(0xFF1F2937)
            )
            .padding(12.dp)
            .pointerInput(item.id) {
                detectTapGestures(
                    onLongPress = {
                        isPressed = true
                        pressProgress = 0f
                    },
                    onPress = {
                        val startTime = System.currentTimeMillis()
                        while (isPressed) {
                            val elapsed = System.currentTimeMillis() - startTime
                            pressProgress = (elapsed.toFloat() / LONG_PRESS_DURATION).coerceIn(0f, 1f)
                            if (elapsed >= LONG_PRESS_DURATION) {
                                onItemCheck(item)
                                isPressed = false
                                pressProgress = 0f
                                break
                            }
                        }
                    }
                )
            },
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Checkbox
        Box(
            modifier = Modifier
                .padding(4.dp),
            contentAlignment = Alignment.Center
        ) {
            if (item.checked) {
                Icon(
                    imageVector = Icons.Filled.CheckCircle,
                    contentDescription = "Checked",
                    tint = Color(0xFF10B981),
                    modifier = Modifier.padding(4.dp)
                )
            } else {
                Icon(
                    imageVector = Icons.Filled.RadioButtonUnchecked,
                    contentDescription = "Unchecked",
                    tint = TextSecondary,
                    modifier = Modifier.padding(4.dp)
                )
            }

            if (isPressed && pressProgress > 0f) {
                LinearProgressIndicator(
                    progress = { pressProgress },
                    color = Color(0xFF06B6D4),
                    modifier = Modifier
                        .fillMaxWidth(0.3f)
                        .height(2.dp)
                        .align(Alignment.Center)
                )
            }
        }

        Column(
            modifier = Modifier.weight(1f)
        ) {
            Text(
                text = item.displayName,
                style = MaterialTheme.typography.bodyLarge,
                color = TextPrimary,
                fontWeight = FontWeight.SemiBold
            )
            if (item.scanCode.isNotEmpty()) {
                Text(
                    text = item.scanCode,
                    style = MaterialTheme.typography.bodySmall,
                    color = TextTertiary
                )
            }
        }

        if (item.checked) {
            Text(
                text = "✓",
                style = MaterialTheme.typography.headlineSmall,
                color = Color(0xFF10B981)
            )
        }
    }
}
