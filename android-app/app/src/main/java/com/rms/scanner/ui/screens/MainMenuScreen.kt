package com.rms.scanner.ui.screens

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CallMade
import androidx.compose.material.icons.filled.CallReceived
import androidx.compose.material.icons.filled.Checklist
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.Link
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.MoveDown
import androidx.compose.material.icons.filled.Nfc
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.ViewInAr
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.rms.scanner.ui.theme.*

data class MenuItemData(
    val label: String,
    val icon: ImageVector,
    val color: Color,
    val route: String
)

@Composable
fun MainMenuScreen(
    onNavigate: (String) -> Unit
) {
    val menuItems = listOf(
        MenuItemData("Ausleihe", Icons.Filled.CallMade, Checkout, "checkout"),
        MenuItemData("Rueckgabe", Icons.Filled.CallReceived, Checkin, "checkin"),
        MenuItemData("Kisten-Scan", Icons.Filled.ViewInAr, BoxScan, "box_scan"),
        MenuItemData("Inventur", Icons.Filled.Checklist, Inventory, "inventory"),
        MenuItemData("Tag zuordnen", Icons.Filled.Nfc, TagPair, "tag_pair"),
        MenuItemData("Packliste", Icons.Filled.LocalShipping, Packing, "packing_list"),
        MenuItemData("Fremdmaterial", Icons.Filled.Inventory2, External, "external_items"),
        MenuItemData("Umlagern", Icons.Filled.MoveDown, Relocate, "relocate"),
        MenuItemData("Einstellungen", Icons.Filled.Settings, SettingsColor, "settings")
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SurfaceLight)
    ) {
        // ── Header ──
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(SurfaceDark)
                .padding(horizontal = 20.dp, vertical = 16.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Pink accent dot (like AdminLTE sidebar indicator)
                Box(
                    modifier = Modifier
                        .size(10.dp)
                        .clip(CircleShape)
                        .background(Accent)
                )
                Spacer(modifier = Modifier.width(10.dp))
                Text(
                    text = "RMS Scanner",
                    style = MaterialTheme.typography.headlineLarge,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            }
            Text(
                text = "Funktion waehlen",
                style = MaterialTheme.typography.bodyMedium,
                color = TextSecondary,
                modifier = Modifier.padding(top = 4.dp, start = 20.dp)
            )
        }

        // ── Menu Grid ──
        LazyVerticalGrid(
            columns = GridCells.Fixed(2),
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            items(menuItems) { item ->
                MenuCard(
                    label = item.label,
                    icon = item.icon,
                    color = item.color,
                    onClick = { onNavigate(item.route) }
                )
            }
        }
    }
}

@Composable
fun MenuCard(
    label: String,
    icon: ImageVector,
    color: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()

    val bgColor by animateColorAsState(
        targetValue = if (isPressed) color.copy(alpha = 0.15f) else SurfaceCard,
        animationSpec = tween(150),
        label = "cardBg"
    )
    val iconBgColor by animateColorAsState(
        targetValue = if (isPressed) color else color.copy(alpha = 0.15f),
        animationSpec = tween(150),
        label = "iconBg"
    )
    val iconColor by animateColorAsState(
        targetValue = if (isPressed) TextOnAccent else color,
        animationSpec = tween(150),
        label = "iconColor"
    )

    Card(
        modifier = modifier
            .fillMaxWidth()
            .height(110.dp)
            .clickable(
                interactionSource = interactionSource,
                indication = null,
                onClick = onClick
            ),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = bgColor),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(14.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.Start
        ) {
            // Icon in colored circle
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(iconBgColor),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = label,
                    tint = iconColor,
                    modifier = Modifier.size(24.dp)
                )
            }

            Spacer(modifier = Modifier.height(10.dp))

            Text(
                text = label,
                style = MaterialTheme.typography.titleMedium,
                color = TextPrimary,
                fontWeight = FontWeight.SemiBold,
                fontSize = 15.sp
            )
        }
    }
}
