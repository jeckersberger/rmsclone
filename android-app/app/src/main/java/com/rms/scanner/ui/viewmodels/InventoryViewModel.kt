package com.rms.scanner.ui.viewmodels

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidEvent
import com.rms.scanner.rfid.RfidManager
import com.rms.scanner.rfid.ScanTriggerManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class InventoryUiState(
    val sessionId: String? = null,
    val isInventoryActive: Boolean = false,
    val scanCount: Int = 0,
    val foundCount: Int = 0,
    val missingCount: Int = 0,
    val unknownCount: Int = 0,
    val isProcessing: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null
)

class InventoryViewModel(
    private val rfidManager: RfidManager
) : ViewModel() {
    private val tag = "InventoryViewModel"
    private val repository = RmsRepository()

    private val _uiState = MutableStateFlow(InventoryUiState())
    val uiState: StateFlow<InventoryUiState> = _uiState

    init {
        startHardwareTriggerListener()
    }

    /**
     * Listen for hardware scan trigger events and barcode scans.
     * For Inventory, trigger press starts the inventory session if not already active.
     */
    private fun startHardwareTriggerListener() {
        // Listen for physical trigger button presses/releases
        viewModelScope.launch {
            ScanTriggerManager.triggerEvents.collect { event ->
                when (event) {
                    is ScanTriggerManager.TriggerEvent.Pressed -> {
                        if (!_uiState.value.isInventoryActive) {
                            startInventory()
                        }
                    }
                    is ScanTriggerManager.TriggerEvent.Released -> {
                        // Inventory runs continuously until explicitly completed
                    }
                }
            }
        }

        // Listen for 2D barcode/QR code scans
        startBarcodeListener()
    }

    /**
     * Start listening for 2D barcode/QR code scans.
     * Barcode values are processed the same way as RFID tags during inventory.
     */
    private fun startBarcodeListener() {
        viewModelScope.launch {
            ScanTriggerManager.barcodeEvents.collect { event ->
                val sessionId = _uiState.value.sessionId
                if (sessionId != null && _uiState.value.isInventoryActive) {
                    handleTagRead(sessionId, event.value)
                }
            }
        }
    }

    fun startInventory() {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isProcessing = true)

                val result = repository.startInventory()
                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    val sessionId = response.session_id ?: ""

                    _uiState.value = _uiState.value.copy(
                        sessionId = sessionId,
                        isInventoryActive = true,
                        scanCount = 0,
                        isProcessing = false,
                        successMessage = "Inventur gestartet"
                    )

                    // Start RFID scanning
                    startRfidScanning(sessionId)
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Inventur konnte nicht gestartet werden",
                        isProcessing = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error starting inventory", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isProcessing = false
                )
            }
        }
    }

    private fun startRfidScanning(sessionId: String) {
        rfidManager.startInventory { event ->
            when (event) {
                is RfidEvent.TagRead -> handleTagRead(sessionId, event.epc)
                is RfidEvent.Error -> _uiState.value = _uiState.value.copy(
                    errorMessage = event.message
                )
                else -> {}
            }
        }
    }

    private fun handleTagRead(sessionId: String, tag: String) {
        viewModelScope.launch {
            try {
                val result = repository.inventoryScan(sessionId, tag)
                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    _uiState.value = _uiState.value.copy(
                        scanCount = (response.scan_count ?: 0) + 1
                    )
                    Log.d(tag, "Tag scanned: $tag, count: ${_uiState.value.scanCount}")
                } else {
                    Log.w(tag, "Failed to record tag scan")
                }
            } catch (e: Exception) {
                Log.e(tag, "Error during inventory scan", e)
            }
        }
    }

    fun completeInventory() {
        val sessionId = _uiState.value.sessionId ?: return

        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isProcessing = true)
                rfidManager.stopInventory()

                val result = repository.completeInventory(sessionId)
                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    _uiState.value = _uiState.value.copy(
                        isInventoryActive = false,
                        foundCount = response.found ?: 0,
                        missingCount = response.missing ?: 0,
                        unknownCount = response.unknown ?: 0,
                        isProcessing = false,
                        successMessage = "Inventur abgeschlossen: ${response.found} gefunden, ${response.missing} vermisst"
                    )
                    Log.d(tag, "Inventory completed: ${response.found} found, ${response.missing} missing, ${response.unknown} unknown")
                } else {
                    _uiState.value = _uiState.value.copy(
                        isProcessing = false,
                        errorMessage = result.exceptionOrNull()?.message ?: "Inventur konnte nicht abgeschlossen werden"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error completing inventory", e)
                _uiState.value = _uiState.value.copy(
                    isProcessing = false,
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    fun cancelInventory() {
        rfidManager.stopInventory()
        _uiState.value = InventoryUiState()
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(
            errorMessage = null,
            successMessage = null
        )
    }
}
