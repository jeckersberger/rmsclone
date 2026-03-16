package com.rms.scanner.ui.viewmodels

import android.content.Context
import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.BoxScanResultItem
import com.rms.scanner.data.api.models.ProjectItem
import com.rms.scanner.data.preferences.AppPreferences
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidEvent
import com.rms.scanner.rfid.RfidManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class BoxScanUiState(
    val projects: List<ProjectItem> = emptyList(),
    val selectedProjectId: Int? = null,
    val scannedTags: MutableSet<String> = mutableSetOf(),
    val scanResults: List<BoxScanResultItem> = emptyList(),
    val assetCount: Int = 0,
    val stockCount: Int = 0,
    val unknownCount: Int = 0,
    val isScanning: Boolean = false,
    val isProcessing: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null
)

class BoxScanViewModel(
    context: Context,
    private val rfidManager: RfidManager
) : ViewModel() {
    private val tag = "BoxScanViewModel"
    private val repository = RmsRepository()
    private val prefs = AppPreferences(context)

    private val _uiState = MutableStateFlow(BoxScanUiState())
    val uiState: StateFlow<BoxScanUiState> = _uiState

    init {
        loadProjects()
    }

    private fun loadProjects() {
        viewModelScope.launch {
            try {
                val result = repository.listProjects()
                if (result.isSuccess) {
                    val projects = result.getOrNull() ?: emptyList()
                    _uiState.value = _uiState.value.copy(
                        projects = projects,
                        selectedProjectId = projects.firstOrNull()?.id
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Failed to load projects", e)
            }
        }
    }

    fun selectProject(projectId: Int) {
        _uiState.value = _uiState.value.copy(selectedProjectId = projectId)
    }

    fun startScanning() {
        if (_uiState.value.isScanning) return

        _uiState.value = _uiState.value.copy(isScanning = true)
        rfidManager.startInventory { event ->
            when (event) {
                is RfidEvent.TagRead -> addTag(event.epc)
                is RfidEvent.Error -> _uiState.value = _uiState.value.copy(
                    errorMessage = event.message
                )
                else -> {}
            }
        }
    }

    fun stopScanning() {
        rfidManager.stopInventory()
        _uiState.value = _uiState.value.copy(isScanning = false)
    }

    private fun addTag(tag: String) {
        val tags = _uiState.value.scannedTags
        tags.add(tag)
        _uiState.value = _uiState.value.copy(scannedTags = tags)
    }

    fun evaluateBoxScan() {
        val tags = _uiState.value.scannedTags.toList()
        if (tags.isEmpty()) {
            _uiState.value = _uiState.value.copy(
                errorMessage = "Keine Tags gescannt"
            )
            return
        }

        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isProcessing = true)

                val result = repository.boxScan(tags)
                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    val scanResults = response.results ?: emptyList()

                    val assetCount = scanResults.count { it.entity_type == "asset" }
                    val stockCount = scanResults.count { it.entity_type == "stock" }
                    val unknownCount = scanResults.count { it.entity_type == "unknown" }

                    _uiState.value = _uiState.value.copy(
                        scanResults = scanResults,
                        assetCount = assetCount,
                        stockCount = stockCount,
                        unknownCount = unknownCount,
                        isProcessing = false,
                        successMessage = "Bewertung abgeschlossen"
                    )

                    Log.d(tag, "Box scan evaluated: $assetCount assets, $stockCount stock, $unknownCount unknown")
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Bewertung fehlgeschlagen",
                        isProcessing = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error evaluating box scan", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isProcessing = false
                )
            }
        }
    }

    fun applyCheckoutToAll() {
        applyActionToAll("checkout")
    }

    fun applyCheckinToAll() {
        applyActionToAll("checkin")
    }

    private fun applyActionToAll(action: String) {
        val tags = _uiState.value.scannedTags.toList()
        if (tags.isEmpty()) return

        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isProcessing = true)

                val result = repository.boxScanAction(
                    rfidTags = tags,
                    scanAction = action,
                    projectId = _uiState.value.selectedProjectId
                )

                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    _uiState.value = _uiState.value.copy(
                        isProcessing = false,
                        successMessage = "Aktion abgeschlossen: ${response.success_count ?: 0} erfolgreich"
                    )
                    Log.d(tag, "Action $action applied to ${response.success_count} tags")
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Aktion fehlgeschlagen",
                        isProcessing = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error applying action", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isProcessing = false
                )
            }
        }
    }

    fun reset() {
        _uiState.value = BoxScanUiState(
            projects = _uiState.value.projects,
            selectedProjectId = _uiState.value.selectedProjectId
        )
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(
            errorMessage = null,
            successMessage = null
        )
    }
}
