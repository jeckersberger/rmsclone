package com.rms.scanner.data.preferences

import android.content.Context
import android.content.SharedPreferences

class AppPreferences(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences(
        "rms_scanner_prefs",
        Context.MODE_PRIVATE
    )

    companion object {
        private const val KEY_SERVER_URL = "server_url"
        private const val KEY_USERNAME = "username"
        private const val KEY_PASSWORD = "password"
        private const val KEY_SCANNER_POWER = "scanner_power"
        private const val KEY_SOUND_ENABLED = "sound_enabled"
        private const val KEY_VIBRATION_ENABLED = "vibration_enabled"
        private const val KEY_USE_MOCK_SCANNER = "use_mock_scanner"
        private const val KEY_LAST_SESSION_ID = "last_session_id"

        private const val DEFAULT_SERVER_URL = "http://192.168.1.100/"
        private const val DEFAULT_SCANNER_POWER = 15
        private const val DEFAULT_SOUND_ENABLED = true
        private const val DEFAULT_VIBRATION_ENABLED = true
        private const val DEFAULT_USE_MOCK_SCANNER = false
    }

    fun getServerUrl(): String = prefs.getString(KEY_SERVER_URL, DEFAULT_SERVER_URL) ?: DEFAULT_SERVER_URL

    fun setServerUrl(url: String) {
        prefs.edit().putString(KEY_SERVER_URL, url).apply()
    }

    fun getUsername(): String? = prefs.getString(KEY_USERNAME, null)

    fun setUsername(username: String?) {
        if (username == null) {
            prefs.edit().remove(KEY_USERNAME).apply()
        } else {
            prefs.edit().putString(KEY_USERNAME, username).apply()
        }
    }

    fun getPassword(): String? = prefs.getString(KEY_PASSWORD, null)

    fun setPassword(password: String?) {
        if (password == null) {
            prefs.edit().remove(KEY_PASSWORD).apply()
        } else {
            prefs.edit().putString(KEY_PASSWORD, password).apply()
        }
    }

    fun getScannerPower(): Int = prefs.getInt(KEY_SCANNER_POWER, DEFAULT_SCANNER_POWER)

    fun setScannerPower(power: Int) {
        prefs.edit().putInt(KEY_SCANNER_POWER, power).apply()
    }

    fun isSoundEnabled(): Boolean = prefs.getBoolean(KEY_SOUND_ENABLED, DEFAULT_SOUND_ENABLED)

    fun setSoundEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_SOUND_ENABLED, enabled).apply()
    }

    fun isVibrationEnabled(): Boolean = prefs.getBoolean(KEY_VIBRATION_ENABLED, DEFAULT_VIBRATION_ENABLED)

    fun setVibrationEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_VIBRATION_ENABLED, enabled).apply()
    }

    fun isUseMockScanner(): Boolean = prefs.getBoolean(KEY_USE_MOCK_SCANNER, DEFAULT_USE_MOCK_SCANNER)

    fun setUseMockScanner(useMock: Boolean) {
        prefs.edit().putBoolean(KEY_USE_MOCK_SCANNER, useMock).apply()
    }

    fun getLastSessionId(): String? = prefs.getString(KEY_LAST_SESSION_ID, null)

    fun setLastSessionId(sessionId: String?) {
        if (sessionId == null) {
            prefs.edit().remove(KEY_LAST_SESSION_ID).apply()
        } else {
            prefs.edit().putString(KEY_LAST_SESSION_ID, sessionId).apply()
        }
    }

    fun clear() {
        prefs.edit().clear().apply()
    }
}
