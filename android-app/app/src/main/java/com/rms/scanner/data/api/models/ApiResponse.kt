package com.rms.scanner.data.api.models

import com.google.gson.JsonElement

data class ApiResponse<T>(
    val result: Boolean? = null,
    val success: Boolean? = null,
    val response: T? = null,
    val error: String? = null,
    val message: String? = null
)

data class UniversalScanRequest(
    val rfid_tag: String,
    val scan_action: String,
    val project_id: Int? = null
)

data class UniversalScanResponse(
    val tag: String? = null,
    val entity_type: String? = null,
    val entity_id: Int? = null,
    val entity_name: String? = null,
    val status: String? = null,
    val details: Map<String, String>? = null,
    // Partner/foreign entity fields
    val is_foreign: Boolean? = false,
    val owner_name: String? = null,
    val message: String? = null,
    val action_taken: String? = null,
    val entity_details: Map<String, Any?>? = null
)

data class UniversalLookupRequest(
    val rfid_tag: String
)

data class UniversalLookupResponse(
    val tag: String? = null,
    val entity_type: String? = null,
    val entity_id: Int? = null,
    val entity_name: String? = null,
    val available: Boolean? = null,
    val details: Map<String, String>? = null
)

data class BoxScanRequest(
    val rfid_tags: String  // JSON array string
)

data class BoxScanResponse(
    val tags: List<String>? = null,
    val results: List<BoxScanResultItem>? = null,
    val summary: BoxScanSummary? = null
)

data class BoxScanResultItem(
    val tag: String? = null,
    val entity_type: String? = null,
    val entity_id: Int? = null,
    val entity_name: String? = null,
    val status: String? = null,
    // Partner/foreign entity fields
    val is_foreign: Boolean? = false,
    val owner_name: String? = null,
    val entity_details: Map<String, Any?>? = null
)

data class BoxScanSummary(
    val total_tags: Int = 0,
    val assets: Int = 0,
    val stock_items: Int = 0,
    val unknown: Int = 0
)

data class BoxScanActionRequest(
    val rfid_tags: String,
    val scan_action: String,
    val project_id: Int? = null
)

data class BoxScanActionResponse(
    val success_count: Int? = null,
    val failed_count: Int? = null,
    val results: List<BoxScanActionResult>? = null
)

data class BoxScanActionResult(
    val tag: String? = null,
    val success: Boolean? = null,
    val message: String? = null
)

data class InventorySessionRequest(
    // Empty body for start_inventory
)

data class InventorySessionResponse(
    val session_id: String? = null,
    val started_at: String? = null
)

data class InventoryScanRequest(
    val session_id: String,
    val rfid_tag: String
)

data class InventoryScanResponse(
    val tag: String? = null,
    val status: String? = null,
    val scan_count: Int? = null
)

data class CompleteInventoryRequest(
    val session_id: String
)

data class CompleteInventoryResponse(
    val session_id: String? = null,
    val total_scans: Int? = null,
    val found: Int? = null,
    val missing: Int? = null,
    val unknown: Int? = null
)

data class AssignTagRequest(
    val asset_id: Int,
    val rfid_tag: String
)

data class AssignTagResponse(
    val asset_id: Int? = null,
    val rfid_tag: String? = null,
    val success: Boolean? = null
)

data class ListAssetsResponse(
    val assets: List<AssetItem>? = null
)

data class AssetItem(
    val id: Int,
    val name: String,
    val type: String,
    val current_rfid: String? = null,
    val status: String? = null
)

data class ListProjectsResponse(
    val projects: List<ProjectItem>? = null
)

data class ProjectItem(
    val id: Int,
    val name: String,
    val description: String? = null
)

data class ListStockItemsResponse(
    val success: Boolean? = null,
    val items: List<StockItemType>? = null
)

data class StockItemType(
    val id: Int,
    val name: String,
    val description: String? = null,
    val category: String? = null
)

data class ListStockInstancesRequest(
    val item_id: Int
)

data class ListStockInstancesResponse(
    val success: Boolean? = null,
    val instances: List<StockInstance>? = null
)

data class StockInstance(
    val id: Int,
    val item_id: Int,
    val item_name: String? = null,
    val serial_number: String? = null,
    val rfid_tag: String? = null,
    val status: String? = null
)

data class AssignStockRfidRequest(
    val instance_id: Int,
    val rfid_tag: String
)

data class AssignStockRfidResponse(
    val success: Boolean? = null,
    val instance_id: Int? = null,
    val rfid_tag: String? = null,
    val message: String? = null
)
