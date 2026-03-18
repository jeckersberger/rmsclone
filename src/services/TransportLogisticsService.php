<?php
/**
 * Transport & Logistik Service (J3)
 *
 * Verwaltet Transportfahrten, Fahrzeuge, Fahrer, Haltestellen und Kosten.
 * Unterstützt Kapazitätsprüfung, Tourenplanung und Kostentracking.
 *
 * MeekroDB Pattern: getOne($table, $where=null, $columns=null), no groupBy(), use affectedRows()
 */
class TransportLogisticsService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ===== VEHICLES =====

    /**
     * Get all vehicles for an instance
     */
    public function getVehicles(int $instanceId, ?bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM transport_vehicles WHERE instances_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        return $this->db->rawQuery($sql, [$instanceId]) ?: [];
    }

    /**
     * Get single vehicle
     */
    public function getVehicle(int $vehicleId): ?array
    {
        $this->db->where('id', $vehicleId);
        return $this->db->getOne('transport_vehicles');
    }

    /**
     * Create new vehicle
     */
    public function createVehicle(array $data): int
    {
        $insert = [
            'instances_id' => $data['instances_id'],
            'name' => $data['name'],
            'type' => $data['type'], // van, truck, trailer, car
            'license_plate' => $data['license_plate'] ?? null,
            'max_weight_kg' => isset($data['max_weight_kg']) ? intval($data['max_weight_kg']) : null,
            'cargo_volume_m3' => isset($data['cargo_volume_m3']) ? floatval($data['cargo_volume_m3']) : null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('transport_vehicles', $insert);
    }

    /**
     * Update vehicle
     */
    public function updateVehicle(int $id, array $data): bool
    {
        $update = [];
        $allowed = ['name', 'type', 'license_plate', 'max_weight_kg', 'cargo_volume_m3', 'notes', 'is_active'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) return false;

        $this->db->where('id', $id);
        $this->db->update('transport_vehicles', $update);
        return $this->db->affectedRows() > 0;
    }

    // ===== DRIVERS =====

    /**
     * Get all drivers for an instance
     */
    public function getDrivers(int $instanceId): array
    {
        $sql = "SELECT td.*, u.users_name1, u.users_name2, u.users_email
                FROM transport_drivers td
                JOIN users u ON td.users_userid = u.users_userid
                WHERE td.instances_id = ?
                ORDER BY u.users_name1, u.users_name2";
        return $this->db->rawQuery($sql, [$instanceId]) ?: [];
    }

    /**
     * Get single driver
     */
    public function getDriver(int $driverId): ?array
    {
        $this->db->where('id', $driverId);
        return $this->db->getOne('transport_drivers');
    }

    /**
     * Create new driver
     */
    public function createDriver(array $data): int
    {
        $insert = [
            'instances_id' => $data['instances_id'],
            'users_userid' => $data['users_userid'],
            'license_types' => $data['license_types'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_available' => $data['is_available'] ?? true,
        ];

        return $this->db->insert('transport_drivers', $insert);
    }

    /**
     * Update driver
     */
    public function updateDriver(int $id, array $data): bool
    {
        $update = [];
        $allowed = ['license_types', 'phone', 'is_available'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) return false;

        $this->db->where('id', $id);
        $this->db->update('transport_drivers', $update);
        return $this->db->affectedRows() > 0;
    }

    // ===== TOURS =====

    /**
     * Get tours with optional filtering
     */
    public function getTours(int $instanceId, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT t.*, d.users_userid, u.users_name1, u.users_name2, v.name AS vehicle_name
                FROM transport_tours t
                LEFT JOIN transport_drivers d ON t.driver_id = d.id
                LEFT JOIN users u ON d.users_userid = u.users_userid
                LEFT JOIN transport_vehicles v ON t.vehicle_id = v.id
                WHERE t.instances_id = ?";
        $params = [$instanceId];

        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        if ($dateFrom) {
            $sql .= " AND t.date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND t.date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY t.date DESC, t.created_at DESC";
        return $this->db->rawQuery($sql, $params) ?: [];
    }

    /**
     * Get single tour with stops and costs
     */
    public function getTour(int $id): ?array
    {
        $this->db->where('id', $id);
        $tour = $this->db->getOne('transport_tours');
        if (!$tour) return null;

        $tour['stops'] = $this->getTourStops($id);
        $tour['costs'] = $this->getTourCosts($id);
        $tour['driver'] = null;
        $tour['vehicle'] = null;

        if ($tour['driver_id']) {
            $this->db->where('id', $tour['driver_id']);
            $tour['driver'] = $this->db->getOne('transport_drivers');
        }

        if ($tour['vehicle_id']) {
            $this->db->where('id', $tour['vehicle_id']);
            $tour['vehicle'] = $this->db->getOne('transport_vehicles');
        }

        return $tour;
    }

    /**
     * Create new tour with stops
     */
    public function createTour(array $data, array $stops = []): int
    {
        $insert = [
            'instances_id' => $data['instances_id'],
            'name' => $data['name'],
            'date' => $data['date'],
            'driver_id' => $data['driver_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'total_distance_km' => isset($data['total_distance_km']) ? floatval($data['total_distance_km']) : null,
            'total_cost' => isset($data['total_cost']) ? floatval($data['total_cost']) : null,
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $tourId = $this->db->insert('transport_tours', $insert);

        // Add stops
        foreach ($stops as $idx => $stop) {
            $this->addStop($tourId, array_merge($stop, ['stop_order' => $idx + 1]));
        }

        return $tourId;
    }

    /**
     * Update tour (not stops/costs)
     */
    public function updateTour(int $id, array $data): bool
    {
        $update = [];
        $allowed = ['name', 'date', 'driver_id', 'vehicle_id', 'status', 'total_distance_km', 'total_cost', 'notes'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) return false;

        $update['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        $this->db->update('transport_tours', $update);
        return $this->db->affectedRows() > 0;
    }

    /**
     * Update tour status
     */
    public function updateTourStatus(int $id, string $status): bool
    {
        $this->db->where('id', $id);
        $this->db->update('transport_tours', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affectedRows() > 0;
    }

    // ===== TOUR STOPS =====

    /**
     * Get all stops for a tour
     */
    public function getTourStops(int $tourId): array
    {
        $sql = "SELECT * FROM transport_tour_stops WHERE tour_id = ? ORDER BY stop_order ASC";
        return $this->db->rawQuery($sql, [$tourId]) ?: [];
    }

    /**
     * Add stop to tour
     */
    public function addStop(int $tourId, array $data): int
    {
        $insert = [
            'tour_id' => $tourId,
            'stop_order' => $data['stop_order'] ?? 1,
            'type' => $data['type'], // pickup, delivery, return
            'project_id' => $data['project_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'address' => $data['address'] ?? null,
            'time_window_start' => $data['time_window_start'] ?? null,
            'time_window_end' => $data['time_window_end'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return $this->db->insert('transport_tour_stops', $insert);
    }

    /**
     * Update stop details
     */
    public function updateStop(int $stopId, array $data): bool
    {
        $update = [];
        $allowed = ['type', 'project_id', 'client_id', 'address', 'time_window_start', 'time_window_end', 'notes'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) return false;

        $this->db->where('id', $stopId);
        $this->db->update('transport_tour_stops', $update);
        return $this->db->affectedRows() > 0;
    }

    /**
     * Mark stop as completed with optional signature/photo
     */
    public function completeStop(int $stopId, ?string $signatureData = null, ?string $photoPath = null): bool
    {
        $update = [
            'completed_at' => date('Y-m-d H:i:s'),
            'confirmed_by_signature' => !empty($signatureData),
        ];

        if ($signatureData) {
            $update['signature_data'] = $signatureData;
        }
        if ($photoPath) {
            $update['photo_path'] = $photoPath;
        }

        $this->db->where('id', $stopId);
        $this->db->update('transport_tour_stops', $update);
        return $this->db->affectedRows() > 0;
    }

    /**
     * Mark stop as arrived
     */
    public function arriveAtStop(int $stopId): bool
    {
        $this->db->where('id', $stopId);
        $this->db->update('transport_tour_stops', [
            'arrived_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affectedRows() > 0;
    }

    // ===== COSTS =====

    /**
     * Add cost to tour
     */
    public function addCost(int $tourId, array $data): int
    {
        $insert = [
            'tour_id' => $tourId,
            'cost_type' => $data['cost_type'], // fuel, toll, parking, other
            'amount' => floatval($data['amount']),
            'description' => $data['description'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('transport_costs', $insert);
    }

    /**
     * Get all costs for a tour
     */
    public function getTourCosts(int $tourId): array
    {
        $sql = "SELECT * FROM transport_costs WHERE tour_id = ? ORDER BY created_at DESC";
        return $this->db->rawQuery($sql, [$tourId]) ?: [];
    }

    /**
     * Delete cost entry
     */
    public function deleteCost(int $costId): bool
    {
        $this->db->where('id', $costId);
        $this->db->delete('transport_costs');
        return $this->db->affectedRows() > 0;
    }

    // ===== CALCULATIONS =====

    /**
     * Calculate total weight of tour items (from associated assets)
     * This assumes assets are linked to tour via project or directly
     */
    public function calculateTourWeight(int $tourId): float
    {
        $tour = $this->getTour($tourId);
        if (!$tour) return 0;

        $totalWeight = 0;

        // Sum weights from tour stops' projects/clients
        foreach ($tour['stops'] as $stop) {
            if ($stop['project_id']) {
                // Get assets for this project
                $sql = "SELECT COALESCE(SUM(a.assetTypes_weightGramms), 0) as total_weight_g
                        FROM assets a
                        WHERE a.projects_id = ?";
                $result = $this->db->rawQuery($sql, [$stop['project_id']]);
                if ($result && isset($result[0]['total_weight_g'])) {
                    $totalWeight += $result[0]['total_weight_g'] / 1000; // Convert to kg
                }
            }
        }

        return round($totalWeight, 2);
    }

    /**
     * Check if vehicle has capacity for load
     */
    public function checkVehicleCapacity(int $vehicleId, float $requiredWeight, float $requiredVolume): bool
    {
        $vehicle = $this->getVehicle($vehicleId);
        if (!$vehicle) return false;

        if ($vehicle['max_weight_kg'] && $requiredWeight > $vehicle['max_weight_kg']) {
            return false;
        }

        if ($vehicle['cargo_volume_m3'] && $requiredVolume > $vehicle['cargo_volume_m3']) {
            return false;
        }

        return true;
    }

    /**
     * Get tours for a specific project
     */
    public function getToursForProject(int $projectId): array
    {
        $sql = "SELECT DISTINCT t.* FROM transport_tours t
                JOIN transport_tour_stops ts ON t.id = ts.tour_id
                WHERE ts.project_id = ?
                ORDER BY t.date DESC";
        return $this->db->rawQuery($sql, [$projectId]) ?: [];
    }

    /**
     * Get tours for a specific date
     */
    public function getToursForDate(string $date, int $instanceId): array
    {
        $sql = "SELECT t.*, d.users_userid, u.users_name1, u.users_name2, v.name AS vehicle_name
                FROM transport_tours t
                LEFT JOIN transport_drivers d ON t.driver_id = d.id
                LEFT JOIN users u ON d.users_userid = u.users_userid
                LEFT JOIN transport_vehicles v ON t.vehicle_id = v.id
                WHERE t.date = ? AND t.instances_id = ?
                ORDER BY t.created_at ASC";
        return $this->db->rawQuery($sql, [$date, $instanceId]) ?: [];
    }

    /**
     * Get dashboard statistics for today and upcoming
     */
    public function getDashboardStats(int $instanceId): array
    {
        $today = date('Y-m-d');
        $stats = [];

        // Today's tours
        $sql = "SELECT COUNT(*) as count FROM transport_tours WHERE date = ? AND instances_id = ?";
        $result = $this->db->rawQuery($sql, [$today, $instanceId]);
        $stats['tours_today'] = $result ? intval($result[0]['count']) : 0;

        // Upcoming tours (next 7 days)
        $sql = "SELECT COUNT(*) as count FROM transport_tours WHERE date > ? AND date <= DATE_ADD(?, INTERVAL 7 DAY) AND instances_id = ?";
        $result = $this->db->rawQuery($sql, [$today, $today, $instanceId]);
        $stats['tours_upcoming'] = $result ? intval($result[0]['count']) : 0;

        // Total distance this month
        $monthStart = date('Y-m-01');
        $sql = "SELECT COALESCE(SUM(total_distance_km), 0) as total_km FROM transport_tours WHERE instances_id = ? AND date >= ?";
        $result = $this->db->rawQuery($sql, [$instanceId, $monthStart]);
        $stats['total_distance_month_km'] = $result ? floatval($result[0]['total_km']) : 0;

        // Total costs this month
        $sql = "SELECT COALESCE(SUM(amount), 0) as total_cost FROM transport_costs
                WHERE tour_id IN (SELECT id FROM transport_tours WHERE instances_id = ? AND date >= ?)";
        $result = $this->db->rawQuery($sql, [$instanceId, $monthStart]);
        $stats['total_costs_month'] = $result ? floatval($result[0]['total_cost']) : 0;

        // In-transit tours
        $sql = "SELECT COUNT(*) as count FROM transport_tours WHERE status IN ('in_transit', 'delivering') AND instances_id = ?";
        $result = $this->db->rawQuery($sql, [$instanceId]);
        $stats['tours_active'] = $result ? intval($result[0]['count']) : 0;

        return $stats;
    }
}
