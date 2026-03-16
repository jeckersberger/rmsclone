<?php
/**
 * CrossInstanceLookupService — Firmenübergreifende Tag-Suche
 *
 * When a scanned RFID tag or barcode isn't found locally, this service
 * searches partner instances (same server) and federated servers (remote).
 *
 * Supports all tag formats:
 *   - New: RMS-a3f7b2c1-A-000042 (8 hex company code from MD5)
 *   - Legacy: RMS-A-000042, RMS-XXXX-A-000042
 *   - QR: RMS://a3f7b2c1/A/000042
 *   - Binary EPC: 52A3F7B2C14100000042xxxx (96-bit / 128-bit hex from Chafon reader)
 *   - Raw RFID: arbitrary EPC stored in database fields
 */
require_once __DIR__ . '/TagFormatService.php';

class CrossInstanceLookupService
{
    private $db;
    private int $instanceId;
    private TagFormatService $tagService;

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
        $this->tagService = new TagFormatService($db, $instanceId);
    }

    /**
     * Look up a tag across all connected partners.
     * Returns null if not found anywhere, or an array with owner info.
     *
     * @return array|null {
     *   'found' => true,
     *   'source' => 'local_partner' | 'federation',
     *   'owner_instance_id' => int,
     *   'owner_instance_name' => string,
     *   'owner_server_url' => string|null (only for federation),
     *   'entity_type' => 'asset' | 'stock_instance' | 'external',
     *   'entity' => [...entity details...],
     *   'rfid_tag' => string,
     * }
     */
    public function lookupTag(string $tagValue): ?array
    {
        $tagValue = trim($tagValue);
        if (empty($tagValue)) return null;

        // Pre-process: resolve binary EPC to human-readable if possible
        $resolvedTag = $this->resolveBinaryEpc($tagValue);

        // Try to parse as structured RMS tag
        $parsed = $this->tagService->parse($resolvedTag);

        // If we have a parsed tag with a company code, try to look up the owning instance directly
        if ($parsed && $parsed['company_code'] && !$parsed['is_local']) {
            $ownerInstance = $this->tagService->findInstanceByCompanyCode($parsed['company_code']);
            if ($ownerInstance) {
                // It's a local partner — look up the entity directly
                $partnerId = (int)$ownerInstance['instances_id'];
                $partnerIds = $this->getActivePartnerInstanceIds();
                if (in_array($partnerId, $partnerIds)) {
                    $result = $this->lookupEntityDirect($partnerId, $parsed['entity_type'], $parsed['entity_id']);
                    if ($result) {
                        $result['owner_instance_name'] = $ownerInstance['instances_name'];
                        return $result;
                    }
                }
            }
        }

        // 1. Try local partner instances (same database server)
        $result = $this->lookupLocalPartners($resolvedTag, $parsed);
        if ($result) return $result;

        // 2. Try federated remote servers
        $result = $this->lookupFederation($tagValue); // Send original value to remote
        if ($result) return $result;

        return null;
    }

    /**
     * Resolve binary EPC hex to human-readable format
     */
    private function resolveBinaryEpc(string $tagValue): string
    {
        $clean = strtoupper(trim($tagValue));

        // Check if it's a 96-bit (24 hex) or 128-bit (32 hex) binary EPC
        if (preg_match('/^[0-9A-F]{24}$/', $clean) || preg_match('/^[0-9A-F]{32}$/', $clean)) {
            if (substr($clean, 0, 2) === '52') { // RMS header byte
                $decoded = $this->tagService->decodeBinaryEpc($clean);
                if ($decoded && $decoded['crc_valid']) {
                    return $decoded['human_readable'];
                }
            }
        }

        return $tagValue;
    }

    /**
     * Direct entity lookup by type and ID on a specific instance
     */
    private function lookupEntityDirect(int $instanceId, string $entityType, int $entityId): ?array
    {
        if ($entityType === 'asset') {
            $this->db->where('a.assets_id', $entityId);
            $this->db->where('a.instances_id', $instanceId);
            $this->db->where('a.assets_deleted', 0);
            $this->db->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
            $asset = $this->db->getOne('assets a', [
                'a.assets_id', 'a.assets_tag', 'a.assets_name',
                'a.asset_definableFields_1 AS rfid_tag',
                'at.assetTypes_name AS type_name',
                'a.instances_id'
            ]);

            if ($asset) {
                return [
                    'found' => true,
                    'source' => 'local_partner',
                    'owner_instance_id' => $instanceId,
                    'owner_instance_name' => '', // filled by caller
                    'owner_server_url' => null,
                    'entity_type' => 'asset',
                    'entity' => [
                        'display_name' => trim(($asset['type_name'] ?: '') . ' ' . ($asset['assets_name'] ?: '#' . $asset['assets_tag'])),
                        'type_name' => $asset['type_name'],
                        'asset_tag' => $asset['assets_tag'],
                        'rfid_tag' => $asset['rfid_tag'],
                    ],
                    'rfid_tag' => $asset['rfid_tag'] ?? '',
                ];
            }
        } elseif ($entityType === 'stock_instance') {
            $this->db->where('si.instance_number', $entityId);
            $this->db->join('stock_items sit', 'si.stock_item_id=sit.id', 'LEFT');
            $this->db->where('sit.instances_id', $instanceId);
            $stockInst = $this->db->getOne('stock_instances si', [
                'si.id', 'si.instance_number', 'si.rfid_tag',
                'sit.name AS item_name', 'sit.category', 'sit.instances_id'
            ]);

            if ($stockInst) {
                return [
                    'found' => true,
                    'source' => 'local_partner',
                    'owner_instance_id' => $instanceId,
                    'owner_instance_name' => '',
                    'owner_server_url' => null,
                    'entity_type' => 'stock_instance',
                    'entity' => [
                        'display_name' => ($stockInst['item_name'] ?: 'Artikel') . ' #' . str_pad($stockInst['instance_number'], 4, '0', STR_PAD_LEFT),
                        'item_name' => $stockInst['item_name'],
                        'category' => $stockInst['category'],
                        'instance_number' => $stockInst['instance_number'],
                        'rfid_tag' => $stockInst['rfid_tag'],
                    ],
                    'rfid_tag' => $stockInst['rfid_tag'] ?? '',
                ];
            }
        } elseif ($entityType === 'external') {
            $this->db->where('e.id', $entityId);
            $this->db->where('e.instances_id', $instanceId);
            $ext = $this->db->getOne('external_items e', [
                'e.id', 'e.description', 'e.owner_name', 'e.barcode', 'e.rfid_tag', 'e.instances_id'
            ]);

            if ($ext) {
                return [
                    'found' => true,
                    'source' => 'local_partner',
                    'owner_instance_id' => $instanceId,
                    'owner_instance_name' => '',
                    'owner_server_url' => null,
                    'entity_type' => 'external',
                    'entity' => [
                        'display_name' => $ext['description'] . ' (' . $ext['owner_name'] . ')',
                        'description' => $ext['description'],
                        'owner_name' => $ext['owner_name'],
                        'barcode' => $ext['barcode'],
                        'rfid_tag' => $ext['rfid_tag'],
                    ],
                    'rfid_tag' => $ext['rfid_tag'] ?? $ext['barcode'] ?? '',
                ];
            }
        }

        return null;
    }

    /**
     * Search partner instances on the same server
     */
    private function lookupLocalPartners(string $tagValue, ?array $parsed): ?array
    {
        // Get all active partner instance IDs
        $partnerIds = $this->getActivePartnerInstanceIds();
        if (empty($partnerIds)) return null;

        foreach ($partnerIds as $partnerId) {
            // If we have a parsed tag, try direct lookup by entity type + ID first
            if ($parsed && $parsed['entity_type'] !== 'unknown') {
                $result = $this->lookupEntityDirect($partnerId, $parsed['entity_type'], $parsed['entity_id']);
                if ($result) {
                    $result['owner_instance_name'] = $this->getInstanceName($partnerId);
                    return $result;
                }
            }

            // Fallback: search by raw tag value in RFID/barcode fields

            // Check assets by RFID tag field
            $this->db->where('a.instances_id', $partnerId);
            $this->db->where('a.assets_deleted', 0);
            $this->db->where('a.asset_definableFields_1', $tagValue);
            $this->db->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
            $asset = $this->db->getOne('assets a', [
                'a.assets_id', 'a.assets_tag', 'a.assets_name',
                'a.asset_definableFields_1 AS rfid_tag',
                'at.assetTypes_name AS type_name',
                'a.instances_id'
            ]);

            if ($asset) {
                $instanceName = $this->getInstanceName($partnerId);
                return [
                    'found' => true,
                    'source' => 'local_partner',
                    'owner_instance_id' => $partnerId,
                    'owner_instance_name' => $instanceName,
                    'owner_server_url' => null,
                    'entity_type' => 'asset',
                    'entity' => [
                        'display_name' => trim(($asset['type_name'] ?: '') . ' ' . ($asset['assets_name'] ?: '#' . $asset['assets_tag'])),
                        'type_name' => $asset['type_name'],
                        'asset_tag' => $asset['assets_tag'],
                        'rfid_tag' => $asset['rfid_tag'],
                    ],
                    'rfid_tag' => $tagValue,
                ];
            }

            // Check stock_instances by RFID tag
            $this->db->where('si.rfid_tag', $tagValue);
            $this->db->join('stock_items sit', 'si.stock_item_id=sit.id', 'LEFT');
            $this->db->where('sit.instances_id', $partnerId);
            $stockInst = $this->db->getOne('stock_instances si', [
                'si.id', 'si.instance_number', 'si.rfid_tag',
                'sit.name AS item_name', 'sit.category', 'sit.instances_id'
            ]);

            if ($stockInst) {
                $instanceName = $this->getInstanceName($partnerId);
                return [
                    'found' => true,
                    'source' => 'local_partner',
                    'owner_instance_id' => $partnerId,
                    'owner_instance_name' => $instanceName,
                    'owner_server_url' => null,
                    'entity_type' => 'stock_instance',
                    'entity' => [
                        'display_name' => ($stockInst['item_name'] ?: 'Artikel') . ' #' . str_pad($stockInst['instance_number'], 4, '0', STR_PAD_LEFT),
                        'item_name' => $stockInst['item_name'],
                        'category' => $stockInst['category'],
                        'instance_number' => $stockInst['instance_number'],
                        'rfid_tag' => $stockInst['rfid_tag'],
                    ],
                    'rfid_tag' => $tagValue,
                ];
            }

            // Check external_items by barcode or RFID
            $this->db->where('e.instances_id', $partnerId);
            $this->db->where('(e.barcode = ? OR e.rfid_tag = ?)', [$tagValue, $tagValue]);
            $ext = $this->db->getOne('external_items e', [
                'e.id', 'e.description', 'e.owner_name', 'e.barcode', 'e.rfid_tag', 'e.instances_id'
            ]);

            if ($ext) {
                $instanceName = $this->getInstanceName($partnerId);
                return [
                    'found' => true,
                    'source' => 'local_partner',
                    'owner_instance_id' => $partnerId,
                    'owner_instance_name' => $instanceName,
                    'owner_server_url' => null,
                    'entity_type' => 'external',
                    'entity' => [
                        'display_name' => $ext['description'] . ' (' . $ext['owner_name'] . ')',
                        'description' => $ext['description'],
                        'owner_name' => $ext['owner_name'],
                        'barcode' => $ext['barcode'],
                        'rfid_tag' => $ext['rfid_tag'],
                    ],
                    'rfid_tag' => $tagValue,
                ];
            }
        }

        return null;
    }

    /**
     * Search federated remote servers
     */
    private function lookupFederation(string $tagValue): ?array
    {
        // Get all active federation servers
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('partner_servers_status', 'active');
        $this->db->where('partner_servers_deleted', 0);
        $servers = $this->db->get('partner_servers', null, [
            'partner_servers_id', 'partner_servers_url', 'partner_servers_name',
            'partner_servers_apiKey'
        ]);

        if (!$servers) return null;

        // Use SSRF protection for outgoing requests
        require_once __DIR__ . '/UrlSecurityService.php';

        foreach ($servers as $server) {
            try {
                $url = rtrim($server['partner_servers_url'], '/') . '/api/federation/tag_lookup.php';
                $apiKey = $server['partner_servers_apiKey'];

                // SSRF check
                $urlCheck = UrlSecurityService::validateUrl($url);
                if (!$urlCheck['safe']) continue;

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['tag_value' => $tagValue]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_HTTPHEADER => [
                        'X-Federation-Key: ' . $apiKey,
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'X-Federation-Version: 1',
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_USERAGENT => 'rmsclone-federation/1',
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);

                    // Handle both finish()-wrapped and direct responses
                    $responseData = $data;
                    if (isset($data['result']) && isset($data['response'])) {
                        $responseData = $data['response'];
                    }

                    if ($responseData && !empty($responseData['found'])) {
                        return [
                            'found' => true,
                            'source' => 'federation',
                            'owner_instance_id' => $server['partner_servers_id'],
                            'owner_instance_name' => $server['partner_servers_name'],
                            'owner_server_url' => $server['partner_servers_url'],
                            'entity_type' => $responseData['entity_type'] ?? 'unknown',
                            'entity' => $responseData['entity'] ?? ['display_name' => 'Unbekannt'],
                            'company_code' => $responseData['company_code'] ?? null,
                            'rfid_tag' => $tagValue,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Silently continue to next server — don't block scanning
                continue;
            }
        }

        return null;
    }

    /**
     * Get IDs of all active partner instances
     */
    private function getActivePartnerInstanceIds(): array
    {
        $ids = [];

        // Where we are instance_a
        $this->db->where('instance_a_id', $this->instanceId);
        $this->db->where('status', 'active');
        $this->db->where('deleted', 0);
        $linksA = $this->db->get('partner_links', null, ['instance_b_id']);
        if ($linksA) {
            foreach ($linksA as $link) $ids[] = (int)$link['instance_b_id'];
        }

        // Where we are instance_b
        $this->db->where('instance_b_id', $this->instanceId);
        $this->db->where('status', 'active');
        $this->db->where('deleted', 0);
        $linksB = $this->db->get('partner_links', null, ['instance_a_id']);
        if ($linksB) {
            foreach ($linksB as $link) $ids[] = (int)$link['instance_a_id'];
        }

        return array_unique($ids);
    }

    /**
     * Get the display name of an instance
     */
    private function getInstanceName(int $instanceId): string
    {
        $this->db->where('instances_id', $instanceId);
        $inst = $this->db->getOne('instances', ['instances_name']);
        return $inst ? $inst['instances_name'] : 'Unbekannt';
    }
}
