<?php
/**
 * MinRentalDurationService - Mindestmietdauer pro Asset-Typ
 *
 * Verwaltet und prueft Mindestmietdauern fuer verschiedene Asset-Typen.
 */
class MinRentalDurationService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Mindestmietdauer fuer einen Asset-Typ setzen
     */
    public function setMinDuration(int $assetTypeId, int $minDays): bool
    {
        $this->db->where('assetTypes_id', $assetTypeId);
        return (bool) $this->db->update('assetTypes', [
            'assetTypes_minRentalDays' => max(0, $minDays),
        ]);
    }

    /**
     * Mindestmietdauer abrufen
     */
    public function getMinDuration(int $assetTypeId): int
    {
        $this->db->where('assetTypes_id', $assetTypeId);
        $result = $this->db->getOne('assetTypes', null, ['assetTypes_minRentalDays']);
        return $result ? (int) ($result['assetTypes_minRentalDays'] ?? 0) : 0;
    }

    /**
     * Prueft ob eine Buchung die Mindestmietdauer erfuellt
     */
    public function validateDuration(int $assetTypeId, string $startDate, string $endDate): array
    {
        $minDays = $this->getMinDuration($assetTypeId);
        if ($minDays <= 0) {
            return ['valid' => true, 'min_days' => 0, 'actual_days' => 0];
        }

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $actualDays = (int) $start->diff($end)->days + 1;

        return [
            'valid' => $actualDays >= $minDays,
            'min_days' => $minDays,
            'actual_days' => $actualDays,
            'message' => $actualDays < $minDays
                ? "Mindestmietdauer: {$minDays} Tage (gewaehlt: {$actualDays} Tage)"
                : null,
        ];
    }

    /**
     * Prueft mehrere Asset-Typen auf einmal
     */
    public function validateBulk(array $assetTypeIds, string $startDate, string $endDate): array
    {
        $results = [];
        $allValid = true;
        foreach ($assetTypeIds as $id) {
            $check = $this->validateDuration(intval($id), $startDate, $endDate);
            $results[$id] = $check;
            if (!$check['valid']) $allValid = false;
        }
        return ['all_valid' => $allValid, 'checks' => $results];
    }
}
