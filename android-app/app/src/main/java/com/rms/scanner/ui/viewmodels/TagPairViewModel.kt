package com.rms.scanner.ui.viewmodels

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.AssetItem
import com.rms.scanner.data.api.models.StockInstance
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidEvent
import com.rms.scanner.rfid.RfidManager
import com.rms.scanner.rfid.ScanTriggerManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class TagPairUiState(
    val entityType: String = "asset",  // "asset" or "stock"
    val assets: List<AssetItem> = emptyList(),
    val selectedAssetId: Int? = null,
    val stockInstances: List<StockInstance> = emptyList(),
    val selectedInstanceId: Int? = null,
    val scannedTid: String = "",       // TID read from the RFID tag
    val tidLookupResult: String? = null, // Info about existing TID pairing
    val isLoadingAssets: Boolean = false,
    val isLoadingInstances: Boolean = false,
    val isPairing: Boolean = false,
    val isScanning: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
)

class TagPairViewModel(
    private val rfidManager: RfidManager
) : ViewModel() {
    private val tag = "TagPairViewModel"
    private val repository = RmsRepository()

    private val _uiState = MutableStateFlow(TagPairUiState())
    val uiState: StateFlow<TagPairUiState> = _uiState

    init {
        loadAssets()
        startHardwareTriggerListener()
    }

    /**
     * Listen for hardware scan trigger events.
     * On the TagPair screen, pressing the trigger starts a single-tag TID scan.
     */
    private fun startHardwareTriggerListener() {
        viewModelScope.launch {
            ScanTriggerManager.triggerEvents.collect { event ->
                when (event) {
                    is ScanTriggerManager.TriggerEvent.Pressed -> {
                        if (!_uiState.value.isScanning) {
                            startTidScan()
                        }
                    }
                    is ScanTriggerManager.TriggerEvent.Released -> {
                        // TagPair uses single-shot scan, release is a no-op
                    }
                }
            }
        }
    }

    fun selectEntityType(type: String) {
        _uiState.value = _uiState.value.copy(
            entityType = type,
            selectedAssetId = null,
            selectedInstanceId = null
        )
        if (type == "asset") {
            loadAssets()
        }
    }

    private fun loadAssets() {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isLoadingAssets = true)
                val result = repository.listAssets()
                if (result.isSuccess) {
                    val assets = result.getOrNull() ?: emptyList()
                    _uiState.value = _uiState.value.copy(
                        assets = assets,
                        selectedAssetId = assets.firstOrNull()?.id,
                        isLoadingAssets = false
                    )
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Laden",
                        isLoadingAssets = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error loading assets", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isLoadingAssets = false
                )
            }
        }
    }

    fun selectAsset(assetId: Int) {
        _uiState.value = _uiState.value.copy(selectedAssetId = assetId)
    }

    fun selectInstance(instanceId: Int) {
        _uiState.value = _uiState.value.copy(selectedInstanceId = instanceId)
    }

    /**
     * Start scanning for a single TID.
     * When a tag is read, its TID is captured and scanning stops.
     */
    fun startTidScan() {
        if (!rfidManager.isConnected()) {
            rfidManager.connect()
        }

        _uiState.value = _uiState.value.copy(
            isScanning = true,
            scannedTid = "",
            tidLookupResult = null,
            errorMessage = null,
            successMessage = null
        )

        rfidManager.startInventory { event ->
            when (event) {
                is RfidEvent.TagRead -> {
                    // First tag read = our TID. Stop scanning.
                    rfidManager.stopInventory()
                    _uiState.value = _uiState.value.copy(
                        scannedTid = event.epc, // TID is reported as epc by the reader
                        isScanning = false
                    )
                    // Look up if this TID is already paired
                    lookupTid(event.epc)
                }
                is RfidEvent.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isScanning = false,
                        errorMessage = "Scanner-Fehler: ${event.message}"
                    )
                }
                else -> { /* ignore */ }
            }
        }
    }

    fun stopTidScan() {
        rfidManager.stopInventory()
        _uiState.value = _uiState.value.copy(isScanning = false)
    }

    /**
     * Look up if a TID is already paired to an entity.
     */
    private fun lookupTid(tid: String) {
        viewModelScope.launch {
            try {
                val result = repository.lookupTid(tid)
                if (result.isSuccess) {
                    @Suppress("UNCHECKED_CAST")
                    val data = result.getOrNull() as? Map<String, Any>
                    val found = data?.get("found") as? Boolean ?: false
                    if (found) {
                        @Suppress("UNCHECKED_CAST")
                        val entity = data?.get("entity") as? Map<String, Any>
                        val displayName = entity?.get("display_name") as? String ?: "Unbekannt"
                        _uiState.value = _uiState.value.copy(
                            tidLookupResult = "Bereits zugeordnet: $displayName"
                        )
                    } else {
                        _uiState.value = _uiState.value.copy(
                            tidLookupResult = "TID frei — bereit zum Zuordnen"
                        )
                    }
                }
            } catch (e: Exception) {
                Log.e(tag, "TID lookup failed", e)
            }
        }
    }

    /**
     * Pair the scanned TID with the selected entity.
     * Calls the server API: POST rfid/scan.php?action=pair_tid
     */
    fun pairTid() {
        val tid = _uiState.value.scannedTid.trim()
        if (tid.isEmpty()) {
            _uiState.value = _uiState.value.copy(errorMessage = "Bitte zuerst einen Tag scannen")
            return
        }

        val entityType = when (_uiState.value.entityType) {
            "asset" -> "asset"
            "stock" -> "stock_instance"
            else -> return
        }
        val entityId = when (_uiState.value.entityType) {
            "asset" -> _uiState.value.selectedAssetId
            "stock" -> _uiState.value.selectedInstanceId
            else -> null
        }

        if (entityId == null) {
            _uiState.value = _uiState.value.copy(errorMessage = "Bitte Geraet oder Artikel waehlen")
            return
        }

        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isPairing = true, errorMessage = null)
                val result = repository.pairTid(entityType, entityId, tid)
                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        isPairing = false,
                        successMessage = "TID erfolgreich zugeordnet!",
                        scannedTid = "",
                        tidLookupResult = null
                    )
                    Log.d(tag, "TID paired: $tid -> $entityType:$entityId")
                } else {
                    _uiState.value = _uiState.value.copy(
                        isPairing = false,
                        errorMessage = result.exceptionOrNull()?.message ?: "Zuordnung fehlgeschlagen"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error pairing TID", e)
                _uiState.value = _uiState.value.copy(
                    isPairing = false,
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    /**
     * Remove TID pairing from the selected entity.
     */
    fun unpairTid() {
        val entityType = when (_uiState.value.entityType) {
            "asset" -> "asset"
            "stock" -> "stock_instance"
            else -> return
        }
        val entityId = when (_uiState.value.entityType) {
            "asset" -> _uiState.value.selectedAssetId
            "stock" -> _uiState.value.selectedInstanceId
            else -> null
        }

        if (entityId == null) {
            _uiState.value = _uiState.value.copy(errorMessage = "Bitte Geraet oder Artikel waehlen")
            return
        }

        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isPairing = true, errorMessage = null)
                val result = repository.unpairTid(entityType, entityId)
                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        isPairing = false,
                        successMessage = "TID-Zuordnung entfernt",
                        tidLookupResult = null
                    )
                } else {
                    _uiState.value = _uiState.value.copy(
                        isPairing = false,
                        errorMessage = result.exceptionOrNull()?.message ?: "Entfernen fehlgeschlagen"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error unpairing TID", e)
                _uiState.value = _uiState.value.copy(
                    isPairing = false,
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(
            errorMessage = null,
            successMessage = null
        )
    }
}
