<?php
/**
 * ProjectRepo - Loads project data with financial info for document rendering.
 */
class ProjectRepo {
    public static function getWithFinance($db, int $instanceId, int $projectId): array {
        $db->where('projects.instances_id', $instanceId);
        $db->where('projects.projects_deleted', 0);
        $db->where('projects.projects_id', $projectId);
        $db->join('projectsTypes', 'projects.projectsTypes_id=projectsTypes.projectsTypes_id', 'LEFT');
        $db->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
        $db->join('users', 'projects.projects_manager=users.users_userid', 'LEFT');
        $project = $db->getOne('projects', [
            'projects.*', 'projectsTypes.*',
            'clients.clients_id', 'clients.clients_name', 'clients.clients_address',
            'clients.clients_email', 'clients.clients_phone', 'clients.clients_website',
            'users.users_name1', 'users.users_name2', 'users.users_email'
        ]);
        if (!$project) throw new \RuntimeException("Projekt {$projectId} nicht gefunden.");

        // Lade Assets mit Kategorien und Preisen
        $db->where('projects_id', $projectId);
        $db->where('assetsAssignments.assetsAssignments_deleted', 0);
        $db->join('assets', 'assetsAssignments.assets_id=assets.assets_id', 'LEFT');
        $db->join('assetTypes', 'assets.assetTypes_id=assetTypes.assetTypes_id', 'LEFT');
        $db->join('manufacturers', 'manufacturers.manufacturers_id=assetTypes.manufacturers_id', 'LEFT');
        $db->join('assetCategories', 'assetTypes.assetCategories_id=assetCategories.assetCategories_id', 'LEFT');
        $db->orderBy('assetCategories.assetCategories_rank', 'ASC');
        $db->orderBy('assetTypes.assetTypes_id', 'ASC');
        $db->where('assets.assets_deleted', 0);
        $rawAssets = $db->get('assetsAssignments', null, [
            'assetsAssignments.*', 'assets.*', 'assetTypes.*',
            'manufacturers.manufacturers_name',
            'assetCategories.assetCategories_name', 'assetCategories.assetCategories_rank',
            'assetCategories.assetCategories_fontAwesome'
        ]);

        // Berechne Tagespreise fuer Projektdauer
        require_once __DIR__ . '/../common/libs/bCMS/projectFinance.php';
        $pf = new \projectFinance();
        $priceMaths = $pf->durationMaths($projectId);

        // Gruppiere Assets nach Typ und berechne Preise
        $assets = [];
        foreach ($rawAssets ?: [] as $a) {
            $dayRate = (int)($a['assets_dayRate'] ?? $a['assetTypes_dayRate'] ?? 0);
            $weekRate = (int)($a['assets_weekRate'] ?? $a['assetTypes_weekRate'] ?? 0);

            if ($a['assetsAssignments_customPrice'] !== null) {
                $totalNet = (float)$a['assetsAssignments_customPrice'] / 100;
            } else {
                $totalNet = ($dayRate * $priceMaths['days'] + $weekRate * $priceMaths['weeks']) / 100;
            }

            $discount = (float)($a['assetsAssignments_discount'] ?? 0);
            if ($discount > 0) {
                $totalNet = $totalNet * (1 - $discount / 100);
            }

            $name = '';
            if (!empty($a['manufacturers_name']) && $a['manufacturers_id'] != 1) {
                $name = $a['manufacturers_name'] . ' ';
            }
            $name .= $a['assetTypes_name'] ?? 'Unbekannt';

            $assets[] = [
                'name' => $name,
                'qty' => 1,
                'unit' => 'Tag',
                'price_day_net' => round($dayRate / 100, 2),
                'total_net' => round($totalNet, 2),
                'note' => $a['assetsAssignments_comment'] ?? null,
                'assetCategories_name' => $a['assetCategories_name'] ?? 'Sonstiges',
                'category_rank' => (int)($a['assetCategories_rank'] ?? 999),
                'category_icon' => $a['assetCategories_fontAwesome'] ?? '',
                'groups' => [],
            ];
        }

        // Lade Zahlungen als "Extras"
        $db->where('payments.payments_deleted', 0);
        $db->where('payments.projects_id', $projectId);
        $payments = $db->get('payments');
        $extras = [];
        foreach ($payments ?: [] as $p) {
            // Nur Staff und SubHire als Extra-Positionen
            if ((int)$p['payments_type'] === 4 || (int)$p['payments_type'] === 3) {
                $amount = ((float)$p['payments_amount'] / 100) * (int)($p['payments_quantity'] ?? 1);
                $extras[] = [
                    'name' => $p['payments_comment'] ?: ((int)$p['payments_type'] === 4 ? 'Personal' : 'Fremdmiete'),
                    'qty' => (int)($p['payments_quantity'] ?? 1),
                    'unit' => 'Stk',
                    'total_net' => round($amount, 2),
                    'note' => $p['payments_supplier'] ?? null,
                ];
            }
        }

        $project['assets'] = $assets;
        $project['extras'] = $extras;

        // Map date fields
        $project['projects_dateStart'] = $project['projects_dates_deliver_start'] ?? $project['projects_dates_use_start'] ?? date('Y-m-d');
        $project['projects_dateEnd'] = $project['projects_dates_deliver_end'] ?? $project['projects_dates_use_end'] ?? date('Y-m-d');

        return $project;
    }
}
