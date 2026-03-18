<?php

/**
 * SustainabilityService
 *
 * Manages ESG reporting, CO2 tracking for transport and energy consumption,
 * and report generation. Integration hooks with transport and project modules.
 */
class SustainabilityService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get sustainability configuration for an instance
     *
     * @param int $instanceId
     * @return array Configuration with emission factors, costs, etc.
     */
    public function getConfig(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $config = $this->db->getOne('sustainability_config');

        if (!$config) {
            // Return defaults if no config exists yet
            return [
                'instances_id' => $instanceId,
                'emission_factors' => [
                    'van_per_km' => 0.21,
                    'truck_per_km' => 0.35,
                    'car_per_km' => 0.15
                ],
                'kwh_price_eur' => 0.30,
                'co2_per_kwh' => 0.42,
                'enable_client_reports' => false
            ];
        }

        if (is_string($config['emission_factors'])) {
            $config['emission_factors'] = json_decode($config['emission_factors'], true) ?? [];
        }

        return $config;
    }

    /**
     * Save or update sustainability configuration
     *
     * @param int $instanceId
     * @param array $data Configuration data
     * @return bool
     */
    public function saveConfig(int $instanceId, array $data): bool
    {
        $this->db->where('instances_id', $instanceId);
        $existing = $this->db->getOne('sustainability_config');

        $updateData = [
            'instances_id' => $instanceId,
            'kwh_price_eur' => (float)($data['kwh_price_eur'] ?? 0.30),
            'co2_per_kwh' => (float)($data['co2_per_kwh'] ?? 0.42),
            'enable_client_reports' => !empty($data['enable_client_reports']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Handle emission factors
        if (isset($data['emission_factors'])) {
            if (is_array($data['emission_factors'])) {
                $updateData['emission_factors'] = json_encode($data['emission_factors']);
            } else {
                $updateData['emission_factors'] = $data['emission_factors'];
            }
        }

        if ($existing) {
            $this->db->where('instances_id', $instanceId);
            return $this->db->update('sustainability_config', $updateData) > 0;
        } else {
            $updateData['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert('sustainability_config', $updateData) && $this->db->getInsertId() > 0;
        }
    }

    /**
     * Calculate CO2 emissions for transport based on distance and vehicle type
     *
     * @param float $distanceKm Distance traveled in km
     * @param string $vehicleType Type of vehicle (van, truck, car, etc.)
     * @param int $instanceId
     * @return float CO2 emissions in kg
     */
    public function calculateTransportCo2(float $distanceKm, string $vehicleType, int $instanceId): float
    {
        $config = $this->getConfig($instanceId);
        $factors = $config['emission_factors'];

        $vehicleKey = strtolower($vehicleType) . '_per_km';
        $factor = $factors[$vehicleKey] ?? 0.15; // Default to car if unknown

        return round($distanceKm * $factor, 4);
    }

    /**
     * Log transport emission entry
     *
     * @param int $tourId Transport tour ID
     * @param int|null $projectId Associated project ID
     * @param float $distanceKm Distance in km
     * @param string $vehicleType Vehicle type
     * @param int $instanceId
     * @return int ID of created log entry
     */
    public function logTransportEmission(int $tourId, ?int $projectId, float $distanceKm, string $vehicleType, int $instanceId): int
    {
        $co2 = $this->calculateTransportCo2($distanceKm, $vehicleType, $instanceId);

        $this->db->insert('sustainability_transport_log', [
            'tour_id' => $tourId,
            'project_id' => $projectId,
            'distance_km' => $distanceKm,
            'vehicle_type' => $vehicleType,
            'co2_kg' => $co2,
            'instances_id' => $instanceId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->db->getInsertId();
    }

    /**
     * Calculate total energy consumption for a project from equipment list
     *
     * Sums: equipment power rating (kW) × hours in operation during project duration
     *
     * @param int $projectId
     * @param int $instanceId
     * @return array ['total_kwh' => float, 'calculation_basis' => string]
     */
    public function calculateProjectEnergy(int $projectId, int $instanceId): array
    {
        // Get project duration
        $this->db->where('id', $projectId);
        $this->db->where('instances_id', $instanceId);
        $project = $this->db->getOne('projects');

        if (!$project) {
            return ['total_kwh' => 0, 'calculation_basis' => 'Project not found'];
        }

        $startDate = strtotime($project['start_date'] ?? date('Y-m-d'));
        $endDate = strtotime($project['end_date'] ?? date('Y-m-d'));
        $durationDays = ($endDate - $startDate) / 86400;
        $durationHours = max(1, $durationDays * 24);

        // Get equipment items for project (join with asset inventory)
        // Assets on project = asset quantities that are active during project
        $this->db->where('projects_id', $projectId);
        $this->db->where('instances_id', $instanceId);
        $assets = $this->db->get('projects_assets', null, ['assets_id', 'qty']);

        $totalKwh = 0.0;
        $equipmentList = [];

        foreach ($assets as $asset) {
            $this->db->where('id', $asset['assets_id']);
            $this->db->where('instances_id', $instanceId);
            $assetDetail = $this->db->getOne('asset');

            if ($assetDetail && !empty($assetDetail['power_rating'])) {
                // power_rating is in kW
                $powerKw = (float)$assetDetail['power_rating'];
                $quantity = (int)($asset['qty'] ?? 1);
                $kwh = $powerKw * $quantity * $durationHours;
                $totalKwh += $kwh;

                $equipmentList[] = sprintf(
                    '%s (%.2f kW) × %d × %.1f h = %.2f kWh',
                    $assetDetail['asset_name'] ?? 'Unknown',
                    $powerKw,
                    $quantity,
                    $durationHours,
                    $kwh
                );
            }
        }

        $basis = !empty($equipmentList) ? implode('; ', $equipmentList) : 'No equipment with power rating found';

        return [
            'total_kwh' => round($totalKwh, 2),
            'calculation_basis' => $basis
        ];
    }

    /**
     * Log energy consumption for a project
     *
     * @param int $projectId
     * @param float $totalKwh Total electricity consumption in kWh
     * @param int $instanceId
     * @return int ID of created log entry
     */
    public function logProjectEnergy(int $projectId, float $totalKwh, int $instanceId): int
    {
        $config = $this->getConfig($instanceId);
        $co2 = round($totalKwh * $config['co2_per_kwh'], 4);

        $energyCalc = $this->calculateProjectEnergy($projectId, $instanceId);

        $this->db->insert('sustainability_energy_log', [
            'project_id' => $projectId,
            'total_kwh' => $totalKwh,
            'co2_kg' => $co2,
            'calculation_basis' => $energyCalc['calculation_basis'],
            'instances_id' => $instanceId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->db->getInsertId();
    }

    /**
     * Get emissions summary for a time period
     *
     * @param int $instanceId
     * @param string $period 'day', 'week', 'month', 'quarter', 'year'
     * @param string|null $dateFrom Custom start date (Y-m-d)
     * @param string|null $dateTo Custom end date (Y-m-d)
     * @return array Summary with total_co2_kg, transport/energy split, averages
     */
    public function getEmissionsSummary(int $instanceId, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        // Calculate date range
        if ($dateFrom && $dateTo) {
            $from = $dateFrom;
            $to = $dateTo;
        } else {
            $today = date('Y-m-d');
            switch ($period) {
                case 'day':
                    $from = $today;
                    $to = $today;
                    break;
                case 'week':
                    $from = date('Y-m-d', strtotime('monday this week'));
                    $to = date('Y-m-d', strtotime('sunday this week'));
                    break;
                case 'quarter':
                    $month = (int)date('m');
                    $quarterStart = (int)ceil($month / 3) * 3 - 2;
                    $quarterEnd = $quarterStart + 2;
                    $from = date('Y-m-01', mktime(0, 0, 0, $quarterStart, 1));
                    $to = date('Y-m-t', mktime(0, 0, 0, $quarterEnd, 1));
                    break;
                case 'year':
                    $from = date('Y-01-01');
                    $to = date('Y-12-31');
                    break;
                case 'month':
                default:
                    $from = date('Y-m-01');
                    $to = date('Y-m-t');
            }
        }

        // Transport emissions
        $this->db->where('instances_id', $instanceId);
        $this->db->where('created_at', ['>=', $from . ' 00:00:00']);
        $this->db->where('created_at', ['<=', $to . ' 23:59:59']);
        $transLogs = $this->db->get('sustainability_transport_log');

        $transportCo2 = 0;
        foreach ($transLogs as $log) {
            $transportCo2 += (float)$log['co2_kg'];
        }

        // Energy emissions
        $this->db->where('instances_id', $instanceId);
        $this->db->where('created_at', ['>=', $from . ' 00:00:00']);
        $this->db->where('created_at', ['<=', $to . ' 23:59:59']);
        $energyLogs = $this->db->get('sustainability_energy_log');

        $energyCo2 = 0;
        foreach ($energyLogs as $log) {
            $energyCo2 += (float)$log['co2_kg'];
        }

        $totalCo2 = $transportCo2 + $energyCo2;

        // Count projects involved
        $projectIds = array_unique(array_column($transLogs, 'project_id') + array_column($energyLogs, 'project_id'));
        $projectIds = array_filter($projectIds);
        $projectCount = count($projectIds);

        $avgPerProject = $projectCount > 0 ? round($totalCo2 / $projectCount, 2) : 0;

        return [
            'period' => $period,
            'date_from' => $from,
            'date_to' => $to,
            'total_co2_kg' => round($totalCo2, 2),
            'transport_co2_kg' => round($transportCo2, 2),
            'energy_co2_kg' => round($energyCo2, 2),
            'transport_percentage' => $totalCo2 > 0 ? round(($transportCo2 / $totalCo2) * 100, 1) : 0,
            'projects_count' => $projectCount,
            'avg_per_project' => $avgPerProject
        ];
    }

    /**
     * Get emissions breakdown by project
     *
     * @param int $instanceId
     * @param string|null $dateFrom Custom start date (Y-m-d)
     * @param string|null $dateTo Custom end date (Y-m-d)
     * @return array Projects with their CO2 totals and breakdown
     */
    public function getEmissionsByProject(int $instanceId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ?? date('Y-m-01');
        $to = $dateTo ?? date('Y-m-t');

        // Transport emissions by project
        $this->db->where('instances_id', $instanceId);
        $this->db->where('project_id', ['>', 0], 'AND');
        $this->db->where('created_at', ['>=', $from . ' 00:00:00']);
        $this->db->where('created_at', ['<=', $to . ' 23:59:59']);
        $transLogs = $this->db->get('sustainability_transport_log');

        // Energy emissions by project
        $this->db->where('instances_id', $instanceId);
        $this->db->where('created_at', ['>=', $from . ' 00:00:00']);
        $this->db->where('created_at', ['<=', $to . ' 23:59:59']);
        $energyLogs = $this->db->get('sustainability_energy_log');

        // Aggregate by project
        $projects = [];

        foreach ($transLogs as $log) {
            $pid = $log['project_id'];
            if (!isset($projects[$pid])) {
                $projects[$pid] = ['transport_co2' => 0, 'energy_co2' => 0, 'total_co2' => 0];
            }
            $projects[$pid]['transport_co2'] += (float)$log['co2_kg'];
        }

        foreach ($energyLogs as $log) {
            $pid = $log['project_id'];
            if (!isset($projects[$pid])) {
                $projects[$pid] = ['transport_co2' => 0, 'energy_co2' => 0, 'total_co2' => 0];
            }
            $projects[$pid]['energy_co2'] += (float)$log['co2_kg'];
        }

        // Calculate totals and fetch project names
        $result = [];
        foreach ($projects as $projectId => $emissions) {
            $this->db->where('id', $projectId);
            $this->db->where('instances_id', $instanceId);
            $projectData = $this->db->getOne('projects', null, ['id', 'project_name']);

            if ($projectData) {
                $totalCo2 = $emissions['transport_co2'] + $emissions['energy_co2'];
                $result[] = [
                    'project_id' => $projectId,
                    'project_name' => $projectData['project_name'],
                    'transport_co2_kg' => round($emissions['transport_co2'], 2),
                    'energy_co2_kg' => round($emissions['energy_co2'], 2),
                    'total_co2_kg' => round($totalCo2, 2)
                ];
            }
        }

        // Sort by total CO2 descending
        usort($result, fn($a, $b) => $b['total_co2_kg'] <=> $a['total_co2_kg']);

        return $result;
    }

    /**
     * Get emissions for a specific client
     *
     * @param int $clientId
     * @param int $instanceId
     * @return array Client sustainability data
     */
    public function getEmissionsByClient(int $clientId, int $instanceId): array
    {
        // Get all projects for this client
        $this->db->where('clients_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $projects = $this->db->get('projects', null, ['id']);

        $projectIds = array_column($projects, 'id');

        if (empty($projectIds)) {
            return [
                'client_id' => $clientId,
                'total_co2_kg' => 0,
                'project_count' => 0,
                'projects' => []
            ];
        }

        // Get transport logs for these projects
        $transportCo2 = 0;
        foreach ($projectIds as $pid) {
            $this->db->where('project_id', $pid);
            $this->db->where('instances_id', $instanceId);
            $logs = $this->db->get('sustainability_transport_log');
            foreach ($logs as $log) {
                $transportCo2 += (float)$log['co2_kg'];
            }
        }

        // Get energy logs for these projects
        $energyCo2 = 0;
        foreach ($projectIds as $pid) {
            $this->db->where('project_id', $pid);
            $this->db->where('instances_id', $instanceId);
            $logs = $this->db->get('sustainability_energy_log');
            foreach ($logs as $log) {
                $energyCo2 += (float)$log['co2_kg'];
            }
        }

        return [
            'client_id' => $clientId,
            'total_co2_kg' => round($transportCo2 + $energyCo2, 2),
            'transport_co2_kg' => round($transportCo2, 2),
            'energy_co2_kg' => round($energyCo2, 2),
            'project_count' => count($projectIds)
        ];
    }

    /**
     * Get month-by-month CO2 emissions trend
     *
     * @param int $instanceId
     * @param int $months Number of months to retrieve (default 12)
     * @return array Monthly data for chart [month => co2_kg]
     */
    public function getEmissionsTrend(int $instanceId, int $months = 12): array
    {
        $trend = [];
        $today = new DateTime();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = clone $today;
            $date->modify("-{$i} months");
            $monthStart = $date->format('Y-m-01');
            $monthEnd = $date->format('Y-m-t');
            $monthLabel = $date->format('Y-m');

            // Transport
            $this->db->where('instances_id', $instanceId);
            $this->db->where('created_at', ['>=', $monthStart . ' 00:00:00']);
            $this->db->where('created_at', ['<=', $monthEnd . ' 23:59:59']);
            $transLogs = $this->db->get('sustainability_transport_log');

            $transportCo2 = 0;
            foreach ($transLogs as $log) {
                $transportCo2 += (float)$log['co2_kg'];
            }

            // Energy
            $this->db->where('instances_id', $instanceId);
            $this->db->where('created_at', ['>=', $monthStart . ' 00:00:00']);
            $this->db->where('created_at', ['<=', $monthEnd . ' 23:59:59']);
            $energyLogs = $this->db->get('sustainability_energy_log');

            $energyCo2 = 0;
            foreach ($energyLogs as $log) {
                $energyCo2 += (float)$log['co2_kg'];
            }

            $trend[$monthLabel] = round($transportCo2 + $energyCo2, 2);
        }

        return $trend;
    }

    /**
     * Generate a new sustainability report
     *
     * @param string $reportType 'monthly', 'quarterly', 'annual', 'project', 'client'
     * @param string $periodStart Start date (Y-m-d)
     * @param string $periodEnd End date (Y-m-d)
     * @param int $instanceId
     * @param int $userId User creating the report
     * @return int Report ID
     */
    public function generateReport(string $reportType, string $periodStart, string $periodEnd, int $instanceId, int $userId): int
    {
        $summary = $this->getEmissionsSummary($instanceId, 'month', $periodStart, $periodEnd);
        $byProject = $this->getEmissionsByProject($instanceId, $periodStart, $periodEnd);
        $trend = $this->getEmissionsTrend($instanceId, 12);

        $reportName = match ($reportType) {
            'monthly' => date('F Y', strtotime($periodStart)) . ' - Sustainability Report',
            'quarterly' => $this->getQuarterLabel($periodStart) . ' - Sustainability Report',
            'annual' => date('Y', strtotime($periodStart)) . ' - Annual Sustainability Report',
            'project' => 'Project ESG Analysis',
            'client' => 'Client Sustainability Report',
            default => 'Sustainability Report'
        };

        $data = [
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'by_project' => $byProject,
            'trend_12_months' => $trend
        ];

        $this->db->insert('sustainability_reports', [
            'name' => $reportName,
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'data_json' => json_encode($data),
            'pdf_path' => null,
            'instances_id' => $instanceId,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->db->getInsertId();
    }

    /**
     * Get all reports for an instance
     *
     * @param int $instanceId
     * @return array List of reports
     */
    public function getReports(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('sustainability_reports');
    }

    /**
     * Get a single report with full details
     *
     * @param int $reportId
     * @return array|null Report data with parsed JSON
     */
    public function getReport(int $reportId): ?array
    {
        $this->db->where('id', $reportId);
        $report = $this->db->getOne('sustainability_reports');

        if ($report && is_string($report['data_json'])) {
            $report['data'] = json_decode($report['data_json'], true) ?? [];
        }

        return $report;
    }

    /**
     * Generate client-facing sustainability report
     *
     * Shows impact of client's events and projects
     *
     * @param int $clientId
     * @param string $periodStart (Y-m-d)
     * @param string $periodEnd (Y-m-d)
     * @param int $instanceId
     * @return array Report data for PDF generation
     */
    public function generateClientReport(int $clientId, string $periodStart, string $periodEnd, int $instanceId): array
    {
        $clientEmissions = $this->getEmissionsByClient($clientId, $instanceId);

        // Get client details
        $this->db->where('id', $clientId);
        $client = $this->db->getOne('clients', null, ['id', 'clients_name']);

        $totalCo2 = $clientEmissions['total_co2_kg'];
        $clientName = $client['clients_name'] ?? 'Unknown Client';

        $reportText = sprintf(
            "Ihr Event verursachte %.2f kg CO₂ Emissionen",
            $totalCo2
        );

        return [
            'client_id' => $clientId,
            'client_name' => $clientName,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_co2_kg' => $totalCo2,
            'transport_co2_kg' => $clientEmissions['transport_co2_kg'],
            'energy_co2_kg' => $clientEmissions['energy_co2_kg'],
            'project_count' => $clientEmissions['project_count'],
            'report_text' => $reportText,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get dashboard statistics
     *
     * @param int $instanceId
     * @return array Dashboard metrics
     */
    public function getDashboardStats(int $instanceId): array
    {
        // This month
        $thisMonthSummary = $this->getEmissionsSummary($instanceId, 'month');

        // Last year same month for comparison
        $lastYearStart = date('Y-m-01', strtotime('-1 year'));
        $lastYearEnd = date('Y-m-t', strtotime('-1 year'));
        $lastYearSummary = $this->getEmissionsSummary($instanceId, 'month', $lastYearStart, $lastYearEnd);

        // Greenest project (lowest CO2)
        $allProjects = $this->getEmissionsByProject($instanceId);
        $greenestProject = !empty($allProjects) ? $allProjects[count($allProjects) - 1] : null;

        // Total energy this month
        $this->db->where('instances_id', $instanceId);
        $this->db->where('created_at', ['>=', date('Y-m-01') . ' 00:00:00']);
        $this->db->where('created_at', ['<=', date('Y-m-t') . ' 23:59:59']);
        $energyLogs = $this->db->get('sustainability_energy_log');
        $totalKwh = 0;
        foreach ($energyLogs as $log) {
            $totalKwh += (float)$log['total_kwh'];
        }

        // YoY growth
        $yoyGrowth = 0;
        if ($lastYearSummary['total_co2_kg'] > 0) {
            $yoyGrowth = round(
                (($thisMonthSummary['total_co2_kg'] - $lastYearSummary['total_co2_kg']) / $lastYearSummary['total_co2_kg']) * 100,
                1
            );
        }

        return [
            'this_month_co2_kg' => $thisMonthSummary['total_co2_kg'],
            'this_month_transport_kg' => $thisMonthSummary['transport_co2_kg'],
            'this_month_energy_kg' => $thisMonthSummary['energy_co2_kg'],
            'this_month_projects' => $thisMonthSummary['projects_count'],
            'last_year_co2_kg' => $lastYearSummary['total_co2_kg'],
            'yoy_growth_percent' => $yoyGrowth,
            'total_kwh_this_month' => round($totalKwh, 2),
            'greenest_project' => $greenestProject,
            'transport_split_percent' => $thisMonthSummary['transport_percentage']
        ];
    }

    /**
     * Auto-log transport emissions when a tour is completed
     * Hook: Call from transport module on tour completion
     *
     * @param int $tourId
     * @param int $instanceId
     * @return void
     */
    public function autoLogFromTour(int $tourId, int $instanceId): void
    {
        // Fetch tour details (distance, vehicle type)
        $this->db->where('id', $tourId);
        $this->db->where('instances_id', $instanceId);
        $tour = $this->db->getOne('transport_tours');

        if (!$tour) {
            return;
        }

        $distance = (float)($tour['distance_km'] ?? 0);
        $vehicleType = $tour['vehicle_type'] ?? 'car';
        $projectId = isset($tour['project_id']) ? (int)$tour['project_id'] : null;

        if ($distance > 0) {
            $this->logTransportEmission($tourId, $projectId, $distance, $vehicleType, $instanceId);
        }
    }

    /**
     * Auto-log energy consumption when a project is completed
     * Hook: Call from project module on project completion
     *
     * @param int $projectId
     * @param int $instanceId
     * @return void
     */
    public function autoLogFromProject(int $projectId, int $instanceId): void
    {
        // Check if already logged
        $this->db->where('project_id', $projectId);
        $this->db->where('instances_id', $instanceId);
        $existing = $this->db->getOne('sustainability_energy_log');

        if ($existing) {
            return; // Already logged
        }

        $energyCalc = $this->calculateProjectEnergy($projectId, $instanceId);
        if ($energyCalc['total_kwh'] > 0) {
            $this->logProjectEnergy($projectId, $energyCalc['total_kwh'], $instanceId);
        }
    }

    /**
     * Helper: Get quarter label from date
     *
     * @param string $date Y-m-d format
     * @return string e.g., "Q1 2026"
     */
    private function getQuarterLabel(string $date): string
    {
        $month = (int)date('m', strtotime($date));
        $year = date('Y', strtotime($date));
        $quarter = (int)ceil($month / 3);
        return "Q{$quarter} {$year}";
    }
}
