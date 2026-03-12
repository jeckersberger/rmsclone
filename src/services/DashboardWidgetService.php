<?php
/**
 * Dashboard Widget Service
 *
 * Manages per-user dashboard widget configuration: ordering, visibility, sizing.
 * Each user+instance pair has its own widget layout stored in dashboard_widget_config.
 */
class DashboardWidgetService
{
    private $db;

    /**
     * All available widget keys with their default settings.
     * Position determines default display order; all visible by default.
     */
    private static $availableWidgets = [
        ['widget_key' => 'revenue_kpi',       'position' => 0,  'visible' => 1, 'size' => 'small'],
        ['widget_key' => 'outstanding_kpi',   'position' => 1,  'visible' => 1, 'size' => 'small'],
        ['widget_key' => 'overdue_kpi',       'position' => 2,  'visible' => 1, 'size' => 'small'],
        ['widget_key' => 'active_projects',   'position' => 3,  'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'upcoming_projects', 'position' => 4,  'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'revenue_chart',     'position' => 5,  'visible' => 1, 'size' => 'large'],
        ['widget_key' => 'top_clients',       'position' => 6,  'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'seasonality',       'position' => 7,  'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'utilization',       'position' => 8,  'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'recent_invoices',   'position' => 9,  'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'dunning_overview',  'position' => 10, 'visible' => 1, 'size' => 'medium'],
        ['widget_key' => 'kur_warning',       'position' => 11, 'visible' => 1, 'size' => 'small'],
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Return the list of all available widget keys.
     *
     * @return string[]
     */
    public static function getAvailableWidgetKeys(): array
    {
        return array_column(self::$availableWidgets, 'widget_key');
    }

    /**
     * Return default widget list with positions, visibility and size.
     *
     * @return array
     */
    public function getDefaultWidgets(): array
    {
        return self::$availableWidgets;
    }

    /**
     * Get the widget configuration for a user+instance.
     * Falls back to defaults if nothing has been saved yet.
     *
     * @param string $userId
     * @param int    $instanceId
     * @return array
     */
    public function getWidgetConfig(string $userId, int $instanceId): array
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('position', 'ASC');
        $rows = $this->db->get('dashboard_widget_config', null, [
            'widget_key', 'position', 'visible', 'size', 'config_json'
        ]);

        if (!$rows || count($rows) === 0) {
            return $this->getDefaultWidgets();
        }

        $config = [];
        foreach ($rows as $row) {
            $config[] = [
                'widget_key'  => $row['widget_key'],
                'position'    => (int) $row['position'],
                'visible'     => (int) $row['visible'],
                'size'        => $row['size'],
                'config_json' => $row['config_json'],
            ];
        }

        return $config;
    }

    /**
     * Save full widget configuration (order, visibility, size) for a user+instance.
     * Replaces any existing configuration using INSERT … ON DUPLICATE KEY UPDATE.
     *
     * @param string $userId
     * @param int    $instanceId
     * @param array  $widgets  Array of {widget_key, position, visible, size}
     * @return bool
     */
    public function saveWidgetConfig(string $userId, int $instanceId, array $widgets): bool
    {
        $validKeys = self::getAvailableWidgetKeys();
        $validSizes = ['small', 'medium', 'large'];

        foreach ($widgets as $widget) {
            $widgetKey = $widget['widget_key'] ?? null;
            if (!$widgetKey || !in_array($widgetKey, $validKeys, true)) {
                continue;
            }

            $size = $widget['size'] ?? 'medium';
            if (!in_array($size, $validSizes, true)) {
                $size = 'medium';
            }

            $data = [
                'users_userid' => $userId,
                'instances_id' => $instanceId,
                'widget_key'   => $widgetKey,
                'position'     => (int) ($widget['position'] ?? 0),
                'visible'      => (int) ($widget['visible'] ?? 1),
                'size'         => $size,
                'config_json'  => $widget['config_json'] ?? null,
            ];

            $this->db->onDuplicate(array_keys($data));
            $this->db->insert('dashboard_widget_config', $data);
        }

        return true;
    }

    /**
     * Update a single widget's properties for a user+instance.
     *
     * @param string $userId
     * @param int    $instanceId
     * @param string $widgetKey
     * @param array  $data  Fields to update (position, visible, size, config_json)
     * @return bool
     */
    public function updateWidget(string $userId, int $instanceId, string $widgetKey, array $data): bool
    {
        $validKeys = self::getAvailableWidgetKeys();
        if (!in_array($widgetKey, $validKeys, true)) {
            return false;
        }

        $updateFields = [];
        if (isset($data['position'])) {
            $updateFields['position'] = (int) $data['position'];
        }
        if (isset($data['visible'])) {
            $updateFields['visible'] = (int) $data['visible'];
        }
        if (isset($data['size'])) {
            $validSizes = ['small', 'medium', 'large'];
            $updateFields['size'] = in_array($data['size'], $validSizes, true) ? $data['size'] : 'medium';
        }
        if (array_key_exists('config_json', $data)) {
            $updateFields['config_json'] = $data['config_json'];
        }

        if (empty($updateFields)) {
            return false;
        }

        $this->db->where('users_userid', $userId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('widget_key', $widgetKey);
        return $this->db->update('dashboard_widget_config', $updateFields);
    }

    /**
     * Reset a user's widget config to defaults by deleting their saved rows.
     *
     * @param string $userId
     * @param int    $instanceId
     * @return bool
     */
    public function resetWidgetConfig(string $userId, int $instanceId): bool
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('instances_id', $instanceId);
        $this->db->delete('dashboard_widget_config');
        return true;
    }
}
