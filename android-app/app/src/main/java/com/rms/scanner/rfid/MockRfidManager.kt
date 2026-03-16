package com.rms.scanner.rfid

import android.os.Handler
import android.os.Looper
import android.util.Log
import java.util.concurrent.atomic.AtomicBoolean

/**
 * MockRfidManager - Mock implementation for testing without hardware
 *
 * Generates simulated tag reads every 2 seconds with realistic EPC formats.
 * Useful for UI testing and development without the physical CF-H906 scanner.
 */
class MockRfidManager : RfidManager {
    private val tag = "MockRfidManager"
    private var isConnectedState = false
    private val isInventoryActive = AtomicBoolean(false)
    private var currentPower = 15
    private var inventoryCallback: ((RfidEvent) -> Unit)? = null
    private val handler = Handler(Looper.getMainLooper())
    private var inventoryRunnable: Runnable? = null

    private val tagPrefixes = listOf("RMS-A", "RMS-I", "RMS-X", "RMS-E")
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

        // Generate initial connected event
        callback.invoke(RfidEvent.Connected)

        // Schedule periodic tag generation
        inventoryRunnable = object : Runnable {
            override fun run() {
                if (isInventoryActive.get() && isConnected()) {
                    generateAndCallTag()
                    handler.postDelayed(this, 2000)  // Every 2 seconds
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

    override fun writeEpc(newEpc: String): Boolean {
        if (!isConnected()) return false

        return try {
            Log.d(tag, "MockRfidManager: Writing EPC: $newEpc")
            inventoryCallback?.invoke(RfidEvent.TagWritten(newEpc, true))
            true
        } catch (e: Exception) {
            Log.e(tag, "Write failed", e)
            inventoryCallback?.invoke(RfidEvent.TagWritten(newEpc, false, e.message))
            false
        }
    }

    override fun writeEpc(oldEpc: String, newEpc: String): Boolean {
        if (!isConnected()) return false

        return try {
            Log.d(tag, "MockRfidManager: Writing EPC from $oldEpc to $newEpc")
            inventoryCallback?.invoke(RfidEvent.TagWritten(newEpc, true))
            true
        } catch (e: Exception) {
            Log.e(tag, "Write failed", e)
            inventoryCallback?.invoke(RfidEvent.TagWritten(newEpc, false, e.message))
            false
        }
    }

    override fun setPower(dbm: Int) {
        if (dbm in 5..30) {
            currentPower = dbm
            Log.d(tag, "MockRfidManager: Power set to $dbm dBm")
        }
    }

    override fun getPower(): Int = currentPower

    override fun getBatteryLevel(): Int = 85  // Mock battery level

    private fun generateAndCallTag() {
        val prefix = tagPrefixes.random()
        val randomNum = (1..9999).random()
        val epc = "$prefix-${String.format("%06d", randomNum)}"

        // Occasionally generate unknown tags
        val finalEpc = if (Math.random() < 0.1) {
            "UNKNOWN-${String.format("%08X", (Math.random() * 0xFFFFFFF).toLong())}"
        } else {
            epc
        }

        val isNewTag = generatedTags.add(finalEpc)
        val rssi = (-95..-30).random()
        val count = if (isNewTag) 1 else (generatedTags.count { it == finalEpc })

        Log.d(tag, "MockRfidManager: Tag read: $finalEpc (RSSI: $rssi, Count: $count)")
        inventoryCallback?.invoke(RfidEvent.TagRead(finalEpc, rssi, count))
    }
}
