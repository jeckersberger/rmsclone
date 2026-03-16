package com.rms.scanner.data.api.models

data class ExternalItem(
    val id: Int,
    val description: String,
    val owner_name: String,
    val owner_contact: String? = null,
    val quantity: Int = 1,
    val barcode: String? = null,
    val rfid_tag: String? = null,
    val project_name: String? = null,
    val location_name: String? = null,
    val status: String = "bei_uns",
    val return_date: String? = null,
    val notes: String? = null
)

data class ExternalItemListResponse(
    val success: Boolean,
    val items: List<ExternalItem>? = null,
    val stats: Map<String, Int>? = null
)

data class ExternalItemCreateResponse(
    val success: Boolean,
    val id: Int? = null,
    val item: ExternalItem? = null,
    val message: String? = null
)
