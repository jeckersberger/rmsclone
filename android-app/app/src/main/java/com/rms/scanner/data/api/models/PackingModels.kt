package com.rms.scanner.data.api.models

data class PackingListResponse(
    val success: Boolean,
    val assets: List<PackingItemData>? = null,
    val stock_instances: List<PackingItemData>? = null,
    val external_items: List<PackingItemData>? = null,
    val progress: PackingProgress? = null
)

data class PackingItemData(
    val id: Int,
    val display_name: String? = null,
    val entity_type: String? = null,
    val scan_code: String? = null,
    val rfid_tag: String? = null,
    val barcode: String? = null,
    val checked: Boolean = false,
    val checked_at: String? = null
)

data class PackingProgress(
    val total: Int = 0,
    val checked: Int = 0,
    val percent: Int = 0
)

data class PackingCheckResponse(
    val success: Boolean,
    val entity_type: String? = null,
    val entity_id: Int? = null,
    val message: String? = null
)
