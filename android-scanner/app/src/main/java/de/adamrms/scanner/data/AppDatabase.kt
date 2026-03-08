package de.adamrms.scanner.data

import androidx.room.*

/**
 * Room Database fuer Offline-Faehigkeit
 */
@Database(entities = [OfflineScanEntity::class], version = 1)
abstract class AppDatabase : RoomDatabase() {
    abstract fun scanDao(): ScanDao
}

/**
 * Offline-Scan Entity - wird bei Internetverbindung synchronisiert
 */
@Entity(tableName = "offline_scans")
data class OfflineScanEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val code: String,
    val action: String,  // "checkin", "checkout", "inventory"
    val notes: String? = null,
    val photoPath: String? = null,
    val timestamp: Long = System.currentTimeMillis(),
    val synced: Boolean = false,
)

/**
 * DAO fuer Offline-Scan Operationen
 */
@Dao
interface ScanDao {
    @Insert
    suspend fun insertScan(scan: OfflineScanEntity)

    @Query("SELECT * FROM offline_scans WHERE synced = 0 ORDER BY timestamp ASC")
    suspend fun getUnsyncedScans(): List<OfflineScanEntity>

    @Query("UPDATE offline_scans SET synced = 1 WHERE id IN (:ids)")
    suspend fun markSynced(ids: List<Long>)

    @Query("DELETE FROM offline_scans WHERE synced = 1 AND timestamp < :before")
    suspend fun cleanupOldScans(before: Long)

    @Query("SELECT COUNT(*) FROM offline_scans WHERE synced = 0")
    suspend fun getUnsyncedCount(): Int
}
