package com.rms.scanner.ui.viewmodels

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.gson.Gson
import com.rms.scanner.data.api.models.CaseContentItem
import com.rms.scanner.data.api.models.CaseInfo
import com.rms.scanner.data.api.models.CaseVerificationResult
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class CaseVerifyUiState(
    val caseInfo: CaseInfo? = null,
    val expectedContents: List<CaseContentItem> = emptyList(),
    val scannedItems: Set<Int> = emptySet(),  // IDs of scanned items
    val verificationResult: CaseVerificationResult? = null,
    val isScanning: Boolean = false,
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val progress: Float = 0f,  // found / expected
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val showDiscrepancyDialog: Boolean = false,
    val missingItems: List<CaseContentItem> = emptyList(),
    val extraItems: List<Map<String, Any>> = emptyList(),
    val swappedItems: List<Map<String, Any>> = emptyList()
)

class CaseVerifyViewModel(
    private val rfidManager: RfidManager? = null
) : ViewModel() {
    private val tag = "CaseVerifyViewModel"
    private val repository = RmsRepository()
    private val gson = Gson()

    private val _uiState = MutableStateFlow(CaseVerifyUiState())
    val uiState: StateFlow<CaseVerifyUiState> = _uiState

    fun loadCaseContents(caseAssetId: Int) {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isLoading = true)

                // Fetch case information and contents
                val result = repository.getCaseContents(caseAssetId)

                if (result.isSuccess) {
                    val caseInfo = result.getOrNull() as? CaseInfo
                    val contents = result.getOrNull() as? List<CaseContentItem> ?: emptyList()

                    _uiState.value = _uiState.value.copy(
                        caseInfo = caseInfo,
                        expectedContents = contents,
                        isLoading = false,
                        progress = 0f
                    )

                    Log.d(tag, "Loaded case contents: ${contents.size} items")
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Laden der Kisteninhalt",
                        isLoading = false
                    )
                    Log.e(tag, "Failed to load case contents", result.exceptionOrNull())
                }
            } catch (e: Exception) {
                Log.e(tag, "Error loading case contents", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isLoading = false
                )
            }
        }
    }

    fun onTagScanned(tag: String) {
        Log.d(this.tag, "Tag scanned: $tag")

        // Find matching content item by RFID tag
        val matchingItem = _uiState.value.expectedContents.firstOrNull { item ->
            // In real implementation, would match by EPC/RFID tag
            item.entity_name.contains(tag, ignoreCase = true) ||
            tag.contains(item.entity_name, ignoreCase = true)
        }

        if (matchingItem != null) {
            val currentScanned = _uiState.value.scannedItems.toMutableSet()
            currentScanned.add(matchingItem.id)

            val progress = currentScanned.size.toFloat() / _uiState.value.expectedContents.size

            _uiState.value = _uiState.value.copy(
                scannedItems = currentScanned,
                progress = progress
            )
            Log.d(this.tag, "Item matched: ${matchingItem.entity_name}")
        } else {
            Log.w(this.tag, "No matching item found for tag: $tag")
            _uiState.value = _uiState.value.copy(
                successMessage = "Tag nicht in erwarteter Inhalt"
            )
        }
    }

    fun completeVerification(projectId: Int) {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isSaving = true)

                val caseAssetId = _uiState.value.caseInfo?.asset_id ?: return@launch

                // Build scanned items JSON
                val scannedItemsJson = gson.toJson(_uiState.value.scannedItems.toList())

                // Verify with API
                val result = repository.verifyCaseScan(caseAssetId, scannedItemsJson)

                if (result.isSuccess) {
                    val verificationResult = result.getOrNull() as? CaseVerificationResult

                    if (verificationResult != null) {
                        if (verificationResult.all_complete) {
                            // All items found - log and proceed
                            logCheckResult(caseAssetId, projectId, "complete", verificationResult)
                        } else {
                            // Show discrepancy dialog
                            _uiState.value = _uiState.value.copy(
                                verificationResult = verificationResult,
                                showDiscrepancyDialog = true,
                                missingItems = verificationResult.missing,
                                extraItems = verificationResult.extra,
                                swappedItems = verificationResult.swapped,
                                isSaving = false
                            )
                            Log.w(tag, "Verification incomplete - missing: ${verificationResult.missing.size}")
                        }
                    } else {
                        _uiState.value = _uiState.value.copy(
                            errorMessage = "Ungültige Antwort vom Server",
                            isSaving = false
                        )
                    }
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler bei Prüfung",
                        isSaving = false
                    )
                    Log.e(tag, "Verification failed", result.exceptionOrNull())
                }
            } catch (e: Exception) {
                Log.e(tag, "Error completing verification", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isSaving = false
                )
            }
        }
    }

    fun acknowledgeDiscrepancy() {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isSaving = true)

                val caseAssetId = _uiState.value.caseInfo?.asset_id ?: return@launch
                val projectId = 0  // Would need to be passed in or stored
                val verificationResult = _uiState.value.verificationResult ?: return@launch

                // Log the check with discrepancies acknowledged
                logCheckResult(caseAssetId, projectId, "acknowledged", verificationResult)
            } catch (e: Exception) {
                Log.e(tag, "Error acknowledging discrepancy", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isSaving = false
                )
            }
        }
    }

    fun continueScan() {
        _uiState.value = _uiState.value.copy(
            showDiscrepancyDialog = false
        )
    }

    private fun logCheckResult(
        caseAssetId: Int,
        projectId: Int,
        checkType: String,
        verificationResult: CaseVerificationResult
    ) {
        viewModelScope.launch {
            try {
                val resultJson = gson.toJson(verificationResult)
                val result = repository.logCaseCheck(
                    caseAssetId = caseAssetId,
                    projectId = projectId,
                    checkType = checkType,
                    resultJson = resultJson
                )

                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        successMessage = "Kisten-Prüfung abgeschlossen",
                        showDiscrepancyDialog = false,
                        isSaving = false
                    )
                    Log.d(tag, "Check logged successfully")
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Protokollieren",
                        isSaving = false
                    )
                    Log.e(tag, "Failed to log check", result.exceptionOrNull())
                }
            } catch (e: Exception) {
                Log.e(tag, "Error logging check", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isSaving = false
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

    fun resetScanning() {
        _uiState.value = _uiState.value.copy(
            scannedItems = emptySet(),
            progress = 0f,
            verificationResult = null,
            showDiscrepancyDialog = false
        )
    }
}
