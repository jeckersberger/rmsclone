package com.rms.scanner.ui.viewmodels

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.ProjectItem
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.ScanTriggerManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class PackingItem(
    val id: Int,
    val entityType: String, // "asset", "stock_instance", "external"
    val displayName: String,
    val scanCode: String,
    val checked: Boolean = false,
    val checkedAt: String? = null
)

data class PackingListUiState(
    val projects: List<ProjectItem> = emptyList(),
    val selectedProjectId: Int? = null,
    val assets: List<PackingItem> = emptyList(),
    val stockInstances: List<PackingItem> = emptyList(),
    val externalItems: List<PackingItem> = emptyList(),
    val totalItems: Int = 0,
    val checkedItems: Int = 0,
    val isLoading: Boolean = false,
    val lastCheckedItem: String? = null,
    val errorMessage: String? = null
)

class PackingListViewModel : ViewModel() {
    private val tag = "PackingListViewModel"
    private val repository = RmsRepository()

    private val _uiState = MutableStateFlow(PackingListUiState())
    val uiState: StateFlow<PackingListUiState> = _uiState

    init {
        loadProjects()
        startBarcodeListener()
    }

    private fun loadProjects() {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isLoading = true)
                val result = repository.listProjects()
                if (result.isSuccess) {
                    val projects = result.getOrNull() ?: emptyList()
                    _uiState.value = _uiState.value.copy(
                        projects = projects,
                        isLoading = false
                    )
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Laden der Projekte",
                        isLoading = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error loading projects", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isLoading = false
                )
            }
        }
    }

    fun selectProject(projectId: Int) {
        _uiState.value = _uiState.value.copy(selectedProjectId = projectId)
        loadPackingList(projectId)
    }

    private fun loadPackingList(projectId: Int) {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isLoading = true)
                val result = repository.getPackingList(projectId)
                if (result.isSuccess) {
                    val response = result.getOrNull()!!

                    val assets = response.assets?.map { item ->
                        PackingItem(
                            id = item.id,
                            entityType = "asset",
                            displayName = item.display_name ?: "Asset #${item.id}",
                            scanCode = item.rfid_tag ?: item.barcode ?: item.scan_code ?: "",
                            checked = item.checked,
                            checkedAt = item.checked_at
                        )
                    } ?: emptyList()

                    val stocks = response.stock_instances?.map { item ->
                        PackingItem(
                            id = item.id,
                            entityType = "stock_instance",
                            displayName = item.display_name ?: "Stock #${item.id}",
                            scanCode = item.rfid_tag ?: item.barcode ?: item.scan_code ?: "",
                            checked = item.checked,
                            checkedAt = item.checked_at
                        )
                    } ?: emptyList()

                    val external = response.external_items?.map { item ->
                        PackingItem(
                            id = item.id,
                            entityType = "external",
                            displayName = item.display_name ?: "Extern #${item.id}",
                            scanCode = item.rfid_tag ?: item.barcode ?: item.scan_code ?: "",
                            checked = item.checked,
                            checkedAt = item.checked_at
                        )
                    } ?: emptyList()

                    val totalItems = assets.size + stocks.size + external.size
                    val checkedItems = assets.count { it.checked } + stocks.count { it.checked } + external.count { it.checked }

                    _uiState.value = _uiState.value.copy(
                        assets = assets,
                        stockInstances = stocks,
                        externalItems = external,
                        totalItems = totalItems,
                        checkedItems = checkedItems,
                        isLoading = false
                    )
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Laden der Packliste",
                        isLoading = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error loading packing list", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isLoading = false
                )
            }
        }
    }

    fun handleRfidScan(scanValue: String) {
        val projectId = _uiState.value.selectedProjectId ?: return
        viewModelScope.launch {
            try {
                val result = repository.scanCheckPackingItem(scanValue, projectId)
                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    _uiState.value = _uiState.value.copy(
                        lastCheckedItem = response.message ?: "Item erfasst"
                    )
                    // Reload the list to show updated state
                    loadPackingList(projectId)
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Scan konnte nicht verarbeitet werden"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error handling RFID scan", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    fun checkItemManual(item: PackingItem) {
        if (item.checked) {
            uncheckItem(item)
        } else {
            checkItem(item)
        }
    }

    private fun checkItem(item: PackingItem) {
        val projectId = _uiState.value.selectedProjectId ?: return
        viewModelScope.launch {
            try {
                val result = repository.checkPackingItem(item.entityType, item.id, projectId)
                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        lastCheckedItem = "${item.displayName} - abgehakt"
                    )
                    loadPackingList(projectId)
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Abhaken"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error checking item", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    private fun uncheckItem(item: PackingItem) {
        val projectId = _uiState.value.selectedProjectId ?: return
        viewModelScope.launch {
            try {
                val result = repository.uncheckPackingItem(item.entityType, item.id, projectId)
                if (result.isSuccess) {
                    loadPackingList(projectId)
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Abhaken"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error unchecking item", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler"
                )
            }
        }
    }

    /**
     * Start listening for 2D barcode/QR code scans.
     * Barcode values are processed the same way as RFID scans.
     */
    private fun startBarcodeListener() {
        viewModelScope.launch {
            ScanTriggerManager.barcodeEvents.collect { event ->
                handleRfidScan(event.value)
            }
        }
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(
            errorMessage = null,
            lastCheckedItem = null
        )
    }
}
