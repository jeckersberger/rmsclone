package com.rms.scanner.data.api.models

data class CaseInfo(
    val asset_id: Int,
    val asset_tag: String,
    val type_name: String,
    val content_count: Int
)

data class CaseContentItem(
    val id: Int,
    val entity_type: String,
    val entity_id: Int,
    val entity_name: String,
    val quantity: Int,
    val is_required: Boolean,
    val notes: String?
)

data class CaseVerificationResult(
    val all_complete: Boolean,
    val found: List<CaseContentItem>,
    val missing: List<CaseContentItem>,
    val extra: List<Map<String, Any>>,
    val swapped: List<Map<String, Any>>,
    val found_count: Int,
    val expected_count: Int
)
