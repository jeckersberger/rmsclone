package com.rms.scanner

import android.app.Application
import android.util.Log
import com.rms.scanner.data.preferences.AppPreferences

class RmsApp : Application() {
    companion object {
        private const val TAG = "RmsApp"
        lateinit var instance: RmsApp
            private set
    }

    lateinit var preferences: AppPreferences
        private set

    override fun onCreate() {
        super.onCreate()
        instance = this
        preferences = AppPreferences(this)

        Log.d(TAG, "RMS Scanner Application initialized")
        Log.d(TAG, "Server URL: ${preferences.getServerUrl()}")
        Log.d(TAG, "Mock Scanner: ${preferences.isUseMockScanner()}")
    }
}
