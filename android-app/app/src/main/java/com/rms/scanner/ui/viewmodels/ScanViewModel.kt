package com.rms.scanner.ui.viewmodels

import android.content.Context
import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.ProjectItem
import com.rms.scanner.data.api.models.UniversalScanResponse
import com.rms.scanner.data.preferences.AppPreferences
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidEvent
import com.rms.scanner.rfid.RfidManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class ScanResult(
    val tag: String,
    val entityType: String,
    val entityName: String,
    val status: String,
    val timestamp: Long = System.currentTimeMillis()
)

data class ScanUiState(
    val projects: List<ProjectItem> = emptyList(),
    val selectedProjectId: Int? = null,
    val lastScanResult: ScanResult? = null,
    val scanHistory: List<ScanResult> = emptyList(),
    val isScanning: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val isProcessing: Boolean = false
)

class ScanViewModel(
    context: Context,
    private val rfidManager: RfidManager
) : ViewModel() {
    private val tag = "ScanViewModel"
    private val repository = RmsRepository()
    private val prefs = AppPreferences(context)

    private val _uiState = MutableStateFlow(ScanUiState())
    val uiState: StateFlow<ScanUiState> = _uiState

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

    fun startScanning(scanAction: String) {
        if (_uiState.value.isScanning) return

        _uiState.value = _uiState.value.copy(isScanning = true)
        rfidManager.startInventory { event ->
            when (event) {
                is RfidEvent.TagRead -> handleTagRead(event.epc, scanAction)
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

    private fun handleTagRead(tag: String, scanAction: String) {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isProcessing = true)

                val result = repository.universalScan(
                    rfidTag = tag,
                    scanAction = scanAction,
                    projectId = _uiState.value.selectedProjectId
                )

                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    val scanResult = ScanResult(
                        tag = tag,
                        entityType = response.entity_type ?: "Unknown",
                        entityName = response.entity_name ?: "Unbekannt",
                        status = response.status ?: "OK"
                    )

                    val history = (_uiState.value.scanHistory + scanResult).takeLast(50)

                    _uiState.value = _uiState.value.copy(
                        lastScanResult = scanResult,
                        scanHistory = history,
                        successMessage = "${response.entity_name}: ${response.status}",
                        errorMessage = null,
                        isProcessing = false
                    )

                    Log.d(tag, "Scan successful: $scanResult")
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Scan fehlgeschlagen",
                        isProcessing = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error during scan", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler beim Scan",
                    isProcessing = false
                )
            }
        }
    }

    fun clearHistory() {
        _uiState.value = _uiState.value.copy(
            scanHistory = emptyList(),
            lastScanResult = null
        )
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(
            errorMessage = null,
            successMessage = null
        )
    }
}
