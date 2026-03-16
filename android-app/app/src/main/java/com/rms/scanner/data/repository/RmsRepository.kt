package com.rms.scanner.data.repository

import android.util.Log
import com.google.gson.Gson
import com.rms.scanner.data.api.RmsApiClient
import com.rms.scanner.data.api.models.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class RmsRepository {
    private val apiService = RmsApiClient.getApiService()
    private val gson = Gson()
    private val tag = "RmsRepository"

    suspend fun universalScan(
        rfidTag: String,
        scanAction: String,
        projectId: Int? = null
    ): Result<UniversalScanResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.universalScan(rfidTag, scanAction, projectId)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "universalScan failed", e)
            Result.failure(e)
        }
    }

    suspend fun universalLookup(rfidTag: String): Result<UniversalLookupResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.universalLookup(rfidTag)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "universalLookup failed", e)
            Result.failure(e)
        }
    }

    suspend fun boxScan(rfidTags: List<String>): Result<BoxScanResponse> = withContext(Dispatchers.IO) {
        try {
            val tagsJson = gson.toJson(rfidTags)
            val response = apiService.boxScan(tagsJson)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "boxScan failed", e)
            Result.failure(e)
        }
    }

    suspend fun boxScanAction(
        rfidTags: List<String>,
        scanAction: String,
        projectId: Int? = null
    ): Result<BoxScanActionResponse> = withContext(Dispatchers.IO) {
        try {
            val tagsJson = gson.toJson(rfidTags)
            val response = apiService.boxScanAction(tagsJson, scanAction, projectId)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "boxScanAction failed", e)
            Result.failure(e)
        }
    }

    suspend fun startInventory(): Result<InventorySessionResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.startInventory()
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "startInventory failed", e)
            Result.failure(e)
        }
    }

    suspend fun inventoryScan(sessionId: String, rfidTag: String): Result<InventoryScanResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.inventoryScan(sessionId, rfidTag)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "inventoryScan failed", e)
            Result.failure(e)
        }
    }

    suspend fun completeInventory(sessionId: String): Result<CompleteInventoryResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.completeInventory(sessionId)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "completeInventory failed", e)
            Result.failure(e)
        }
    }

    suspend fun assignTag(assetId: Int, rfidTag: String): Result<AssignTagResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.assignTag(assetId, rfidTag)
            if (response.result == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "assignTag failed", e)
            Result.failure(e)
        }
    }

    suspend fun listAssets(): Result<List<AssetItem>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.listAssets()
            if (response.result == true && response.response?.assets != null) {
                Result.success(response.response.assets)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "listAssets failed", e)
            Result.failure(e)
        }
    }

    suspend fun listStockItems(): Result<List<StockItemType>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.listStockItems()
            if (response.success == true && response.response?.items != null) {
                Result.success(response.response.items)
            } else {
                Result.failure(Exception(response.message ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "listStockItems failed", e)
            Result.failure(e)
        }
    }

    suspend fun listStockInstances(itemId: Int): Result<List<StockInstance>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.listStockInstances(itemId)
            if (response.success == true && response.response?.instances != null) {
                Result.success(response.response.instances)
            } else {
                Result.failure(Exception(response.message ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "listStockInstances failed", e)
            Result.failure(e)
        }
    }

    suspend fun assignStockRfid(instanceId: Int, rfidTag: String): Result<AssignStockRfidResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.assignStockRfid(instanceId, rfidTag)
            if (response.success == true && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.message ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "assignStockRfid failed", e)
            Result.failure(e)
        }
    }

    suspend fun listProjects(): Result<List<ProjectItem>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.listProjects()
            if (response.result == true && response.response?.projects != null) {
                Result.success(response.response.projects)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "listProjects failed", e)
            Result.failure(e)
        }
    }

    suspend fun listLocations(): Result<List<Location>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.listLocations()
            if (response.result && response.response != null) {
                Result.success(response.response.locations)
            } else {
                Result.failure(Exception("Lagerorte konnten nicht geladen werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "listLocations failed", e)
            Result.failure(e)
        }
    }

    suspend fun scanAssignLocation(
        rfidTag: String,
        locationId: Int?,
        locationCustom: String?,
        notes: String?
    ): Result<LocationScanData> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.scanAssignLocation(
                rfidTag = rfidTag,
                locationId = locationId,
                locationCustom = locationCustom,
                notes = notes
            )
            if (response.result && response.response != null && response.response.success) {
                Result.success(response.response)
            } else {
                Result.failure(Exception(response.response?.message ?: "Umlagern fehlgeschlagen"))
            }
        } catch (e: Exception) {
            Log.e(tag, "scanAssignLocation failed", e)
            Result.failure(e)
        }
    }

    suspend fun bulkAssignLocation(
        rfidTags: List<String>,
        locationId: Int?,
        locationCustom: String?,
        notes: String?
    ): Result<BulkLocationData> = withContext(Dispatchers.IO) {
        try {
            val tagsJson = gson.toJson(rfidTags)
            val response = apiService.bulkAssignLocation(
                rfidTags = tagsJson,
                locationId = locationId,
                locationCustom = locationCustom,
                notes = notes
            )
            if (response.result && response.response != null) {
                Result.success(response.response)
            } else {
                Result.failure(Exception("Sammel-Umlagern fehlgeschlagen"))
            }
        } catch (e: Exception) {
            Log.e(tag, "bulkAssignLocation failed", e)
            Result.failure(e)
        }
    }

    suspend fun getPackingList(projectId: Int): Result<PackingListResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getPackingList(projectId = projectId)
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception("Packliste konnte nicht geladen werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "getPackingList failed", e)
            Result.failure(e)
        }
    }

    suspend fun checkPackingItem(
        entityType: String,
        entityId: Int,
        projectId: Int
    ): Result<PackingCheckResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.checkPackingItem(
                entityType = entityType,
                entityId = entityId,
                projectId = projectId
            )
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception("Item konnte nicht abgehakt werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "checkPackingItem failed", e)
            Result.failure(e)
        }
    }

    suspend fun uncheckPackingItem(
        entityType: String,
        entityId: Int,
        projectId: Int
    ): Result<PackingCheckResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.uncheckPackingItem(
                entityType = entityType,
                entityId = entityId,
                projectId = projectId
            )
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception("Item konnte nicht abgewählt werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "uncheckPackingItem failed", e)
            Result.failure(e)
        }
    }

    suspend fun scanCheckPackingItem(
        scanValue: String,
        projectId: Int
    ): Result<PackingCheckResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.scanCheckPackingItem(
                scanValue = scanValue,
                projectId = projectId
            )
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception("Scan konnte nicht verarbeitet werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "scanCheckPackingItem failed", e)
            Result.failure(e)
        }
    }

    suspend fun listExternalItems(status: String? = null): Result<List<ExternalItem>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.listExternalItems(status = status)
            if (response.success && response.items != null) {
                Result.success(response.items)
            } else {
                Result.failure(Exception("Fremdmaterial konnte nicht geladen werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "listExternalItems failed", e)
            Result.failure(e)
        }
    }

    suspend fun createExternalItem(
        description: String,
        ownerName: String,
        ownerContact: String? = null,
        quantity: Int = 1,
        projectId: Int? = null,
        returnDate: String? = null,
        notes: String? = null
    ): Result<ExternalItemCreateResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.createExternalItem(
                description = description,
                ownerName = ownerName,
                ownerContact = ownerContact,
                quantity = quantity,
                projectId = projectId,
                returnDate = returnDate,
                notes = notes
            )
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Fremdmaterial konnte nicht erfasst werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "createExternalItem failed", e)
            Result.failure(e)
        }
    }

    suspend fun markExternalItemReturned(itemId: Int): Result<ExternalItemCreateResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.markExternalItemReturned(id = itemId)
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception("Fremdmaterial konnte nicht als zurückgegeben markiert werden"))
            }
        } catch (e: Exception) {
            Log.e(tag, "markExternalItemReturned failed", e)
            Result.failure(e)
        }
    }

    suspend fun getCaseContents(caseAssetId: Int): Result<Pair<CaseInfo?, List<CaseContentItem>>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getCaseContents(caseAssetId = caseAssetId)
            if (response.result == true && response.response != null) {
                val caseInfo = gson.fromJson(gson.toJson(response.response), CaseInfo::class.java)
                val contents = when (response.response) {
                    is List<*> -> response.response.mapNotNull { item ->
                        try {
                            gson.fromJson(gson.toJson(item), CaseContentItem::class.java)
                        } catch (e: Exception) {
                            null
                        }
                    }
                    else -> emptyList()
                }
                Result.success(Pair(caseInfo, contents))
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "getCaseContents failed", e)
            Result.failure(e)
        }
    }

    suspend fun verifyCaseScan(caseAssetId: Int, scannedItemsJson: String): Result<CaseVerificationResult> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.verifyCaseScan(caseAssetId = caseAssetId, scannedItemsJson = scannedItemsJson)
            if (response.result == true && response.response != null) {
                val verificationResult = gson.fromJson(gson.toJson(response.response), CaseVerificationResult::class.java)
                Result.success(verificationResult)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "verifyCaseScan failed", e)
            Result.failure(e)
        }
    }

    suspend fun logCaseCheck(
        caseAssetId: Int,
        projectId: Int,
        checkType: String,
        resultJson: String
    ): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.logCaseCheck(
                caseAssetId = caseAssetId,
                projectId = projectId,
                checkType = checkType,
                resultJson = resultJson
            )
            if (response.result == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "logCaseCheck failed", e)
            Result.failure(e)
        }
    }

    suspend fun acknowledgeCaseDiscrepancy(checkId: Int): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.acknowledgeCaseDiscrepancy(checkId = checkId)
            if (response.result == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "acknowledgeCaseDiscrepancy failed", e)
            Result.failure(e)
        }
    }

    suspend fun getCompanyCode(): Result<String> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getCompanyCode()
            if (response.result == true && response.response != null) {
                val code = when (response.response) {
                    is String -> response.response
                    is Map<*, *> -> (response.response as? Map<String, Any>)?.get("code") as? String ?: "XX"
                    else -> {
                        val jsonStr = gson.toJson(response.response)
                        val map = gson.fromJson(jsonStr, Map::class.java)
                        (map as? Map<String, Any>)?.get("code") as? String ?: "XX"
                    }
                }
                Result.success(code)
            } else {
                Result.failure(Exception(response.error ?: "Unknown error"))
            }
        } catch (e: Exception) {
            Log.e(tag, "getCompanyCode failed", e)
            Result.failure(e)
        }
    }

    /**
     * Get binary EPC hex for writing to an RFID tag.
     * Returns both 96-bit and 128-bit binary formats plus human-readable.
     */
    suspend fun getWriteEpc(entityType: String, entityId: Int): Result<Map<String, Any>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getWriteEpc(entityType = entityType, entityId = entityId)
            if (response.result == true && response.response != null) {
                val jsonStr = gson.toJson(response.response)
                @Suppress("UNCHECKED_CAST")
                val data = gson.fromJson(jsonStr, Map::class.java) as Map<String, Any>
                Result.success(data)
            } else {
                Result.failure(Exception(response.error ?: "EPC generation failed"))
            }
        } catch (e: Exception) {
            Log.e(tag, "getWriteEpc failed", e)
            Result.failure(e)
        }
    }

    /**
     * Decode a binary EPC hex string read from an RFID tag.
     */
    suspend fun decodeEpc(epcHex: String): Result<Map<String, Any>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.decodeEpc(epcHex = epcHex)
            if (response.result == true && response.response != null) {
                val jsonStr = gson.toJson(response.response)
                @Suppress("UNCHECKED_CAST")
                val data = gson.fromJson(jsonStr, Map::class.java) as Map<String, Any>
                Result.success(data)
            } else {
                Result.failure(Exception(response.error ?: "EPC decode failed"))
            }
        } catch (e: Exception) {
            Log.e(tag, "decodeEpc failed", e)
            Result.failure(e)
        }
    }
}
