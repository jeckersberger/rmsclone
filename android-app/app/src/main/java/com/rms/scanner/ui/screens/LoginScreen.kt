package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.theme.Primary
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.viewmodels.LoginViewModel

@Composable
fun LoginScreen(
    viewModel: LoginViewModel,
    onLoginSuccess: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Header
        Text(
            text = "RMS Scanner",
            style = MaterialTheme.typography.displaySmall,
            color = TextPrimary,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(vertical = 32.dp)
        )

        Text(
            text = "Verbindungseinstellungen",
            style = MaterialTheme.typography.titleLarge,
            color = TextSecondary,
            modifier = Modifier
                .align(Alignment.Start)
                .padding(bottom = 24.dp)
        )

        // Server URL Input
        OutlinedTextField(
            value = uiState.serverUrl,
            onValueChange = { viewModel.updateServerUrl(it) },
            label = { Text("Server URL") },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp),
            enabled = !uiState.isConnecting,
            colors = TextFieldDefaults.colors(
                focusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                unfocusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary
            ),
            singleLine = true
        )

        // Optional Username
        OutlinedTextField(
            value = uiState.username,
            onValueChange = { viewModel.updateUsername(it) },
            label = { Text("Benutzername (optional)") },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp),
            enabled = !uiState.isConnecting,
            colors = TextFieldDefaults.colors(
                focusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                unfocusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary
            ),
            singleLine = true
        )

        // Optional Password
        OutlinedTextField(
            value = uiState.password,
            onValueChange = { viewModel.updatePassword(it) },
            label = { Text("Passwort (optional)") },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 32.dp),
            enabled = !uiState.isConnecting,
            colors = TextFieldDefaults.colors(
                focusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                unfocusedContainerColor = com.rms.scanner.ui.theme.SurfaceDark,
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary
            ),
            singleLine = true
        )

        // Connection Status
        if (uiState.connectionStatus.isNotEmpty()) {
            Text(
                text = uiState.connectionStatus,
                style = MaterialTheme.typography.bodyMedium,
                color = if (uiState.isConnected) androidx.compose.material3.tokens.ColorSchemeKeyTokens.Outline else TextSecondary,
                modifier = Modifier.padding(bottom = 16.dp)
            )
        }

        // Error Message
        if (uiState.errorMessage != null) {
            Text(
                text = uiState.errorMessage!!,
                style = MaterialTheme.typography.bodyMedium,
                color = androidx.compose.material3.tokens.ColorSchemeKeyTokens.Error,
                modifier = Modifier.padding(bottom = 16.dp)
            )
        }

        // Connect Button
        Button(
            onClick = {
                viewModel.testConnection()
            },
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 16.dp)
                .size(height = 56.dp, width = 200.dp),
            enabled = !uiState.isConnecting,
            colors = ButtonDefaults.buttonColors(
                containerColor = Primary,
                contentColor = TextPrimary
            )
        ) {
            if (uiState.isConnecting) {
                CircularProgressIndicator(
                    modifier = Modifier.size(24.dp),
                    color = TextPrimary
                )
            } else {
                Text(
                    text = "Verbinden",
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold
                )
            }
        }

        // Auto navigate on success
        if (uiState.isConnected) {
            LaunchedEffect(Unit) {
                onLoginSuccess()
            }
        }
    }
}

import androidx.compose.runtime.LaunchedEffect
