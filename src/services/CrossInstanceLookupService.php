<?php
/**
 * CrossInstanceLookupService — Firmenübergreifende Tag-Suche
 *
 * When a scanned RFID tag or barcode isn't found locally, this service
 * searches partner instances (same server) and federated servers (remote).
 */
class CrossInstanceLookupService
{
    private $db;
    private int $instanceId;

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
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

        // 1. Try local partner instances (same database server)
        $result = $this->lookupLocalPartners($tagValue);
        if ($result) return $result;

        // 2. Try federated remote servers
        $result = $this->lookupFederation($tagValue);
        if ($result) return $result;

        return null;
    }

    /**
     * Search partner instances on the same server
     */
    private function lookupLocalPartners(string $tagValue): ?array
    {
        // Get all active partner instance IDs
        $partnerIds = $this->getActivePartnerInstanceIds();
        if (empty($partnerIds)) return null;

        foreach ($partnerIds as $partnerId) {
            // Check assets in partner instance
            $this->db->where('a.instances_id', $partnerId);
            $this->db->where('a.assets_deleted', 0);
            $this->db->where('(a.asset_definableFields_1 = ? OR CONCAT("RMS-A-", LPAD(a.assets_id, 6, "0")) = ?)', [$tagValue, $tagValue]);
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

            // Check stock_instances in partner instance
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

            // Check external_items in partner instance
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
            'partner_servers_remoteApiKey'
        ]);

        if (!$servers) return null;

        foreach ($servers as $server) {
            try {
                $url = rtrim($server['partner_servers_url'], '/') . '/api/federation/tag_lookup.php';
                $apiKey = $server['partner_servers_remoteApiKey'];

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(['tag_value' => $tagValue]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_HTTPHEADER => [
                        'X-Federation-Key: ' . $apiKey,
                        'Content-Type: application/x-www-form-urlencoded'
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    if ($data && !empty($data['found'])) {
                        return [
                            'found' => true,
                            'source' => 'federation',
                            'owner_instance_id' => $server['partner_servers_id'],
                            'owner_instance_name' => $server['partner_servers_name'],
                            'owner_server_url' => $server['partner_servers_url'],
                            'entity_type' => $data['entity_type'] ?? 'unknown',
                            'entity' => $data['entity'] ?? ['display_name' => 'Unbekannt'],
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
