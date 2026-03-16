package com.rms.scanner.ui.viewmodels

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.AssetItem
import com.rms.scanner.data.api.models.StockInstance
import com.rms.scanner.data.repository.RmsRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import java.util.UUID

data class TagWriteUiState(
    val entityType: String = "asset",  // "asset" or "stock"
    val assets: List<AssetItem> = emptyList(),
    val selectedAssetId: Int? = null,
    val stockInstances: List<StockInstance> = emptyList(),
    val selectedInstanceId: Int? = null,
    val newEpc: String = "",            // Human-readable: RMS-a3f7b2c1-A-000042
    val binaryEpc: String = "",         // Binary hex for RFID write: 52A3F7B2C1410000002A...
    val isLoadingAssets: Boolean = false,
    val isLoadingInstances: Boolean = false,
    val isWriting: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val generatedEpc: String? = null,
    val companyCode: String = "",
    val isLoadingCompanyCode: Boolean = false
)

class TagWriteViewModel : ViewModel() {
    private val tag = "TagWriteViewModel"
    private val repository = RmsRepository()

    private val _uiState = MutableStateFlow(TagWriteUiState())
    val uiState: StateFlow<TagWriteUiState> = _uiState

    init {
        loadAssets()
        loadCompanyCode()
    }

    fun selectEntityType(type: String) {
        _uiState.value = _uiState.value.copy(
            entityType = type,
            selectedAssetId = null,
            selectedInstanceId = null,
            newEpc = ""
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

    fun updateNewEpc(epc: String) {
        _uiState.value = _uiState.value.copy(newEpc = epc)
    }

    /**
     * Generate the EPC for writing to an RFID tag.
     * Calls the server API to get both human-readable and binary EPC formats.
     * The binary EPC (96-bit hex) is what actually gets written to the tag memory.
     * The human-readable format is displayed in the UI.
     */
    fun generateAutoEpc() {
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
            _uiState.value = _uiState.value.copy(
                errorMessage = "Bitte zuerst ein Gerät/Artikel auswählen"
            )
            return
        }

        viewModelScope.launch {
            try {
                val result = repository.getWriteEpc(entityType, entityId)
                if (result.isSuccess) {
                    @Suppress("UNCHECKED_CAST")
                    val data = result.getOrNull() as? Map<String, Any> ?: return@launch
                    val humanReadable = data["human_readable"] as? String ?: ""
                    val binaryEpc96 = data["epc_96bit"] as? String ?: ""

                    _uiState.value = _uiState.value.copy(
                        newEpc = humanReadable,
                        binaryEpc = binaryEpc96,
                        generatedEpc = humanReadable
                    )
                    Log.d(tag, "EPC generated: $humanReadable (binary: $binaryEpc96)")
                } else {
                    // Fallback: generate locally with company code
                    val companyCode = _uiState.value.companyCode.ifEmpty { "00000000" }
                    val typeChar = if (entityType == "asset") "A" else "I"
                    val padId = entityId.toString().padStart(6, '0')
                    val autoEpc = "RMS-$companyCode-$typeChar-$padId"
                    _uiState.value = _uiState.value.copy(
                        newEpc = autoEpc,
                        binaryEpc = "", // No binary without server
                        generatedEpc = autoEpc,
                        errorMessage = "Binär-EPC nicht verfügbar (Offline-Modus)"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error generating EPC", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler bei EPC-Generierung"
                )
            }
        }
    }

    private fun loadCompanyCode() {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isLoadingCompanyCode = true)
                val result = repository.getCompanyCode()
                if (result.isSuccess) {
                    val code = result.getOrNull() as? String ?: "XX"
                    _uiState.value = _uiState.value.copy(
                        companyCode = code,
                        isLoadingCompanyCode = false
                    )
                    Log.d(tag, "Company code loaded: $code")
                } else {
                    // Use default if loading fails
                    _uiState.value = _uiState.value.copy(
                        companyCode = "XX",
                        isLoadingCompanyCode = false
                    )
                    Log.w(tag, "Failed to load company code, using default")
                }
            } catch (e: Exception) {
                Log.e(tag, "Error loading company code", e)
                _uiState.value = _uiState.value.copy(
                    companyCode = "XX",
                    isLoadingCompanyCode = false
                )
            }
        }
    }

    fun writeTag() {
        val epc = _uiState.value.newEpc.trim()
        if (epc.isEmpty()) {
            _uiState.value = _uiState.value.copy(
                errorMessage = "EPC erforderlich"
            )
            return
        }

        when (_uiState.value.entityType) {
            "asset" -> {
                val assetId = _uiState.value.selectedAssetId
                if (assetId == null) {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = "Gerät erforderlich"
                    )
                    return
                }
                writeAssetTag(assetId, epc)
            }
            "stock" -> {
                val instanceId = _uiState.value.selectedInstanceId
                if (instanceId == null) {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = "Lagerartikel erforderlich"
                    )
                    return
                }
                writeStockTag(instanceId, epc)
            }
        }
    }

    private fun writeAssetTag(assetId: Int, epc: String) {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isWriting = true)

                val result = repository.assignTag(assetId, epc)
                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        isWriting = false,
                        successMessage = "Tag erfolgreich geschrieben",
                        newEpc = ""
                    )
                    Log.d(tag, "Asset tag written: $epc to asset $assetId")
                } else {
                    _uiState.value = _uiState.value.copy(
                        isWriting = false,
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Schreiben"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error writing asset tag", e)
                _uiState.value = _uiState.value.copy(
                    isWriting = false,
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    private fun writeStockTag(instanceId: Int, epc: String) {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isWriting = true)

                val result = repository.assignStockRfid(instanceId, epc)
                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        isWriting = false,
                        successMessage = "Tag erfolgreich geschrieben",
                        newEpc = ""
                    )
                    Log.d(tag, "Stock tag written: $epc to instance $instanceId")
                } else {
                    _uiState.value = _uiState.value.copy(
                        isWriting = false,
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Schreiben"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error writing stock tag", e)
                _uiState.value = _uiState.value.copy(
                    isWriting = false,
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
