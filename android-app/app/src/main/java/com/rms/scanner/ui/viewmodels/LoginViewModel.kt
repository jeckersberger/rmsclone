package com.rms.scanner.ui.viewmodels

import android.content.Context
import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.rms.scanner.data.api.RmsApiClient
import com.rms.scanner.data.preferences.AppPreferences
import com.rms.scanner.data.repository.RmsRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

data class LoginUiState(
    val serverUrl: String = "http://192.168.1.100/",
    val username: String = "",
    val password: String = "",
    val isConnecting: Boolean = false,
    val connectionStatus: String = "",
    val isConnected: Boolean = false,
    val errorMessage: String? = null
)

class LoginViewModel(context: Context) : ViewModel() {
    private val tag = "LoginViewModel"
    private val prefs = AppPreferences(context)
    private val repository = RmsRepository()

    private val _uiState = MutableStateFlow(
        LoginUiState(
            serverUrl = prefs.getServerUrl(),
            username = prefs.getUsername() ?: "",
            password = prefs.getPassword() ?: ""
        )
    )
    val uiState: StateFlow<LoginUiState> = _uiState

    fun updateServerUrl(url: String) {
        _uiState.value = _uiState.value.copy(serverUrl = url)
    }

    fun updateUsername(username: String) {
        _uiState.value = _uiState.value.copy(username = username)
    }

    fun updatePassword(password: String) {
        _uiState.value = _uiState.value.copy(password = password)
    }

    fun testConnection() {
        viewModelScope.launch {
            try {
                _uiState.value = _uiState.value.copy(
                    isConnecting = true,
                    connectionStatus = "Verbindung wird getestet...",
                    errorMessage = null
                )

                val url = _uiState.value.serverUrl.trim()
                if (url.isEmpty()) {
                    _uiState.value = _uiState.value.copy(
                        isConnecting = false,
                        errorMessage = "Server URL erforderlich"
                    )
                    return@launch
                }

                // Set the base URL in the API client
                RmsApiClient.setBaseUrl(url)

                // Try a simple API call to verify connection
                val result = repository.listProjects()

                if (result.isSuccess) {
                    // Save preferences
                    prefs.setServerUrl(url)
                    prefs.setUsername(_uiState.value.username)
                    prefs.setPassword(_uiState.value.password)

                    _uiState.value = _uiState.value.copy(
                        isConnecting = false,
                        connectionStatus = "Verbunden",
                        isConnected = true,
                        errorMessage = null
                    )
                    Log.d(tag, "Connection successful")
                } else {
                    _uiState.value = _uiState.value.copy(
                        isConnecting = false,
                        connectionStatus = "Verbindung fehlgeschlagen",
                        isConnected = false,
                        errorMessage = result.exceptionOrNull()?.message ?: "Unbekannter Fehler"
                    )
                    Log.e(tag, "Connection failed", result.exceptionOrNull())
                }
            } catch (e: Exception) {
                Log.e(tag, "Connection error", e)
                _uiState.value = _uiState.value.copy(
                    isConnecting = false,
                    connectionStatus = "Fehler",
                    isConnected = false,
                    errorMessage = e.message ?: "Verbindungsfehler"
                )
            }
        }
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(errorMessage = null)
    }
}
