package de.adamrms.scanner.api

import retrofit2.Response
import retrofit2.http.*

/**
 * AdamRMS REST API Client
 */
interface AdamRmsApi {

    /** Token-basierter Login */
    @POST("api/auth/token.php")
    suspend fun login(
        @Body credentials: LoginRequest
    ): Response<TokenResponse>

    /** Asset per Code suchen */
    @POST("api/scanner/lookup.php")
    suspend fun lookupAsset(
        @Body request: ScanRequest
    ): Response<ScanResponse>

    /** Equipment Check-in/out */
    @POST("api/scanner/checkin.php")
    suspend fun checkInOut(
        @Body request: CheckInRequest
    ): Response<ApiResponse>

    /** Packauftrag laden */
    @GET("api/portal/access.php")
    suspend fun getPackingList(
        @Query("token") token: String
    ): Response<PackingListResponse>

    /** Inventur-Scan melden */
    @POST("api/rfid/manage.php")
    suspend fun submitInventoryScan(
        @Body request: InventoryScanRequest
    ): Response<InventoryResponse>

    /** Offline-Scans synchronisieren */
    @POST("api/scanner/sync.php")
    suspend fun syncOfflineScans(
        @Body scans: List<OfflineScan>
    ): Response<SyncResponse>
}

// Data classes
data class LoginRequest(val username: String, val password: String)
data class TokenResponse(val result: Boolean, val token: String?)
data class ScanRequest(val code: String, val action: String = "lookup")
data class ScanResponse(val found: Boolean, val type: String?, val data: AssetData?)
data class AssetData(val assets_id: Int, val assets_tag: String, val assetTypes_name: String)
data class CheckInRequest(val asset_id: Int, val action: String, val notes: String?, val photo: String?)
data class ApiResponse(val result: Boolean, val message: String?)
data class PackingListResponse(val result: Boolean, val items: List<PackingItem>?)
data class PackingItem(val id: Int, val name: String, val tag: String, val checked: Boolean)
data class InventoryScanRequest(val action: String = "inventory_check", val tags: List<String>)
data class InventoryResponse(val result: Boolean, val total_expected: Int, val present: Int, val missing: List<AssetData>)
data class OfflineScan(val code: String, val action: String, val timestamp: Long, val notes: String?)
data class SyncResponse(val result: Boolean, val synced: Int)
