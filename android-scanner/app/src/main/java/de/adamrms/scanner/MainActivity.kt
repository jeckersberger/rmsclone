package de.adamrms.scanner

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController

/**
 * AdamRMS Scanner - Hauptaktivitaet
 *
 * Navigation:
 * - Scanner: Barcode/QR-Code scannen und Asset finden
 * - Check-in/out: Equipment ein-/auschecken mit Foto
 * - Packauftrag: Lieferschein-QR scannen, Positionen abhaken
 * - Inventur: Assets scannen und mit Soll abgleichen
 */
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                AdamRMSScannerApp()
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AdamRMSScannerApp() {
    val navController = rememberNavController()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("AdamRMS Scanner") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary,
                )
            )
        },
        bottomBar = {
            NavigationBar {
                NavigationBarItem(
                    selected = true,
                    onClick = { navController.navigate("scanner") },
                    icon = { Text("📷") },
                    label = { Text("Scanner") }
                )
                NavigationBarItem(
                    selected = false,
                    onClick = { navController.navigate("checkin") },
                    icon = { Text("📋") },
                    label = { Text("Check-in") }
                )
                NavigationBarItem(
                    selected = false,
                    onClick = { navController.navigate("packing") },
                    icon = { Text("📦") },
                    label = { Text("Packen") }
                )
                NavigationBarItem(
                    selected = false,
                    onClick = { navController.navigate("inventory") },
                    icon = { Text("📊") },
                    label = { Text("Inventur") }
                )
            }
        }
    ) { padding ->
        NavHost(
            navController = navController,
            startDestination = "scanner",
            modifier = Modifier.padding(padding)
        ) {
            composable("scanner") {
                // TODO: ScannerScreen mit CameraX + ML Kit
                PlaceholderScreen("Scanner", "Barcode/QR-Code scannen um Equipment zu finden")
            }
            composable("checkin") {
                PlaceholderScreen("Check-in/out", "Equipment ein- oder auschecken mit Zustandsprotokoll")
            }
            composable("packing") {
                PlaceholderScreen("Packauftrag", "Lieferschein-QR scannen und Positionen abhaken")
            }
            composable("inventory") {
                PlaceholderScreen("Inventur", "Assets scannen und mit Soll-Bestand abgleichen")
            }
        }
    }
}

@Composable
fun PlaceholderScreen(title: String, description: String) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(androidx.compose.ui.unit.dp.times(24)),
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.headlineMedium
        )
        Spacer(modifier = Modifier.height(androidx.compose.ui.unit.dp.times(8)))
        Text(
            text = description,
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

private fun androidx.compose.ui.unit.Dp.Companion.times(value: Int) = (value).dp
