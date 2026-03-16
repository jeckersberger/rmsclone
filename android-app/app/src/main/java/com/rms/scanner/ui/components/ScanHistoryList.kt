package com.rms.scanner.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Divider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.rms.scanner.ui.theme.TextSecondary
import com.rms.scanner.ui.viewmodels.ScanResult
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun ScanHistoryList(
    history: List<ScanResult>,
    modifier: Modifier = Modifier
) {
    if (history.isEmpty()) {
        return
    }

    val dateFormat = SimpleDateFormat("HH:mm:ss", Locale.getDefault())

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(Color(0xFF111827), RoundedCornerShape(12.dp))
            .padding(12.dp)
    ) {
        Text(
            text = "Scan-Verlauf (${history.size})",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        LazyColumn(modifier = Modifier.fillMaxWidth()) {
            items(history) { result ->
                ScanHistoryItem(result, dateFormat)
                if (history.indexOf(result) < history.size - 1) {
                    Divider(
                        color = Color(0xFF374151),
                        thickness = 1.dp,
                        modifier = Modifier.padding(vertical = 4.dp)
                    )
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

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(8.dp)
    ) {
        Text(
            text = result.entityName,
            style = MaterialTheme.typography.bodyMedium
        )
        Text(
            text = "${result.entityType} • $time",
            style = MaterialTheme.typography.bodySmall,
            color = TextSecondary
        )
    }
}
