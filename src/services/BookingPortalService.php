<?php
/**
 * BookingPortalService - Online-Buchungsportal (L3)
 *
 * Manages:
 * - Portal configuration per instance
 * - Public equipment catalog with availability
 * - Booking inquiries (guest and registered clients)
 * - Portal authentication for guests/clients
 */
class BookingPortalService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get portal configuration for an instance
     */
    public function getPortalConfig(int $instanceId): ?array
    {
        $this->db->where('instances_id', $instanceId);
        return $this->db->getOne('portal_config') ?: null;
    }

    /**
     * Save/update portal configuration
     */
    public function savePortalConfig(int $instanceId, array $data): bool
    {
        $allowedFields = [
            'is_active', 'portal_title', 'portal_description', 'logo_path',
            'primary_color', 'show_prices', 'require_registration',
            'require_admin_approval', 'terms_html'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if (in_array($field, ['is_active', 'show_prices', 'require_registration', 'require_admin_approval'])) {
                    $updateData[$field] = $value ? 1 : 0;
                } else {
                    $updateData[$field] = $value;
                }
            }
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        // Check if config exists
        $existing = $this->getPortalConfig($instanceId);
        if ($existing) {
            $this->db->where('instances_id', $instanceId);
            return (bool) $this->db->update('portal_config', $updateData);
        } else {
            $updateData['instances_id'] = $instanceId;
            $updateData['created_at'] = date('Y-m-d H:i:s');
            return (bool) $this->db->insert('portal_config', $updateData);
        }
    }

    /**
     * Get public equipment catalog with availability and pricing
     */
    public function getPublicCatalog(int $instanceId, ?int $categoryId = null, ?string $search = null): array
    {
        // Build SQL with optional search
        if ($search) {
            $search = "%{$search}%";
            $sql = "SELECT assetTypes_id, assetTypes_name, assetTypes_description,
                           assetTypes_dayRate, assetTypes_weekRate, assetCategories_id
                    FROM assetTypes
                    WHERE instances_id = ? AND assetTypes_deleted = 0";
            $params = [$instanceId];

            if ($categoryId) {
                $sql .= " AND assetCategories_id = ?";
                $params[] = $categoryId;
            }

            $sql .= " AND (assetTypes_name LIKE ? OR assetTypes_description LIKE ?)";
            $params[] = $search;
            $params[] = $search;

            $sql .= " ORDER BY assetTypes_name ASC";
            $assetTypes = $this->db->rawQuery($sql, $params) ?: [];
        } else {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('assetTypes_deleted', 0);

            if ($categoryId) {
                $this->db->where('assetCategories_id', $categoryId);
            }

            $this->db->orderBy('assetTypes_name', 'ASC');

            $assetTypes = $this->db->get('assetTypes', null, [
                'assetTypes_id', 'assetTypes_name', 'assetTypes_description',
                'assetTypes_dayRate', 'assetTypes_weekRate', 'assetCategories_id'
            ]) ?: [];
        }

        foreach ($assetTypes as &$type) {
            // Count available assets
            $result = $this->db->rawQuery(
                "SELECT COUNT(*) as cnt FROM assets WHERE assetTypes_id = ? AND assets_deleted = 0",
                [$type['assetTypes_id']]
            );
            $availableCount = (int)($result[0]['cnt'] ?? 0);
            $type['available_count'] = $availableCount;
        }

        return $assetTypes;
    }

    /**
     * Check availability for a specific asset type in date range
     * Returns: available count and pricing
     */
    public function checkAvailability(int $assetTypeId, string $startDate, string $endDate, int $quantity, int $instanceId): array
    {
        // Get asset type info
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('instances_id', $instanceId);
        $assetType = $this->db->getOne('assetTypes', null, [
            'assetTypes_name', 'assetTypes_dayRate', 'assetTypes_weekRate'
        ]);

        if (!$assetType) {
            return ['available' => false, 'message' => 'Asset type not found'];
        }

        // Count total assets of this type
        $result = $this->db->rawQuery(
            "SELECT COUNT(*) as cnt FROM assets WHERE assetTypes_id = ? AND assets_deleted = 0",
            [$assetTypeId]
        );
        $totalCount = (int)($result[0]['cnt'] ?? 0);

        if ($totalCount < $quantity) {
            return [
                'available' => false,
                'message' => "Only {$totalCount} available",
                'available_count' => $totalCount
            ];
        }

        // Check for assignments in date range (simplified: check if projects overlap)
        $sql = "SELECT COUNT(DISTINCT a.assets_id) as busy_count
                FROM assets a
                JOIN assetsAssignments aa ON a.assets_id = aa.assets_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.assetTypes_id = ?
                AND a.assets_deleted = 0
                AND aa.assetsAssignments_deleted = 0
                AND p.projects_deleted = 0
                AND p.projects_dates_use_start < ?
                AND p.projects_dates_use_end > ?";

        $result = $this->db->rawQuery($sql, [$assetTypeId, $endDate, $startDate]);
        $busyCount = (int) ($result[0]['busy_count'] ?? 0);
        $availableCount = $totalCount - $busyCount;

        if ($availableCount < $quantity) {
            return [
                'available' => false,
                'message' => "Only {$availableCount} available for this period",
                'available_count' => $availableCount
            ];
        }

        // Calculate rental days and pricing
        $startDt = new DateTime($startDate);
        $endDt = new DateTime($endDate);
        $days = (int) $startDt->diff($endDt)->format('%a') + 1;
        $weeks = (int) floor($days / 7);
        $remainingDays = $days % 7;

        $dayRate = (float) $assetType['assetTypes_dayRate'] ?? 0;
        $weekRate = (float) $assetType['assetTypes_weekRate'] ?? 0;

        // Use week rate if available and applicable
        $totalPrice = 0;
        if ($weeks > 0 && $weekRate > 0) {
            $totalPrice += $weeks * $weekRate;
        } else {
            $totalPrice += $weeks * 7 * $dayRate;
        }

        if ($remainingDays > 0) {
            $totalPrice += $remainingDays * $dayRate;
        }

        $totalPrice *= $quantity;

        return [
            'available' => true,
            'available_count' => $availableCount,
            'total_count' => $totalCount,
            'rental_days' => $days,
            'day_rate' => $dayRate,
            'week_rate' => $weekRate,
            'total_price' => $totalPrice,
            'quantity' => $quantity
        ];
    }

    /**
     * Submit a booking inquiry (guest or registered client)
     */
    public function submitInquiry(array $data, int $instanceId): int
    {
        $insertData = [
            'instances_id' => $instanceId,
            'items' => json_encode($data['items'] ?? []),
            'rental_start' => $data['rental_start'] ?? null,
            'rental_end' => $data['rental_end'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'new',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // If registered client
        if (!empty($data['client_id'])) {
            $insertData['client_id'] = (int) $data['client_id'];
        } else {
            // Guest inquiry
            $insertData['guest_name'] = $data['guest_name'] ?? null;
            $insertData['guest_email'] = $data['guest_email'] ?? null;
            $insertData['guest_phone'] = $data['guest_phone'] ?? null;
            $insertData['guest_company'] = $data['guest_company'] ?? null;
        }

        return (int) $this->db->insert('portal_inquiries', $insertData);
    }

    /**
     * Get all inquiries for an instance (admin)
     */
    public function getInquiries(int $instanceId, ?string $status = null): array
    {
        $this->db->where('instances_id', $instanceId);

        if ($status) {
            $this->db->where('status', $status);
        }

        $this->db->orderBy('created_at', 'DESC');

        return $this->db->get('portal_inquiries', null, ['*']) ?: [];
    }

    /**
     * Get a single inquiry by ID
     */
    public function getInquiry(int $id): ?array
    {
        $this->db->where('id', $id);
        return $this->db->getOne('portal_inquiries') ?: null;
    }

    /**
     * Update inquiry status
     */
    public function updateInquiryStatus(int $id, string $status, ?int $projectId = null): bool
    {
        $allowedStatuses = ['new', 'reviewed', 'quoted', 'accepted', 'rejected', 'cancelled'];

        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($projectId !== null) {
            $updateData['project_id'] = $projectId;
        }

        $this->db->where('id', $id);
        return (bool) $this->db->update('portal_inquiries', $updateData);
    }

    /**
     * Convert inquiry to project (admin action)
     */
    public function convertToProject(int $inquiryId, int $userId, int $instanceId): int
    {
        $inquiry = $this->getInquiry($inquiryId);

        if (!$inquiry) {
            return 0;
        }

        // Parse items
        $items = json_decode($inquiry['items'], true) ?? [];
        $totalValue = 0;

        foreach ($items as $item) {
            $totalValue += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        // Create project
        $projectData = [
            'instances_id' => $instanceId,
            'clients_id' => $inquiry['client_id'],
            'users_id' => $userId,
            'projects_name' => 'Portal: ' . ($inquiry['guest_name'] ?? $inquiry['guest_email'] ?? 'Unnamed'),
            'projects_description' => $inquiry['message'] ?? '',
            'projects_dates_use_start' => $inquiry['rental_start'],
            'projects_dates_use_end' => $inquiry['rental_end'],
            'projects_value_total' => $totalValue,
            'projects_status' => 'offer',
            'projects_created' => date('Y-m-d H:i:s'),
            'projects_updated' => date('Y-m-d H:i:s')
        ];

        $projectId = (int) $this->db->insert('projects', $projectData);

        if ($projectId) {
            // Link inquiry to project
            $this->updateInquiryStatus($inquiryId, 'accepted', $projectId);

            // Add line items to project (optional, depends on your project structure)
            // This would need to be expanded based on your asset assignment logic
        }

        return $projectId;
    }

    /**
     * Register a new portal client
     */
    public function registerPortalClient(array $data, int $instanceId): int
    {
        // Create client record
        $clientData = [
            'instances_id' => $instanceId,
            'clients_name' => $data['name'] ?? '',
            'clients_email' => $data['email'] ?? '',
            'clients_phone' => $data['phone'] ?? '',
            'clients_company' => $data['company'] ?? '',
            'clients_password' => password_hash($data['password'] ?? '', PASSWORD_BCRYPT),
            'clients_created' => date('Y-m-d H:i:s'),
            'clients_updated' => date('Y-m-d H:i:s')
        ];

        return (int) $this->db->insert('clients', $clientData);
    }

    /**
     * Login portal client (check credentials)
     */
    public function loginPortalClient(string $email, string $password, int $instanceId): ?array
    {
        $this->db->where('clients_email', $email);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);

        $client = $this->db->getOne('clients', null, [
            'clients_id', 'clients_name', 'clients_email', 'clients_password'
        ]);

        if (!$client) {
            return null;
        }

        if (!password_verify($password, $client['clients_password'])) {
            return null;
        }

        return [
            'clients_id' => (int) $client['clients_id'],
            'clients_name' => $client['clients_name'],
            'clients_email' => $client['clients_email']
        ];
    }

    /**
     * Create a portal session token (for guest/client access)
     */
    public function createPortalSession(?int $clientId = null): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->db->insert('portal_sessions', [
            'token' => $token,
            'client_id' => $clientId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $token;
    }

    /**
     * Validate portal session token
     */
    public function validatePortalSession(string $token): ?array
    {
        $this->db->where('token', $token);
        $this->db->where('expires_at', date('Y-m-d H:i:s'), '>=');

        $session = $this->db->getOne('portal_sessions', null, [
            'client_id', 'token', 'expires_at'
        ]);

        return $session ?: null;
    }

    /**
     * Get inquiries for a specific client
     */
    public function getClientInquiries(int $clientId): array
    {
        $this->db->where('client_id', $clientId);
        $this->db->orderBy('created_at', 'DESC');

        return $this->db->get('portal_inquiries', null, ['*']) ?: [];
    }

    /**
     * Get asset categories for portal
     */
    public function getCategories(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assetCategories_deleted', 0);
        $this->db->orderBy('assetCategories_name', 'ASC');

        return $this->db->get('assetCategories', null, [
            'assetCategories_id', 'assetCategories_name'
        ]) ?: [];
    }
}
