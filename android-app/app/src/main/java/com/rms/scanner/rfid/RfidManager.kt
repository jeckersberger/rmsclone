package com.rms.scanner.rfid

interface RfidManager {
    // Connection management
    fun connect(): Boolean
    fun disconnect()
    fun isConnected(): Boolean

    // Reading operations (reads EPC + TID from tags)
    fun startInventory(callback: (RfidEvent) -> Unit)
    fun stopInventory()

    // Settings
    fun setPower(dbm: Int)
    fun getPower(): Int
    fun getBatteryLevel(): Int

    // Note: No write operations. The system uses TID-based pairing.
    // TIDs are unique hardware identifiers burned into each tag at the factory.
    // The reader reads the TID, and the server pairs it with an entity in the DB.
}
