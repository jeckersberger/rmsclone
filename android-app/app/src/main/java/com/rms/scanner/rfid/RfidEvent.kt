package com.rms.scanner.rfid

sealed class RfidEvent {
    data class TagRead(
        val epc: String,
        val rssi: Int = 0,
        val count: Int = 1
        // Note: In the real Chafon SDK integration, the TID is typically
        // available alongside the EPC. The epc field here may contain either
        // the EPC or the TID depending on the reader configuration.
        // For TID-based pairing, configure the reader to report TIDs.
    ) : RfidEvent()

    data class Error(
        val message: String,
        val throwable: Throwable? = null
    ) : RfidEvent()

    object Connected : RfidEvent()
    object Disconnected : RfidEvent()
}
