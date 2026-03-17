<?php
/**
 * SurchargeService - Wochenend-/Feiertags-Zuschlaege
 *
 * Berechnet automatisch Zuschlaege fuer Vermietungen an
 * Wochenenden und gesetzlichen Feiertagen.
 */
class SurchargeService
{
    private $db;

    /** Standard-Zuschlaege in Prozent */
    const DEFAULT_WEEKEND_SURCHARGE = 15.0;
    const DEFAULT_HOLIDAY_SURCHARGE = 25.0;

    /** Deutsche gesetzliche Feiertage (bundesweit) */
    private static $fixedHolidays = [
        '01-01' => 'Neujahr',
        '05-01' => 'Tag der Arbeit',
        '10-03' => 'Tag der Deutschen Einheit',
        '12-25' => '1. Weihnachtstag',
        '12-26' => '2. Weihnachtstag',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Berechnet Zuschlaege fuer einen Mietzeitraum
     */
    public function calculateSurcharges(float $dailyRate, string $startDate, string $endDate, ?int $instanceId = null): array
    {
        $config = $this->getConfig($instanceId);
        if (!$config['enabled']) {
            return ['surcharge_amount' => 0, 'details' => [], 'total_days' => 0, 'surcharge_days' => 0];
        }

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $details = [];
        $totalSurcharge = 0;
        $totalDays = 0;
        $surchargeDays = 0;

        $current = clone $start;
        while ($current <= $end) {
            $totalDays++;
            $dateStr = $current->format('Y-m-d');
            $surchargePercent = 0;
            $reason = '';

            // Feiertag hat Vorrang (hoehere Rate)
            if ($config['holiday_enabled'] && $this->isHoliday($current)) {
                $surchargePercent = $config['holiday_rate'];
                $reason = 'Feiertag: ' . $this->getHolidayName($current);
            } elseif ($config['weekend_enabled'] && $this->isWeekend($current)) {
                $surchargePercent = $config['weekend_rate'];
                $reason = 'Wochenende';
            }

            if ($surchargePercent > 0) {
                $amount = round($dailyRate * $surchargePercent / 100, 2);
                $totalSurcharge += $amount;
                $surchargeDays++;
                $details[] = [
                    'date' => $dateStr,
                    'reason' => $reason,
                    'surcharge_percent' => $surchargePercent,
                    'surcharge_amount' => $amount,
                ];
            }

            $current->modify('+1 day');
        }

        return [
            'surcharge_amount' => round($totalSurcharge, 2),
            'total_days' => $totalDays,
            'surcharge_days' => $surchargeDays,
            'details' => $details,
        ];
    }

    /**
     * Konfiguration fuer Zuschlaege abrufen
     */
    public function getConfig(?int $instanceId = null): array
    {
        $defaults = [
            'enabled' => true,
            'weekend_enabled' => true,
            'weekend_rate' => self::DEFAULT_WEEKEND_SURCHARGE,
            'holiday_enabled' => true,
            'holiday_rate' => self::DEFAULT_HOLIDAY_SURCHARGE,
        ];

        if (!$instanceId) return $defaults;

        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances', null, [
            'instances_surchargeEnabled',
            'instances_weekendSurchargeRate',
            'instances_holidaySurchargeRate',
        ]);

        if (!$instance || !isset($instance['instances_surchargeEnabled'])) return $defaults;

        return [
            'enabled' => (bool) $instance['instances_surchargeEnabled'],
            'weekend_enabled' => (bool) $instance['instances_surchargeEnabled'],
            'weekend_rate' => floatval($instance['instances_weekendSurchargeRate'] ?? self::DEFAULT_WEEKEND_SURCHARGE),
            'holiday_enabled' => (bool) $instance['instances_surchargeEnabled'],
            'holiday_rate' => floatval($instance['instances_holidaySurchargeRate'] ?? self::DEFAULT_HOLIDAY_SURCHARGE),
        ];
    }

    private function isWeekend(DateTime $date): bool
    {
        $dow = (int) $date->format('N');
        return $dow >= 6; // 6=Sa, 7=So
    }

    private function isHoliday(DateTime $date): bool
    {
        $md = $date->format('m-d');
        if (isset(self::$fixedHolidays[$md])) return true;

        // Bewegliche Feiertage (Ostern-basiert)
        $year = (int) $date->format('Y');
        $easter = $this->getEasterDate($year);
        $movable = [
            $this->addDays($easter, -2),  // Karfreitag
            $this->addDays($easter, 1),   // Ostermontag
            $this->addDays($easter, 39),  // Christi Himmelfahrt
            $this->addDays($easter, 50),  // Pfingstmontag
        ];

        $dateStr = $date->format('Y-m-d');
        foreach ($movable as $holiday) {
            if ($holiday->format('Y-m-d') === $dateStr) return true;
        }

        return false;
    }

    private function getHolidayName(DateTime $date): string
    {
        $md = $date->format('m-d');
        if (isset(self::$fixedHolidays[$md])) return self::$fixedHolidays[$md];

        $year = (int) $date->format('Y');
        $easter = $this->getEasterDate($year);
        $dateStr = $date->format('Y-m-d');

        $movable = [
            [-2, 'Karfreitag'],
            [1, 'Ostermontag'],
            [39, 'Christi Himmelfahrt'],
            [50, 'Pfingstmontag'],
        ];

        foreach ($movable as [$offset, $name]) {
            if ($this->addDays($easter, $offset)->format('Y-m-d') === $dateStr) return $name;
        }

        return 'Feiertag';
    }

    private function getEasterDate(int $year): DateTime
    {
        $days = easter_days($year);
        $date = new DateTime("$year-03-21");
        $date->modify("+{$days} days");
        return $date;
    }

    private function addDays(DateTime $date, int $days): DateTime
    {
        $d = clone $date;
        $d->modify("{$days} days");
        return $d;
    }
}
