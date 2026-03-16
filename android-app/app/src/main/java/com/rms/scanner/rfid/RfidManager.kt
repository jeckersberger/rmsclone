package com.rms.scanner.rfid

interface RfidManager {
    // Connection management
    fun connect(): Boolean
    fun disconnect()
    fun isConnected(): Boolean

    // Reading operations
    fun startInventory(callback: (RfidEvent) -> Unit)
    fun stopInventory()

    // Writing operations
    fun writeEpc(newEpc: String): Boolean
    fun writeEpc(oldEpc: String, newEpc: String): Boolean

    // Settings
    fun setPower(dbm: Int)
    fun getPower(): Int
    fun getBatteryLevel(): Int
}
