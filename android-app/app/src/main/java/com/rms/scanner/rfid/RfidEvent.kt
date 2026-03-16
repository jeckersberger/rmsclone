package com.rms.scanner.rfid

sealed class RfidEvent {
    data class TagRead(
        val epc: String,
        val rssi: Int = 0,
        val count: Int = 1
    ) : RfidEvent()

    data class TagWritten(
        val epc: String,
        val success: Boolean,
        val errorMessage: String? = null
    ) : RfidEvent()

    data class Error(
        val message: String,
        val throwable: Throwable? = null
    ) : RfidEvent()

    object Connected : RfidEvent()
    object Disconnected : RfidEvent()
}
