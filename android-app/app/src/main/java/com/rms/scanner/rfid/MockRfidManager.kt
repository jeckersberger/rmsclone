package com.rms.scanner.rfid

import android.os.Handler
import android.os.Looper
import android.util.Log
import java.util.concurrent.atomic.AtomicBoolean

/**
 * MockRfidManager - Mock implementation for testing without hardware
 *
 * Generates simulated tag reads every 2 seconds with realistic TID + EPC formats.
 * Useful for UI testing and development without the physical CF-H906 scanner.
 */
class MockRfidManager : RfidManager {
    private val tag = "MockRfidManager"
    private var isConnectedState = false
    private val isInventoryActive = AtomicBoolean(false)
    private var currentPower = 30  // Max power like production
    private var inventoryCallback: ((RfidEvent) -> Unit)? = null
    private val handler = Handler(Looper.getMainLooper())
    private var inventoryRunnable: Runnable? = null

    private val generatedTags = mutableSetOf<String>()

    override fun connect(): Boolean {
        return try {
            Log.d(tag, "MockRfidManager: Connecting...")
            isConnectedState = true
            inventoryCallback?.invoke(RfidEvent.Connected)
            true
        } catch (e: Exception) {
            Log.e(tag, "Connection failed", e)
            false
        }
    }

    override fun disconnect() {
        try {
            Log.d(tag, "MockRfidManager: Disconnecting...")
            stopInventory()
            isConnectedState = false
            inventoryCallback?.invoke(RfidEvent.Disconnected)
        } catch (e: Exception) {
            Log.e(tag, "Disconnect failed", e)
        }
    }

    override fun isConnected(): Boolean = isConnectedState

    override fun startInventory(callback: (RfidEvent) -> Unit) {
        if (!isConnected()) {
            callback.invoke(RfidEvent.Error("Not connected"))
            return
        }

        inventoryCallback = callback
        generatedTags.clear()
        isInventoryActive.set(true)

        callback.invoke(RfidEvent.Connected)

        inventoryRunnable = object : Runnable {
            override fun run() {
                if (isInventoryActive.get() && isConnected()) {
                    generateAndCallTag()
                    handler.postDelayed(this, 2000)
                }
            }
        }

        handler.post(inventoryRunnable!!)
        Log.d(tag, "MockRfidManager: Inventory started")
    }

    override fun stopInventory() {
        try {
            isInventoryActive.set(false)
            inventoryRunnable?.let { handler.removeCallbacks(it) }
            inventoryRunnable = null
            Log.d(tag, "MockRfidManager: Inventory stopped")
        } catch (e: Exception) {
            Log.e(tag, "Error stopping inventory", e)
        }
    }

    override fun setPower(dbm: Int) {
        if (dbm in 5..30) {
            currentPower = dbm
            Log.d(tag, "MockRfidManager: Power set to $dbm dBm")
        }
    }

    override fun getPower(): Int = currentPower

    override fun getBatteryLevel(): Int = 85

    private fun generateAndCallTag() {
        // Generate a realistic TID (unique per tag, like a real UHF chip)
        val tidHex = String.format("E200%012X", (Math.random() * 0xFFFFFFFFFFFF).toLong())

        val isNewTag = generatedTags.add(tidHex)
        val rssi = (-95..-30).random()
        val count = if (isNewTag) 1 else 2

        Log.d(tag, "MockRfidManager: Tag read — TID: $tidHex (RSSI: $rssi)")
        // In the real Chafon SDK, both EPC and TID are provided.
        // For mock, we report the TID as the primary identifier.
        inventoryCallback?.invoke(RfidEvent.TagRead(tidHex, rssi, count))
    }
}
