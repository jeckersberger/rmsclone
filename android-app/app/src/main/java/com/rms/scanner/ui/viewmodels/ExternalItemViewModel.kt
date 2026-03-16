package com.rms.scanner.ui.viewmodels

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.models.ExternalItem
import com.rms.scanner.data.api.models.ProjectItem
import com.rms.scanner.data.repository.RmsRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import java.util.UUID

data class ExternalItemFormState(
    val description: String = "",
    val ownerName: String = "",
    val ownerContact: String = "",
    val quantity: Int = 1,
    val selectedProjectId: Int? = null,
    val returnDate: String = "",
    val notes: String = ""
)

data class ExternalItemUiState(
    val formState: ExternalItemFormState = ExternalItemFormState(),
    val projects: List<ProjectItem> = emptyList(),
    val recentItems: List<ExternalItem> = emptyList(),
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val successMessage: String? = null,
    val errorMessage: String? = null
)

class ExternalItemViewModel : ViewModel() {
    private val tag = "ExternalItemViewModel"
    private val repository = RmsRepository()

    private val _uiState = MutableStateFlow(ExternalItemUiState())
    val uiState: StateFlow<ExternalItemUiState> = _uiState

    init {
        loadProjects()
        loadRecentItems()
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

    private fun loadRecentItems() {
        viewModelScope.launch {
            try {
                val result = repository.listExternalItems("bei_uns")
                if (result.isSuccess) {
                    val items = result.getOrNull()?.take(10) ?: emptyList()
                    _uiState.value = _uiState.value.copy(recentItems = items)
                }
            } catch (e: Exception) {
                Log.e(tag, "Error loading recent items", e)
            }
        }
    }

    fun updateDescription(description: String) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(description = description)
        )
    }

    fun updateOwnerName(ownerName: String) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(ownerName = ownerName)
        )
    }

    fun updateOwnerContact(ownerContact: String) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(ownerContact = ownerContact)
        )
    }

    fun updateQuantity(quantity: Int) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(quantity = quantity.coerceAtLeast(1))
        )
    }

    fun selectProject(projectId: Int) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(selectedProjectId = projectId)
        )
    }

    fun updateReturnDate(returnDate: String) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(returnDate = returnDate)
        )
    }

    fun updateNotes(notes: String) {
        _uiState.value = _uiState.value.copy(
            formState = _uiState.value.formState.copy(notes = notes)
        )
    }

    fun saveExternalItem() {
        val form = _uiState.value.formState

        // Validation
        if (form.description.isBlank()) {
            _uiState.value = _uiState.value.copy(
                errorMessage = "Beschreibung ist erforderlich"
            )
            return
        }

        if (form.ownerName.isBlank()) {
            _uiState.value = _uiState.value.copy(
                errorMessage = "Eigentümer ist erforderlich"
            )
            return
        }

        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(isSaving = true)
                val result = repository.createExternalItem(
                    description = form.description,
                    ownerName = form.ownerName,
                    ownerContact = form.ownerContact.takeIf { it.isNotBlank() },
                    quantity = form.quantity,
                    projectId = form.selectedProjectId,
                    returnDate = form.returnDate.takeIf { it.isNotBlank() },
                    notes = form.notes.takeIf { it.isNotBlank() }
                )

                if (result.isSuccess) {
                    val response = result.getOrNull()!!
                    _uiState.value = _uiState.value.copy(
                        successMessage = "Fremdmaterial erfasst: ${response.item?.barcode ?: ""}",
                        formState = ExternalItemFormState(),
                        isSaving = false
                    )
                    loadRecentItems()
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler beim Speichern",
                        isSaving = false
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error saving external item", e)
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Fehler",
                    isSaving = false
                )
            }
        }
    }

    fun markItemReturned(itemId: Int) {
        viewModelScope.launch {
            try {
                val result = repository.markExternalItemReturned(itemId)
                if (result.isSuccess) {
                    _uiState.value = _uiState.value.copy(
                        successMessage = "Fremdmaterial als zurückgegeben markiert"
                    )
                    loadRecentItems()
                } else {
                    _uiState.value = _uiState.value.copy(
                        errorMessage = result.exceptionOrNull()?.message ?: "Fehler"
                    )
                }
            } catch (e: Exception) {
                Log.e(tag, "Error marking item as returned", e)
                _uiState.value = _uiState.value.copy(
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
