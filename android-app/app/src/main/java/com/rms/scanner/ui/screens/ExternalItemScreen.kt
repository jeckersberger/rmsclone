package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import com.rms.scanner.data.api.models.ExternalItem
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.TextTertiary
import com.rms.scanner.ui.viewmodels.ExternalItemViewModel
import androidx.compose.ui.graphics.Color
import kotlin.math.roundToInt

@Composable
fun ExternalItemScreen(
    viewModel: ExternalItemViewModel,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
            .padding(16.dp)
    ) {
        // Header
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
                text = "Fremdmaterial erfassen",
                style = MaterialTheme.typography.displaySmall,
                color = TextPrimary,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.weight(1f)
            )
        }

        LazyColumn(
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                ExternalItemForm(
                    uiState = uiState,
                    onDescriptionChange = { viewModel.updateDescription(it) },
                    onOwnerNameChange = { viewModel.updateOwnerName(it) },
                    onOwnerContactChange = { viewModel.updateOwnerContact(it) },
                    onQuantityChange = { viewModel.updateQuantity(it) },
                    onProjectSelect = { viewModel.selectProject(it) },
                    onReturnDateChange = { viewModel.updateReturnDate(it) },
                    onNotesChange = { viewModel.updateNotes(it) }
                )
            }

            item {
                Button(
                    onClick = { viewModel.saveExternalItem() },
                    enabled = !uiState.isSaving,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color(0xFFEC4899),
                        contentColor = TextPrimary
                    )
                ) {
                    Text(
                        text = if (uiState.isSaving) "Wird gespeichert..." else "Erfassen",
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(vertical = 8.dp)
                    )
                }
            }

            if (!uiState.successMessage.isNullOrEmpty()) {
                item {
                    Text(
                        text = uiState.successMessage ?: "",
                        color = Color(0xFF16A34A),
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(Color(0xFF064E3B))
                            .padding(12.dp)
                    )
                }
            }

            if (!uiState.errorMessage.isNullOrEmpty()) {
                item {
                    Text(
                        text = uiState.errorMessage ?: "",
                        color = Color(0xFFFCA5A5),
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(Color(0xFF7F1D1D))
                            .padding(12.dp)
                    )
                }
            }

            if (uiState.recentItems.isNotEmpty()) {
                item {
                    Text(
                        text = "Zuletzt erfasst",
                        style = MaterialTheme.typography.titleMedium,
                        color = TextPrimary,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(top = 12.dp)
                    )
                }

                items(uiState.recentItems) { item ->
                    SwipeableExternalItemRow(
                        item = item,
                        onReturn = { viewModel.markItemReturned(item.id) }
                    )
                }
            }
        }
    }
}

@Composable
private fun ExternalItemForm(
    uiState: com.rms.scanner.ui.viewmodels.ExternalItemUiState,
    onDescriptionChange: (String) -> Unit,
    onOwnerNameChange: (String) -> Unit,
    onOwnerContactChange: (String) -> Unit,
    onQuantityChange: (Int) -> Unit,
    onProjectSelect: (Int) -> Unit,
    onReturnDateChange: (String) -> Unit,
    onNotesChange: (String) -> Unit
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Description field
        FormTextField(
            label = "Beschreibung *",
            value = uiState.formState.description,
            onValueChange = onDescriptionChange,
            placeholder = "z.B. Bohrmaschine"
        )

        // Owner name field
        FormTextField(
            label = "Eigentümer *",
            value = uiState.formState.ownerName,
            onValueChange = onOwnerNameChange,
            placeholder = "z.B. Max Mustermann"
        )

        // Contact field
        FormTextField(
            label = "Kontakt",
            value = uiState.formState.ownerContact,
            onValueChange = onOwnerContactChange,
            placeholder = "z.B. max@example.com"
        )

        // Quantity field
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text(
                text = "Menge",
                style = MaterialTheme.typography.bodyMedium,
                color = TextPrimary,
                modifier = Modifier.weight(1f)
            )
            Box(
                modifier = Modifier
                    .background(Color(0xFF374151))
                    .padding(8.dp)
            ) {
                Text(
                    text = uiState.formState.quantity.toString(),
                    style = MaterialTheme.typography.bodyLarge,
                    color = TextPrimary
                )
            }
        }

        // Project selector
        ProjectDropdownField(
            label = "Projekt",
            projects = uiState.projects,
            selectedProjectId = uiState.formState.selectedProjectId,
            onProjectSelected = onProjectSelect
        )

        // Return date field
        FormTextField(
            label = "Rückgabedatum",
            value = uiState.formState.returnDate,
            onValueChange = onReturnDateChange,
            placeholder = "z.B. 2024-12-31"
        )

        // Notes field
        FormTextField(
            label = "Notizen",
            value = uiState.formState.notes,
            onValueChange = onNotesChange,
            placeholder = "Optional",
            minLines = 3
        )
    }
}

@Composable
private fun FormTextField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String = "",
    minLines: Int = 1
) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = TextPrimary,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(bottom = 4.dp)
        )
        TextField(
            value = value,
            onValueChange = onValueChange,
            placeholder = { Text(placeholder, color = TextTertiary) },
            modifier = Modifier
                .fillMaxWidth(),
            colors = TextFieldDefaults.colors(
                focusedContainerColor = Color(0xFF1F2937),
                unfocusedContainerColor = Color(0xFF1F2937),
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary,
                cursorColor = Color(0xFFEC4899)
            ),
            textStyle = MaterialTheme.typography.bodyLarge,
            minLines = minLines
        )
    }
}

@Composable
private fun ProjectDropdownField(
    label: String,
    projects: List<com.rms.scanner.data.api.models.ProjectItem>,
    selectedProjectId: Int?,
    onProjectSelected: (Int) -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedProject = projects.find { it.id == selectedProjectId }

    Column(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = TextPrimary,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(bottom = 4.dp)
        )

        Box(modifier = Modifier.fillMaxWidth()) {
            TextButton(
                onClick = { expanded = true },
                modifier = Modifier
                    .fillMaxWidth()
                    .background(Color(0xFF374151))
            ) {
                Text(
                    text = selectedProject?.name ?: label,
                    color = if (selectedProject != null) TextPrimary else TextTertiary,
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
}

@Composable
private fun SwipeableExternalItemRow(
    item: ExternalItem,
    onReturn: () -> Unit
) {
    var offset by remember { mutableStateOf(0f) }
    val threshold = 100f

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(Color(0xFF1F2937))
            .pointerInput(item.id) {
                detectHorizontalDragGestures { change, dragAmount ->
                    offset = (offset + dragAmount).coerceIn(-threshold, 0f)
                    if (offset <= -threshold) {
                        onReturn()
                        offset = 0f
                    }
                }
            }
    ) {
        // Delete background
        if (offset < -20f) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(Color(0xFFDC2626))
                    .align(Alignment.CenterEnd)
                    .padding(16.dp),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Filled.Delete,
                    contentDescription = "Zurückgeben",
                    tint = Color.White
                )
            }
        }

        // Content
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .offset { IntOffset(offset.roundToInt(), 0) }
                .background(Color(0xFF374151))
                .padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = item.description,
                    style = MaterialTheme.typography.bodyLarge,
                    color = TextPrimary,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = "Eigentümer: ${item.owner_name}",
                    style = MaterialTheme.typography.bodySmall,
                    color = TextSecondary
                )
                if (item.barcode != null) {
                    Text(
                        text = item.barcode,
                        style = MaterialTheme.typography.bodySmall,
                        color = TextTertiary
                    )
                }
            }

            if (item.status == "returned") {
                Text(
                    text = "✓",
                    style = MaterialTheme.typography.headlineSmall,
                    color = Color(0xFF10B981)
                )
            }
        }
    }
}
