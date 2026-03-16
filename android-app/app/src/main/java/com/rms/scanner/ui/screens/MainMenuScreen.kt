package com.rms.scanner.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.theme.BoxScan
import com.rms.scanner.ui.theme.Checkout
import com.rms.scanner.ui.theme.Checkin
import com.rms.scanner.ui.theme.Inventory
import com.rms.scanner.ui.theme.Settings
import com.rms.scanner.ui.theme.SurfaceLight
import com.rms.scanner.ui.theme.Success
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import androidx.compose.material.icons.filled.Checklist
import androidx.compose.material.icons.filled.Handshake

data class MenuItemData(
    val label: String,
    val color: androidx.compose.ui.graphics.Color,
    val route: String
)

@Composable
fun MainMenuScreen(
    onNavigate: (String) -> Unit
) {
    val menuItems = listOf(
        MenuItemData("Ausleihe", Checkout, "checkout"),
        MenuItemData("Rückgabe", Checkin, "checkin"),
        MenuItemData("Kisten-Scan", BoxScan, "box_scan"),
        MenuItemData("Inventur", Inventory, "inventory"),
        MenuItemData("Tag zuordnen", Success, "tag_pair"),
        MenuItemData("Packliste", androidx.compose.ui.graphics.Color(0xFF06B6D4), "packing_list"),
        MenuItemData("Fremdmaterial", androidx.compose.ui.graphics.Color(0xFFF43F5E), "external_items"),
        MenuItemData("Einstellungen", Settings, "settings"),
        MenuItemData("Umlagern", Success, "relocate")
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
            .padding(16.dp)
    ) {
        Text(
            text = "RMS Scanner",
            style = MaterialTheme.typography.displaySmall,
            color = TextPrimary,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        Text(
            text = "Wählen Sie eine Funktion",
            style = MaterialTheme.typography.titleMedium,
            color = TextSecondary,
            modifier = Modifier.padding(bottom = 24.dp)
        )

        LazyVerticalGrid(
            columns = GridCells.Fixed(3),
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(12.dp),
            verticalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(12.dp)
        ) {
            items(menuItems.size) { index ->
                val item = menuItems[index]
                MenuTile(
                    label = item.label,
                    color = item.color,
                    onClick = { onNavigate(item.route) }
                )
            }
        }
    }
}

@Composable
fun MenuTile(
    label: String,
    color: androidx.compose.ui.graphics.Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Button(
        onClick = onClick,
        modifier = modifier
            .padding(vertical = 12.dp),
        colors = ButtonDefaults.buttonColors(
            containerColor = color,
            contentColor = TextPrimary
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = label,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold
            )
        }
    }
}
