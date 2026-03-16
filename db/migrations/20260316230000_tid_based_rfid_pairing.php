<?php
use Phinx\Migration\AbstractMigration;

/**
 * TID-basiertes RFID-Pairing
 *
 * Umstellung von "EPC auf Tag schreiben" auf "TID vom Tag lesen und in DB speichern".
 * UHF-RFID-Tags haben ab Werk eine eindeutige TID (Tag Identifier), die nicht
 * verändert werden kann. Das System liest diese TID und verknüpft sie in der
 * Datenbank mit dem jeweiligen Asset/Stock/External Item.
 *
 * Vorteile:
 * - Kein RFID-Writer-Gerät nötig (nur Reader)
 * - TID ist fälschungssicher (nicht überschreibbar)
 * - Einfacherer Workflow: Scannen → Zuordnen → Fertig
 *
 * Die bisherigen rfid_tag/asset_definableFields_1 Felder bleiben für
 * Abwärtskompatibilität und gespeicherte EPC-Werte erhalten.
 */
class TidBasedRfidPairing extends AbstractMigration
{
    public function change()
    {
        // Add dedicated TID column to assets
        if (!$this->table('assets')->hasColumn('assets_rfidTid')) {
            $this->table('assets')
                ->addColumn('assets_rfidTid', 'string', [
                    'limit' => 64,
                    'null' => true,
                    'after' => 'asset_definableFields_10',
                    'comment' => 'UHF-RFID Tag Identifier (TID) — unique hardware ID from chip manufacturer',
                ])
                ->addIndex(['assets_rfidTid'], ['unique' => true, 'name' => 'idx_assets_rfidTid'])
                ->update();
        }

        // Add dedicated TID column to stock_instances
        if (!$this->table('stock_instances')->hasColumn('rfid_tid')) {
            $this->table('stock_instances')
                ->addColumn('rfid_tid', 'string', [
                    'limit' => 64,
                    'null' => true,
                    'after' => 'rfid_tag',
                    'comment' => 'UHF-RFID Tag Identifier (TID) — unique hardware ID from chip manufacturer',
                ])
                ->addIndex(['rfid_tid'], ['unique' => true, 'name' => 'idx_stock_rfid_tid'])
                ->update();
        }

        // Add dedicated TID column to external_items
        if (!$this->table('external_items')->hasColumn('rfid_tid')) {
            $this->table('external_items')
                ->addColumn('rfid_tid', 'string', [
                    'limit' => 64,
                    'null' => true,
                    'after' => 'rfid_tag',
                    'comment' => 'UHF-RFID Tag Identifier (TID) — unique hardware ID from chip manufacturer',
                ])
                ->addIndex(['rfid_tid'], ['unique' => true, 'name' => 'idx_external_rfid_tid'])
                ->update();
        }
    }
}
