<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Extended AI Features: Asset Allocation, Client Risk, Email Reply,
 * Finance Forecast, Document Check, Predictive Maintenance,
 * Crew Optimization, Duplicate Detection.
 */
final class AiFeaturesExtended extends AbstractMigration
{
    public function change(): void
    {
        $instances = $this->table('instances');

        $newFeatures = [
            'instances_aiFeatureAssetAllocation' => 'Feature: Smarte Asset-Zuteilung',
            'instances_aiFeatureClientRisk' => 'Feature: Kunden-Risikobewertung',
            'instances_aiFeatureEmailReply' => 'Feature: E-Mail Auto-Reply Vorschlaege',
            'instances_aiFeatureFinanceForecast' => 'Feature: Finanz-Prognosen',
            'instances_aiFeatureDocumentCheck' => 'Feature: Dokument-Qualitaetscheck',
            'instances_aiFeaturePredictMaintenance' => 'Feature: Predictive Maintenance',
            'instances_aiFeatureCrewOptimize' => 'Feature: Crew-Optimierung',
            'instances_aiFeatureDuplicateDetect' => 'Feature: Duplikat-Erkennung',
        ];

        foreach ($newFeatures as $col => $comment) {
            if (!$instances->hasColumn($col)) {
                $instances->addColumn($col, 'boolean', [
                    'default' => 1,
                    'comment' => $comment,
                ]);
            }
        }

        $instances->update();
    }
}
