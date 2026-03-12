<?php
/**
 * BusinessRepo - Reads instance/business settings for document rendering.
 */
class BusinessRepo {
    public static function getSettings($db, int $instanceId): array {
        $db->where('instances_id', $instanceId);
        $instance = $db->getOne('instances');
        if (!$instance) throw new \RuntimeException("Instance {$instanceId} nicht gefunden.");
        return $instance;
    }
}
