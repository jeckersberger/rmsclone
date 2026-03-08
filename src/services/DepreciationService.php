<?php
/**
 * DepreciationService - AfA-Rechner (Absetzung fuer Abnutzung)
 *
 * Berechnet die steuerliche Abschreibung nach deutschem Steuerrecht:
 * - Lineare AfA (Standard)
 * - Degressive AfA (optional, bis 2024: 25%, ab 2025: 20%)
 * - GWG-Sofortabzug (bis 800 EUR netto)
 * - Sammelposten (250-1.000 EUR)
 */
class DepreciationService
{
    private $db;

    /** GWG-Grenze (Geringwertige Wirtschaftsgueter) */
    const GWG_LIMIT = 800;

    /** Sammelposten-Obergrenze */
    const POOL_LIMIT = 1000;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Berechnet die jaehrliche AfA fuer ein Asset
     *
     * @param float $acquisitionCost Anschaffungskosten (netto)
     * @param int $usefulLifeYears Nutzungsdauer in Jahren (AfA-Tabelle)
     * @param string $acquisitionDate Anschaffungsdatum (YYYY-MM-DD)
     * @param string $method 'linear' oder 'degressive'
     * @return array AfA-Plan mit jaehrlichen Werten
     */
    public function calculate(float $acquisitionCost, int $usefulLifeYears, string $acquisitionDate, string $method = 'linear'): array
    {
        // GWG-Sofortabzug
        if ($acquisitionCost <= self::GWG_LIMIT) {
            return [
                'method' => 'gwg',
                'description' => 'Geringwertiges Wirtschaftsgut - Sofortabzug',
                'acquisition_cost' => $acquisitionCost,
                'total_depreciation' => $acquisitionCost,
                'schedule' => [[
                    'year' => (int) date('Y', strtotime($acquisitionDate)),
                    'depreciation' => $acquisitionCost,
                    'book_value' => 0,
                ]],
            ];
        }

        if ($method === 'degressive') {
            return $this->calculateDegressive($acquisitionCost, $usefulLifeYears, $acquisitionDate);
        }

        return $this->calculateLinear($acquisitionCost, $usefulLifeYears, $acquisitionDate);
    }

    /**
     * Lineare AfA: gleichmaessige Verteilung ueber Nutzungsdauer
     */
    private function calculateLinear(float $cost, int $years, string $startDate): array
    {
        $yearlyAmount = round($cost / $years, 2);
        $startYear = (int) date('Y', strtotime($startDate));
        $startMonth = (int) date('n', strtotime($startDate));

        // Anteilige AfA im ersten Jahr (pro rata temporis)
        $monthsFirstYear = 13 - $startMonth; // inkl. Anschaffungsmonat
        $firstYearAmount = round($yearlyAmount * $monthsFirstYear / 12, 2);

        $schedule = [];
        $bookValue = $cost;

        // Erstes Jahr (anteilig)
        $depr = min($firstYearAmount, $bookValue);
        $bookValue -= $depr;
        $schedule[] = [
            'year' => $startYear,
            'depreciation' => $depr,
            'book_value' => round($bookValue, 2),
        ];

        // Volle Jahre
        for ($y = 1; $y < $years; $y++) {
            $depr = min($yearlyAmount, $bookValue);
            $bookValue -= $depr;
            $schedule[] = [
                'year' => $startYear + $y,
                'depreciation' => round($depr, 2),
                'book_value' => round(max(0, $bookValue), 2),
            ];
        }

        // Restbetrag im letzten Jahr
        if ($bookValue > 0.01) {
            $schedule[] = [
                'year' => $startYear + $years,
                'depreciation' => round($bookValue, 2),
                'book_value' => 0,
            ];
        }

        return [
            'method' => 'linear',
            'description' => "Lineare AfA ueber {$years} Jahre",
            'acquisition_cost' => $cost,
            'yearly_amount' => $yearlyAmount,
            'useful_life_years' => $years,
            'total_depreciation' => $cost,
            'schedule' => $schedule,
        ];
    }

    /**
     * Degressive AfA: jaehrlich sinkender Betrag
     */
    private function calculateDegressive(float $cost, int $years, string $startDate): array
    {
        $linearRate = 1 / $years;
        $degressiveRate = min($linearRate * 2.5, 0.25); // Max 25% bzw. 2,5-facher linearer Satz
        $startYear = (int) date('Y', strtotime($startDate));

        $schedule = [];
        $bookValue = $cost;

        for ($y = 0; $y < $years * 2 && $bookValue > 0.01; $y++) {
            $degressiveAmount = round($bookValue * $degressiveRate, 2);
            $linearAmount = round($cost / $years, 2);

            // Wechsel zu linear wenn linear > degressiv
            if ($linearAmount >= $degressiveAmount) {
                // Restliche Jahre linear
                $remainingYears = $years - $y;
                if ($remainingYears <= 0) $remainingYears = 1;
                $linearRest = round($bookValue / $remainingYears, 2);
                for ($r = 0; $r < $remainingYears && $bookValue > 0.01; $r++) {
                    $depr = min($linearRest, $bookValue);
                    $bookValue -= $depr;
                    $schedule[] = [
                        'year' => $startYear + $y + $r,
                        'depreciation' => round($depr, 2),
                        'book_value' => round(max(0, $bookValue), 2),
                    ];
                }
                break;
            }

            $bookValue -= $degressiveAmount;
            $schedule[] = [
                'year' => $startYear + $y,
                'depreciation' => $degressiveAmount,
                'book_value' => round(max(0, $bookValue), 2),
            ];
        }

        return [
            'method' => 'degressive',
            'description' => "Degressive AfA (" . round($degressiveRate * 100, 1) . "%) mit Wechsel zu linear",
            'acquisition_cost' => $cost,
            'degressive_rate' => $degressiveRate,
            'useful_life_years' => $years,
            'total_depreciation' => $cost,
            'schedule' => $schedule,
        ];
    }

    /**
     * Gaengige Nutzungsdauern nach AfA-Tabelle (Auszug)
     */
    public static function getCommonUsefulLifeYears(): array
    {
        return [
            'Lichtequipment' => 7,
            'Tontechnik' => 7,
            'Videotechnik' => 7,
            'Buehnenelemente' => 10,
            'Traversensysteme' => 10,
            'Zelte/Pagoden' => 8,
            'Transportfahrzeuge' => 6,
            'Anhaenger' => 11,
            'Bueromaterial' => 3,
            'Computer/IT' => 3,
            'Mobiliar' => 13,
            'Werkzeug' => 5,
        ];
    }
}
