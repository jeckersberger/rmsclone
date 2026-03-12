<?php
/**
 * DiscountCodeService - Rabatt-Codes / Aktionspreise
 *
 * Verwaltet Rabattcodes mit:
 * - Prozent- oder Festbetrag-Rabatte
 * - Gueltigkeitszeitraum
 * - Maximale Nutzungsanzahl
 * - Asset-Typ-Einschraenkungen
 */
class DiscountCodeService
{
    private $db;

    const TYPE_PERCENT = 'percent';
    const TYPE_FIXED = 'fixed';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Rabattcode erstellen
     */
    public function create(int $instanceId, array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        if (strlen($code) < 3 || strlen($code) > 30) {
            return ['success' => false, 'error' => 'Code muss 3-30 Zeichen lang sein'];
        }
        if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
            return ['success' => false, 'error' => 'Code darf nur Grossbuchstaben, Zahlen, - und _ enthalten'];
        }

        // Duplikatpruefung
        $this->db->where('code', $code);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        if ($this->db->getOne('discount_codes', ['id'])) {
            return ['success' => false, 'error' => 'Code existiert bereits'];
        }

        $type = in_array($data['type'] ?? '', [self::TYPE_PERCENT, self::TYPE_FIXED])
            ? $data['type'] : self::TYPE_PERCENT;
        $value = max(0, floatval($data['value'] ?? 0));
        if ($type === self::TYPE_PERCENT && $value > 100) $value = 100;

        $id = $this->db->insert('discount_codes', [
            'instances_id' => $instanceId,
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'description' => $data['description'] ?? '',
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'max_uses' => isset($data['max_uses']) ? max(0, intval($data['max_uses'])) : 0,
            'current_uses' => 0,
            'min_order_value' => floatval($data['min_order_value'] ?? 0),
            'asset_type_ids' => !empty($data['asset_type_ids']) ? json_encode(array_map('intval', $data['asset_type_ids'])) : null,
            'active' => 1,
            'deleted' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id
            ? ['success' => true, 'id' => $id, 'code' => $code]
            : ['success' => false, 'error' => 'Fehler beim Speichern'];
    }

    /**
     * Rabattcode validieren und Rabatt berechnen
     */
    public function validate(string $code, int $instanceId, float $orderValue = 0, ?int $assetTypeId = null): array
    {
        $code = strtoupper(trim($code));
        $this->db->where('code', $code);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where('active', 1);
        $discount = $this->db->getOne('discount_codes');

        if (!$discount) {
            return ['valid' => false, 'error' => 'Ungueltiger Rabattcode'];
        }

        // Gueltigkeitszeitraum pruefen
        $now = date('Y-m-d');
        if ($discount['valid_from'] && $now < $discount['valid_from']) {
            return ['valid' => false, 'error' => 'Rabattcode noch nicht gueltig'];
        }
        if ($discount['valid_until'] && $now > $discount['valid_until']) {
            return ['valid' => false, 'error' => 'Rabattcode abgelaufen'];
        }

        // Max. Nutzungen pruefen
        if ($discount['max_uses'] > 0 && $discount['current_uses'] >= $discount['max_uses']) {
            return ['valid' => false, 'error' => 'Rabattcode wurde bereits maximal eingeloest'];
        }

        // Mindestbestellwert
        if ($discount['min_order_value'] > 0 && $orderValue < $discount['min_order_value']) {
            return ['valid' => false, 'error' => 'Mindestbestellwert: ' . number_format($discount['min_order_value'], 2, ',', '.') . ' EUR'];
        }

        // Asset-Typ-Einschraenkung
        if ($discount['asset_type_ids'] && $assetTypeId) {
            $allowedIds = json_decode($discount['asset_type_ids'], true) ?: [];
            if (!empty($allowedIds) && !in_array($assetTypeId, $allowedIds)) {
                return ['valid' => false, 'error' => 'Rabattcode gilt nicht fuer diesen Artikel'];
            }
        }

        // Rabatt berechnen
        $discountAmount = $discount['type'] === self::TYPE_PERCENT
            ? round($orderValue * $discount['value'] / 100, 2)
            : min($discount['value'], $orderValue);

        return [
            'valid' => true,
            'code' => $discount['code'],
            'type' => $discount['type'],
            'value' => floatval($discount['value']),
            'discount_amount' => $discountAmount,
            'description' => $discount['description'],
        ];
    }

    /**
     * Rabattcode einloesen (Zaehler erhoehen)
     */
    public function redeem(string $code, int $instanceId): bool
    {
        $code = strtoupper(trim($code));
        $this->db->where('code', $code);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        return (bool) $this->db->update('discount_codes', [
            'current_uses' => $this->db->inc(1),
        ]);
    }

    /**
     * Alle Rabattcodes einer Instanz auflisten
     */
    public function list(int $instanceId, bool $activeOnly = false): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        if ($activeOnly) $this->db->where('active', 1);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('discount_codes') ?: [];
    }

    /**
     * Rabattcode deaktivieren
     */
    public function deactivate(int $id, int $instanceId): bool
    {
        $this->db->where('id', $id);
        $this->db->where('instances_id', $instanceId);
        return (bool) $this->db->update('discount_codes', ['active' => 0]);
    }

    /**
     * Rabattcode loeschen (soft delete)
     */
    public function delete(int $id, int $instanceId): bool
    {
        $this->db->where('id', $id);
        $this->db->where('instances_id', $instanceId);
        return (bool) $this->db->update('discount_codes', ['deleted' => 1]);
    }
}
