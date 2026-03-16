package com.rms.scanner.data.api

import com.rms.scanner.data.api.models.*
import retrofit2.http.Field
import retrofit2.http.FormUrlEncoded
import retrofit2.http.POST

interface RmsApiService {
    // RFID Scan endpoints
    @FormUrlEncoded
    @POST("rfid/scan.php?action=universal_scan")
    suspend fun universalScan(
        @Field("rfid_tag") rfidTag: String,
        @Field("scan_action") scanAction: String,
        @Field("project_id") projectId: Int? = null
    ): ApiResponse<UniversalScanResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=universal_lookup")
    suspend fun universalLookup(
        @Field("rfid_tag") rfidTag: String
    ): ApiResponse<UniversalLookupResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=box_scan")
    suspend fun boxScan(
        @Field("rfid_tags") rfidTags: String
    ): ApiResponse<BoxScanResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=box_scan_action")
    suspend fun boxScanAction(
        @Field("rfid_tags") rfidTags: String,
        @Field("scan_action") scanAction: String,
        @Field("project_id") projectId: Int? = null
    ): ApiResponse<BoxScanActionResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=start_inventory")
    suspend fun startInventory(): ApiResponse<InventorySessionResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=inventory_scan")
    suspend fun inventoryScan(
        @Field("session_id") sessionId: String,
        @Field("rfid_tag") rfidTag: String
    ): ApiResponse<InventoryScanResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=complete_inventory")
    suspend fun completeInventory(
        @Field("session_id") sessionId: String
    ): ApiResponse<CompleteInventoryResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=assign_tag")
    suspend fun assignTag(
        @Field("asset_id") assetId: Int,
        @Field("rfid_tag") rfidTag: String
    ): ApiResponse<AssignTagResponse>

    @FormUrlEncoded
    @POST("rfid/scan.php?action=list_assets")
    suspend fun listAssets(): ApiResponse<ListAssetsResponse>

    // Stock endpoints
    @FormUrlEncoded
    @POST("stock/items.php?action=list_items")
    suspend fun listStockItems(): ApiResponse<ListStockItemsResponse>

    @FormUrlEncoded
    @POST("stock/items.php?action=list_instances")
    suspend fun listStockInstances(
        @Field("item_id") itemId: Int
    ): ApiResponse<ListStockInstancesResponse>

    @FormUrlEncoded
    @POST("stock/items.php?action=assign_rfid")
    suspend fun assignStockRfid(
        @Field("instance_id") instanceId: Int,
        @Field("rfid_tag") rfidTag: String
    ): ApiResponse<AssignStockRfidResponse>

    // Project endpoints
    @FormUrlEncoded
    @POST("assets/projects.php?action=list")
    suspend fun listProjects(): ApiResponse<ListProjectsResponse>

    // Location endpoints
    @FormUrlEncoded
    @POST("locations.php")
    suspend fun listLocations(
        @Field("action") action: String = "list_locations"
    ): LocationListResponse

    @FormUrlEncoded
    @POST("locations.php")
    suspend fun scanAssignLocation(
        @Field("action") action: String = "scan_assign",
        @Field("rfid_tag") rfidTag: String,
        @Field("location_id") locationId: Int? = null,
        @Field("location_custom") locationCustom: String? = null,
        @Field("notes") notes: String? = null
    ): LocationScanResponse

    @FormUrlEncoded
    @POST("locations.php")
    suspend fun bulkAssignLocation(
        @Field("action") action: String = "bulk_assign",
        @Field("rfid_tags") rfidTags: String,
        @Field("location_id") locationId: Int? = null,
        @Field("location_custom") locationCustom: String? = null,
        @Field("notes") notes: String? = null
    ): BulkLocationResponse

    // Packing list endpoints
    @FormUrlEncoded
    @POST("packing/list.php")
    suspend fun getPackingList(
        @Field("action") action: String = "get_list",
        @Field("project_id") projectId: Int
    ): PackingListResponse

    @FormUrlEncoded
    @POST("packing/list.php")
    suspend fun checkPackingItem(
        @Field("action") action: String = "check_item",
        @Field("entity_type") entityType: String,
        @Field("entity_id") entityId: Int,
        @Field("project_id") projectId: Int
    ): PackingCheckResponse

    @FormUrlEncoded
    @POST("packing/list.php")
    suspend fun uncheckPackingItem(
        @Field("action") action: String = "uncheck_item",
        @Field("entity_type") entityType: String,
        @Field("entity_id") entityId: Int,
        @Field("project_id") projectId: Int
    ): PackingCheckResponse

    @FormUrlEncoded
    @POST("packing/list.php")
    suspend fun scanCheckPackingItem(
        @Field("action") action: String = "scan_check",
        @Field("scan_value") scanValue: String,
        @Field("project_id") projectId: Int
    ): PackingCheckResponse

    // External items endpoints
    @FormUrlEncoded
    @POST("external/items.php")
    suspend fun listExternalItems(
        @Field("action") action: String = "list",
        @Field("status") status: String? = null
    ): ExternalItemListResponse

    @FormUrlEncoded
    @POST("external/items.php")
    suspend fun createExternalItem(
        @Field("action") action: String = "create",
        @Field("description") description: String,
        @Field("owner_name") ownerName: String,
        @Field("owner_contact") ownerContact: String? = null,
        @Field("quantity") quantity: Int = 1,
        @Field("project_id") projectId: Int? = null,
        @Field("return_date") returnDate: String? = null,
        @Field("notes") notes: String? = null
    ): ExternalItemCreateResponse

    @FormUrlEncoded
    @POST("external/items.php")
    suspend fun markExternalItemReturned(
        @Field("action") action: String = "mark_returned",
        @Field("id") id: Int
    ): ExternalItemCreateResponse

    // Case Contents
    @FormUrlEncoded
    @POST("api/cases/contents.php")
    suspend fun listCases(
        @Field("action") action: String = "list_cases"
    ): ApiResponse

    @FormUrlEncoded
    @POST("api/cases/contents.php")
    suspend fun getCaseContents(
        @Field("action") action: String = "get_contents",
        @Field("case_asset_id") caseAssetId: Int
    ): ApiResponse

    @FormUrlEncoded
    @POST("api/cases/contents.php")
    suspend fun verifyCaseScan(
        @Field("action") action: String = "verify_scan",
        @Field("case_asset_id") caseAssetId: Int,
        @Field("scanned_items") scannedItemsJson: String
    ): ApiResponse

    @FormUrlEncoded
    @POST("api/cases/contents.php")
    suspend fun logCaseCheck(
        @Field("action") action: String = "log_check",
        @Field("case_asset_id") caseAssetId: Int,
        @Field("project_id") projectId: Int,
        @Field("check_type") checkType: String,
        @Field("result") resultJson: String
    ): ApiResponse

    @FormUrlEncoded
    @POST("api/cases/contents.php")
    suspend fun acknowledgeCaseDiscrepancy(
        @Field("action") action: String = "acknowledge",
        @Field("check_id") checkId: Int
    ): ApiResponse

    // Company Code
    @FormUrlEncoded
    @POST("api/settings/companyCode.php")
    suspend fun getCompanyCode(
        @Field("action") action: String = "get_code"
    ): ApiResponse

    // Binary EPC encode/decode for RFID tag memory
    @FormUrlEncoded
    @POST("api/rfid/scan.php")
    suspend fun getWriteEpc(
        @Field("action") action: String = "get_write_epc",
        @Field("entity_type") entityType: String,
        @Field("entity_id") entityId: Int
    ): ApiResponse

    @FormUrlEncoded
    @POST("api/rfid/scan.php")
    suspend fun decodeEpc(
        @Field("action") action: String = "decode_epc",
        @Field("epc_hex") epcHex: String
    ): ApiResponse
}
