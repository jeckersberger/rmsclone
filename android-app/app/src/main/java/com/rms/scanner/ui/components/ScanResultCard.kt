package com.rms.scanner.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.theme.AssetColor
import com.rms.scanner.ui.theme.StockColor
import com.rms.scanner.ui.theme.TextPrimary
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.theme.UnknownColor
import com.rms.scanner.ui.viewmodels.ScanResult

@Composable
fun ScanResultCard(
    result: ScanResult,
    modifier: Modifier = Modifier
) {
    val entityColor = when (result.entityType.lowercase()) {
        "asset" -> AssetColor
        "stock", "stock_item" -> StockColor
        else -> UnknownColor
    }

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(Color(0xFF1F2937), RoundedCornerShape(12.dp))
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Tag: ${result.tag}",
                    style = MaterialTheme.typography.bodySmall,
                    color = TextSecondary,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
                Text(
                    text = result.entityName,
                    style = MaterialTheme.typography.headlineSmall,
                    color = TextPrimary,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
                Text(
                    text = result.entityType,
                    style = MaterialTheme.typography.bodySmall,
                    color = entityColor
                )
            }
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Status: ${result.status}",
                style = MaterialTheme.typography.bodyMedium,
                color = TextPrimary,
                modifier = Modifier.weight(1f)
            )
            EntityBadge(
                type = result.entityType,
                modifier = Modifier.size(32.dp)
            )
        }
    }
}

@Composable
fun EntityBadge(
    type: String,
    modifier: Modifier = Modifier
) {
    val backgroundColor = when (type.lowercase()) {
        "asset" -> AssetColor
        "stock", "stock_item" -> StockColor
        else -> UnknownColor
    }

    val label = when (type.lowercase()) {
        "asset" -> "A"
        "stock", "stock_item" -> "I"
        else -> "?"
    }

    androidx.compose.material3.Surface(
        modifier = modifier,
        shape = RoundedCornerShape(16.dp),
        color = backgroundColor
    ) {
        Text(
            text = label,
            modifier = Modifier.padding(4.dp),
            color = TextPrimary,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
fun BoxResultGroup(
    title: String,
    results: List<String>,
    color: Color,
    modifier: Modifier = Modifier
) {
    if (results.isEmpty()) return

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(Color(0xFF1F2937), RoundedCornerShape(12.dp))
            .padding(16.dp)
    ) {
        Text(
            text = "$title (${results.size})",
            style = MaterialTheme.typography.headlineSmall,
            color = color,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = 12.dp)
        )

        results.forEach { result ->
            Text(
                text = "• $result",
                style = MaterialTheme.typography.bodyMedium,
                color = TextSecondary,
                modifier = Modifier.padding(vertical = 4.dp)
            )
        }
    }
}

@Composable
fun StatusIndicator(
    isActive: Boolean,
    label: String = "",
    modifier: Modifier = Modifier
) {
    val color = if (isActive) Color(0xFF10B981) else Color(0xFF6B7280)
    val text = if (isActive) "AKTIV" else "INAKTIV"

    Row(
        modifier = modifier,
        verticalAlignment = Alignment.CenterVertically
    ) {
        androidx.compose.foundation.background(
            color,
            RoundedCornerShape(50.dp)
        )
        androidx.compose.foundation.layout.Box(
            modifier = Modifier
                .size(12.dp)
                .background(color, RoundedCornerShape(50.dp))
        )

        Text(
            text = "$text $label",
            style = MaterialTheme.typography.labelMedium,
            color = color,
            modifier = Modifier.padding(start = 8.dp)
        )
    }
}
