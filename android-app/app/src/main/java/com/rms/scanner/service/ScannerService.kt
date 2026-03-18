package com.rms.scanner.service

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.util.Log
import androidx.core.app.NotificationCompat
import com.rms.scanner.MainActivity
import com.rms.scanner.R

/**
 * ScannerService - Foreground service for continuous RFID scanning
 *
 * This service runs in the foreground to prevent the app from being killed
 * while scanning is in progress. It displays a persistent notification.
 */
class ScannerService : Service() {
    private val tag = "ScannerService"
    private val notificationId = 1

    override fun onCreate() {
        super.onCreate()
        Log.d(tag, "ScannerService created")
        createNotificationChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        Log.d(tag, "ScannerService started")

        val notification = createNotification()
        startForeground(notificationId, notification)

        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? {
        return null
    }

    override fun onDestroy() {
        super.onDestroy()
        Log.d(tag, "ScannerService destroyed")
        @Suppress("DEPRECATION")
        stopForeground(true)
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channelId = "rms_scanner_channel"
            val channelName = "RMS Scanner"
            val importance = NotificationManager.IMPORTANCE_LOW
            val channel = NotificationChannel(channelId, channelName, importance)
            channel.description = "Notifications for RFID scanning"

            val notificationManager = getSystemService(NotificationManager::class.java)
            notificationManager?.createNotificationChannel(channel)
        }
    }

    private fun createNotification(): Notification {
        val channelId = "rms_scanner_channel"
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        return NotificationCompat.Builder(this, channelId)
            .setContentTitle("RMS Scanner")
            .setContentText("Scanner läuft...")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()
    }
}
