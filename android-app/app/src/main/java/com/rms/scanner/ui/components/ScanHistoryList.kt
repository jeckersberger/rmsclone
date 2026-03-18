package com.rms.scanner.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.theme.*
import com.rms.scanner.ui.viewmodels.ScanResult
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun ScanHistoryList(
    history: List<ScanResult>,
    modifier: Modifier = Modifier
) {
    if (history.isEmpty()) return

    val dateFormat = SimpleDateFormat("HH:mm:ss", Locale.getDefault())

    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = SurfaceCard),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Text(
                text = "Scan-Verlauf (${history.size})",
                style = MaterialTheme.typography.titleMedium,
                color = TextSecondary,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            LazyColumn(modifier = Modifier.fillMaxWidth()) {
                items(history.reversed()) { result ->
                    ScanHistoryItem(result, dateFormat)
                    if (history.indexOf(result) > 0) {
                        HorizontalDivider(
                            color = SurfaceBorder,
                            thickness = 1.dp,
                            modifier = Modifier.padding(vertical = 2.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun ScanHistoryItem(
    result: ScanResult,
    dateFormat: SimpleDateFormat
) {
    val time = dateFormat.format(Date(result.timestamp))
    val entityColor = when (result.entityType.lowercase()) {
        "asset" -> AssetColor
        "stock", "stock_item" -> StockColor
        else -> UnknownColor
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp, horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = result.entityName,
                style = MaterialTheme.typography.bodyMedium,
                color = TextPrimary,
                fontWeight = FontWeight.Medium,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = result.entityType,
                style = MaterialTheme.typography.bodySmall,
                color = entityColor
            )
        }

        Spacer(modifier = Modifier.width(8.dp))

        Text(
            text = time,
            style = MaterialTheme.typography.labelSmall,
            color = TextTertiary
        )
    }
}
