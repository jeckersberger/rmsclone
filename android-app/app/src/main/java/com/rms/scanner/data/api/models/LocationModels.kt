package com.rms.scanner.data.api.models

data class Location(
    val id: Int,
    val name: String,
    val description: String? = null,
    val color: String = "#6c757d",
    val icon: String = "fas fa-warehouse",
    val is_active: Int = 1,
    val sort_order: Int = 0
)

data class LocationListResponse(
    val result: Boolean,
    val response: LocationListData? = null
)

data class LocationListData(
    val locations: List<Location>
)

data class LocationScanResponse(
    val result: Boolean,
    val response: LocationScanData? = null
)

data class LocationScanData(
    val success: Boolean,
    val entity_type: String? = null,
    val entity: Map<String, Any>? = null,
    val location: Map<String, Any>? = null,
    val message: String? = null
)

data class BulkLocationResponse(
    val result: Boolean,
    val response: BulkLocationData? = null
)

data class BulkLocationData(
    val total: Int = 0,
    val success: Int = 0,
    val errors: Int = 0,
    val details: List<Map<String, Any>>? = null
)
