<?php
/**
 * Federation Service - Server-zu-Server Kommunikation
 *
 * Ermoeglicht die Verbindung von zwei komplett getrennten
 * rmsclone-Installationen ueber HTTPS REST API.
 *
 * Protokoll:
 *   1. Server B sendet Handshake an Server A (mit Partner-Code)
 *   2. Server A verifiziert Code, generiert API-Keys
 *   3. Beide Server speichern die Verbindung in partner_servers
 *   4. Alle weiteren Anfragen sind mit API-Key authentifiziert
 */
class FederationService
{
    private $db;
    private const API_TIMEOUT = 15; // Sekunden
    private const API_VERSION = '1';

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ── Ausgehende Verbindung (wir verbinden uns zu einem Partner) ──

    /**
     * Handshake mit einem Remote-Server initiieren
     *
     * @param int $instanceId Unsere lokale Instanz
     * @param string $remoteUrl Basis-URL des Partner-Servers
     * @param string $partnerCode Partner-Code der Gegenstelle
     * @return array ['success' => bool, 'error' => string|null, 'partner_name' => string|null]
     */
    public function initiateHandshake(int $instanceId, string $remoteUrl, string $partnerCode): array
    {
        $remoteUrl = rtrim($remoteUrl, '/');

        // Pruefen ob bereits verbunden
        $this->db->where('instances_id', $instanceId);
        $this->db->where('partner_servers_url', $remoteUrl);
        $this->db->where('partner_servers_deleted', 0);
        $existing = $this->db->getOne('partner_servers');
        if ($existing) {
            return ['success' => false, 'error' => 'already_connected'];
        }

        // Unseren API-Key generieren (den der Partner nutzen wird um bei uns anzufragen)
        $ourApiKey = $this->generateApiKey();

        // Unseren Firmennamen holen
        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances', ['instances_name']);
        $ourName = $instance ? $instance['instances_name'] : 'Unbekannt';

        // Eigene Server-URL ermitteln
        $ourUrl = $this->getOwnServerUrl();

        // Handshake an Remote-Server senden
        $response = $this->sendRequest($remoteUrl . '/api/federation/handshake.php', [
            'partner_code' => $partnerCode,
            'requesting_server_url' => $ourUrl,
            'requesting_server_name' => $ourName,
            'requesting_server_instance_id' => $instanceId,
            'requesting_api_key' => $ourApiKey,
            'api_version' => self::API_VERSION,
        ]);

        if (!$response || !$response['result']) {
            $error = $response['error']['message'] ?? 'remote_server_error';
            $this->logFederation(null, 'outgoing', 'handshake', 'error', $error);
            return ['success' => false, 'error' => $error];
        }

        $data = $response['response'];

        // Verbindung lokal speichern
        $this->db->insert('partner_servers', [
            'instances_id' => $instanceId,
            'partner_servers_url' => $remoteUrl,
            'partner_servers_name' => $data['server_name'] ?? 'Partner',
            'partner_servers_apiKey' => $ourApiKey,
            'partner_servers_remoteApiKey' => $data['api_key'],
            'partner_servers_remoteInstanceId' => $data['instance_id'] ?? null,
            'partner_servers_status' => 'active',
            'partner_servers_lastSeen' => date('Y-m-d H:i:s'),
        ]);

        $serverId = $this->db->getInsertId();
        $this->logFederation($serverId, 'outgoing', 'handshake', 'success', 'Connected to ' . $remoteUrl);

        return [
            'success' => true,
            'partner_name' => $data['server_name'] ?? 'Partner',
            'server_id' => $serverId,
        ];
    }

    /**
     * Eingehenden Handshake verarbeiten (ein anderer Server verbindet sich zu uns)
     */
    public function handleIncomingHandshake(
        string $partnerCode,
        string $requestingServerUrl,
        string $requestingServerName,
        int $requestingInstanceId,
        string $requestingApiKey
    ): array {
        // Partner-Code pruefen
        $this->db->where('instances_partnerCode', $partnerCode);
        $this->db->where('instances_deleted', 0);
        $instance = $this->db->getOne('instances', ['instances_id', 'instances_name']);

        if (!$instance) {
            return ['success' => false, 'error' => 'invalid_code'];
        }

        $instanceId = $instance['instances_id'];
        $requestingServerUrl = rtrim($requestingServerUrl, '/');

        // Pruefen ob bereits verbunden
        $this->db->where('instances_id', $instanceId);
        $this->db->where('partner_servers_url', $requestingServerUrl);
        $this->db->where('partner_servers_deleted', 0);
        $existing = $this->db->getOne('partner_servers');
        if ($existing) {
            return ['success' => false, 'error' => 'already_connected'];
        }

        // Unseren API-Key generieren (den der anfragende Server nutzen wird)
        $ourApiKey = $this->generateApiKey();

        // Verbindung speichern
        $this->db->insert('partner_servers', [
            'instances_id' => $instanceId,
            'partner_servers_url' => $requestingServerUrl,
            'partner_servers_name' => $requestingServerName,
            'partner_servers_apiKey' => $requestingApiKey,
            'partner_servers_remoteApiKey' => $ourApiKey,
            'partner_servers_remoteInstanceId' => $requestingInstanceId,
            'partner_servers_status' => 'active',
            'partner_servers_lastSeen' => date('Y-m-d H:i:s'),
        ]);

        $serverId = $this->db->getInsertId();
        $this->logFederation($serverId, 'incoming', 'handshake', 'success', 'Accepted from ' . $requestingServerUrl);

        return [
            'success' => true,
            'api_key' => $ourApiKey,
            'server_name' => $instance['instances_name'],
            'instance_id' => $instanceId,
        ];
    }

    // ── API-Key Authentifizierung ──

    /**
     * Eingehende Anfrage per API-Key authentifizieren
     *
     * @return array|null Partner-Server Daten oder null bei Fehler
     */
    public function authenticateRequest(string $apiKey): ?array
    {
        $this->db->where('partner_servers_remoteApiKey', $apiKey);
        $this->db->where('partner_servers_status', 'active');
        $this->db->where('partner_servers_deleted', 0);
        $server = $this->db->getOne('partner_servers');

        if ($server) {
            // lastSeen aktualisieren
            $this->db->where('partner_servers_id', $server['partner_servers_id']);
            $this->db->update('partner_servers', ['partner_servers_lastSeen' => date('Y-m-d H:i:s')]);
        }

        return $server;
    }

    // ── Ausgehende Equipment-Anfragen ──

    /**
     * Equipment von einem Partner-Server abrufen
     */
    public function fetchPartnerEquipment(int $serverId, ?string $startDate = null, ?string $endDate = null, ?string $search = null): array
    {
        $server = $this->getServer($serverId);
        if (!$server) return [];

        $params = [];
        if ($startDate) $params['start_date'] = $startDate;
        if ($endDate) $params['end_date'] = $endDate;
        if ($search) $params['search'] = $search;

        $response = $this->sendAuthenticatedRequest(
            $server['partner_servers_url'] . '/api/federation/equipment.php',
            $server['partner_servers_apiKey'],
            $params
        );

        if ($response && $response['result']) {
            $this->logFederation($serverId, 'outgoing', 'equipment', 'success');
            $equipment = $response['response']['equipment'] ?? [];
            // Partner-Name hinzufuegen
            foreach ($equipment as &$item) {
                $item['partner_server_id'] = $serverId;
                $item['partner_name'] = $server['partner_servers_name'];
            }
            return $equipment;
        }

        $this->logFederation($serverId, 'outgoing', 'equipment', 'error', $response['error']['message'] ?? 'unknown');
        return [];
    }

    /**
     * Equipment-Anfrage an Partner-Server senden
     */
    public function sendEquipmentRequest(int $serverId, array $requestData): array
    {
        $server = $this->getServer($serverId);
        if (!$server) return ['success' => false, 'error' => 'server_not_found'];

        $response = $this->sendAuthenticatedRequest(
            $server['partner_servers_url'] . '/api/federation/request.php',
            $server['partner_servers_apiKey'],
            $requestData
        );

        if ($response && $response['result']) {
            $this->logFederation($serverId, 'outgoing', 'request', 'success');
            return ['success' => true, 'remote_request_id' => $response['response']['request_id'] ?? null];
        }

        $this->logFederation($serverId, 'outgoing', 'request', 'error', $response['error']['message'] ?? 'unknown');
        return ['success' => false, 'error' => $response['error']['message'] ?? 'remote_error'];
    }

    // ── Eingehende Equipment-Anfragen (von Partner-Server) ──

    /**
     * Lokales Equipment fuer einen authentifizierten Partner auflisten
     */
    public function getLocalEquipment(int $instanceId, ?string $startDate = null, ?string $endDate = null, ?string $search = null): array
    {
        $sql = "SELECT at.assetTypes_id, at.assetTypes_name,
                       ac.assetCategories_name,
                       at.assetTypes_dayRate, at.assetTypes_weekRate,
                       COUNT(a.assets_id) as total_count
                FROM assetTypes at
                JOIN assets a ON at.assetTypes_id = a.assetTypes_id AND a.assets_deleted = 0
                LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE at.instances_id = ?
                AND at.assetTypes_deleted = 0";
        $params = [$instanceId];

        if ($search) {
            $sql .= " AND (at.assetTypes_name LIKE ? OR ac.assetCategories_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " GROUP BY at.assetTypes_id ORDER BY ac.assetCategories_rank ASC, at.assetTypes_name ASC";

        $equipment = $this->db->rawQuery($sql, $params) ?: [];

        // Verfuegbarkeit pruefen
        if ($startDate && $endDate && class_exists('AvailabilityService')) {
            $availSvc = new AvailabilityService($this->db);
            foreach ($equipment as &$eq) {
                $avail = $availSvc->getAssetTypeAvailability($eq['assetTypes_id'], $instanceId, $startDate, $endDate);
                $eq['available_count'] = $avail['available'];
            }
        }

        return $equipment;
    }

    // ── Verwaltung ──

    /**
     * Alle verbundenen Partner-Server einer Instanz abrufen
     */
    public function getConnectedServers(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('partner_servers_deleted', 0);
        $this->db->orderBy('partner_servers_created', 'DESC');
        return $this->db->get('partner_servers', null, [
            'partner_servers_id',
            'partner_servers_url',
            'partner_servers_name',
            'partner_servers_status',
            'partner_servers_lastSeen',
            'partner_servers_created',
        ]) ?: [];
    }

    /**
     * Verbindung trennen (beide Seiten)
     */
    public function disconnect(int $serverId, int $instanceId): bool
    {
        $server = $this->getServer($serverId);
        if (!$server || $server['instances_id'] != $instanceId) return false;

        // Remote-Server benachrichtigen
        $this->sendAuthenticatedRequest(
            $server['partner_servers_url'] . '/api/federation/disconnect.php',
            $server['partner_servers_apiKey'],
            []
        );

        // Lokal deaktivieren
        $this->db->where('partner_servers_id', $serverId);
        $this->db->update('partner_servers', [
            'partner_servers_status' => 'revoked',
            'partner_servers_deleted' => 1,
        ]);

        $this->logFederation($serverId, 'outgoing', 'disconnect', 'success');
        return true;
    }

    /**
     * Eingehende Disconnect-Anfrage verarbeiten
     */
    public function handleDisconnect(string $apiKey): bool
    {
        $this->db->where('partner_servers_remoteApiKey', $apiKey);
        $this->db->where('partner_servers_deleted', 0);
        $server = $this->db->getOne('partner_servers');

        if ($server) {
            $this->db->where('partner_servers_id', $server['partner_servers_id']);
            $this->db->update('partner_servers', [
                'partner_servers_status' => 'revoked',
                'partner_servers_deleted' => 1,
            ]);
            $this->logFederation($server['partner_servers_id'], 'incoming', 'disconnect', 'success');
            return true;
        }
        return false;
    }

    /**
     * Verbindung testen (Ping an Partner-Server)
     */
    public function pingServer(int $serverId): array
    {
        $server = $this->getServer($serverId);
        if (!$server) return ['success' => false, 'error' => 'server_not_found'];

        $start = microtime(true);
        $response = $this->sendAuthenticatedRequest(
            $server['partner_servers_url'] . '/api/federation/ping.php',
            $server['partner_servers_apiKey'],
            []
        );
        $latency = round((microtime(true) - $start) * 1000);

        if ($response && $response['result']) {
            return [
                'success' => true,
                'latency_ms' => $latency,
                'server_name' => $response['response']['server_name'] ?? $server['partner_servers_name'],
                'version' => $response['response']['api_version'] ?? 'unknown',
            ];
        }

        return ['success' => false, 'error' => 'unreachable', 'latency_ms' => $latency];
    }

    // ── Interne Hilfsfunktionen ──

    private function getServer(int $serverId): ?array
    {
        $this->db->where('partner_servers_id', $serverId);
        $this->db->where('partner_servers_deleted', 0);
        return $this->db->getOne('partner_servers');
    }

    private function generateApiKey(): string
    {
        return bin2hex(random_bytes(32)); // 64 Zeichen hex
    }

    private function getOwnServerUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        // ROOTURL aus Config verwenden falls verfuegbar
        global $CONFIG;
        if (isset($CONFIG) && !empty($CONFIG['ROOTURL'])) {
            return rtrim($CONFIG['ROOTURL'], '/');
        }
        return $protocol . '://' . $host;
    }

    /**
     * HTTPS-Anfrage an Remote-Server senden (ohne Auth)
     */
    private function sendRequest(string $url, array $data): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Federation-Version: ' . self::API_VERSION,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'rmsclone-federation/' . self::API_VERSION,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Authentifizierte HTTPS-Anfrage an Partner-Server senden
     */
    private function sendAuthenticatedRequest(string $url, string $apiKey, array $data): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Federation-Version: ' . self::API_VERSION,
                'X-Federation-Key: ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'rmsclone-federation/' . self::API_VERSION,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return json_decode($response, true);
    }

    private function logFederation(?int $serverId, string $direction, string $endpoint, string $status, ?string $message = null): void
    {
        $this->db->insert('partner_federation_log', [
            'partner_servers_id' => $serverId,
            'partner_federation_log_direction' => $direction,
            'partner_federation_log_endpoint' => $endpoint,
            'partner_federation_log_status' => $status,
            'partner_federation_log_message' => $message,
        ]);
    }
}
