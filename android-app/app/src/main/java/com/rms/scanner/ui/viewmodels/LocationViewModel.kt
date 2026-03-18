package com.rms.scanner.ui.viewmodels

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.Location
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidEvent
import com.rms.scanner.rfid.RfidManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class LocationUiState(
    val locations: List<Location> = emptyList(),
    val selectedLocationId: Int? = null,
    val customLocation: String = "",
    val notes: String = "",
    val scannedTags: List<String> = emptyList(),
    val lastResult: ScanResultInfo? = null,
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val totalRelocated: Int = 0,
    val totalErrors: Int = 0
)

data class ScanResultInfo(
    val entityType: String,
    val displayName: String,
    val locationName: String,
    val success: Boolean
)

class LocationViewModel(
    private val repository: RmsRepository,
    private val rfidManager: RfidManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(LocationUiState())
    val uiState: StateFlow<LocationUiState> = _uiState.asStateFlow()

    init {
        loadLocations()
    }

    fun loadLocations() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)
            repository.listLocations()
                .onSuccess { locations ->
                    _uiState.value = _uiState.value.copy(
                        locations = locations,
                        isLoading = false
                    )
                }
                .onFailure { e ->
                    _uiState.value = _uiState.value.copy(
                        errorMessage = e.message,
                        isLoading = false
                    )
                }
        }
    }

    fun selectLocation(locationId: Int?) {
        _uiState.value = _uiState.value.copy(selectedLocationId = locationId)
    }

    fun setCustomLocation(text: String) {
        _uiState.value = _uiState.value.copy(customLocation = text)
    }

    fun setNotes(text: String) {
        _uiState.value = _uiState.value.copy(notes = text)
    }

    fun startScanning() {
        rfidManager.startInventory { event ->
            if (event is RfidEvent.TagRead) {
                handleTagRead(event.epc)
            }
        }
    }

    fun stopScanning() {
        rfidManager.stopInventory()
    }

    private fun handleTagRead(epc: String) {
        val state = _uiState.value

        // Validate location is selected
        if (state.selectedLocationId == null && state.customLocation.isBlank()) {
            _uiState.value = state.copy(errorMessage = "Bitte zuerst Lagerort wählen")
            return
        }

        // Deduplicate
        if (epc in state.scannedTags) return

        _uiState.value = state.copy(
            scannedTags = state.scannedTags + epc
        )

        // Immediately assign location via API
        viewModelScope.launch {
            repository.scanAssignLocation(
                rfidTag = epc,
                locationId = state.selectedLocationId,
                locationCustom = state.customLocation.ifBlank { null },
                notes = state.notes.ifBlank { null }
            ).onSuccess { data ->
                val entityType = data.entity_type ?: "unknown"
                val entity = data.entity
                val displayName = when {
                    entity != null -> (entity["display_name"] ?: entity["assets_name"] ?: entity["item_name"] ?: "Unbekannt").toString()
                    else -> "Unbekannt"
                }
                val locationName = data.location?.let {
                    (it["name"] ?: it["location_custom"] ?: "").toString()
                } ?: ""

                _uiState.value = _uiState.value.copy(
                    lastResult = ScanResultInfo(
                        entityType = entityType,
                        displayName = displayName,
                        locationName = locationName,
                        success = true
                    ),
                    totalRelocated = _uiState.value.totalRelocated + 1,
                    errorMessage = null
                )
            }.onFailure { e ->
                _uiState.value = _uiState.value.copy(
                    lastResult = ScanResultInfo(
                        entityType = "unknown",
                        displayName = epc,
                        locationName = "",
                        success = false
                    ),
                    totalErrors = _uiState.value.totalErrors + 1,
                    errorMessage = e.message
                )
            }
        }
    }

    fun resetScan() {
        _uiState.value = _uiState.value.copy(
            scannedTags = emptyList(),
            lastResult = null,
            totalRelocated = 0,
            totalErrors = 0,
            errorMessage = null
        )
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(errorMessage = null)
    }
}
