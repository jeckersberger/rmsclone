<?php
/**
 * Foreign Keys fuer alle neuen Tabellen
 *
 * Stellt referentielle Integritaet sicher fuer:
 * - dsgvo_log, partner_links, partner_requests, partner_request_items
 * - document_lifecycle, document_status_history, dunning_history
 * - client_contacts, client_tag_assignments, euer_bookings
 * - asset_availability_blocks, datev_exports, rate_limits
 */
use Phinx\Migration\AbstractMigration;

class AddForeignKeys extends AbstractMigration
{
    public function up()
    {
        // ── dsgvo_log ──
        if ($this->hasTable('dsgvo_log')) {
            $this->table('dsgvo_log')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('clients_id', 'clients', 'clients_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('performed_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── document_lifecycle ──
        if ($this->hasTable('document_lifecycle')) {
            $this->table('document_lifecycle')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('projects_id', 'projects', 'projects_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── document_status_history ──
        if ($this->hasTable('document_status_history')) {
            $this->table('document_status_history')
                ->addForeignKey('document_lifecycle_id', 'document_lifecycle', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('changed_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── dunning_levels ──
        if ($this->hasTable('dunning_levels')) {
            $this->table('dunning_levels')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }

        // ── dunning_history ──
        if ($this->hasTable('dunning_history')) {
            $this->table('dunning_history')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('document_lifecycle_id', 'document_lifecycle', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('dunning_level_id', 'dunning_levels', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── client_contacts ──
        if ($this->hasTable('client_contacts')) {
            $this->table('client_contacts')
                ->addForeignKey('clients_id', 'clients', 'clients_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }

        // ── client_tag_assignments ──
        if ($this->hasTable('client_tag_assignments')) {
            $this->table('client_tag_assignments')
                ->addForeignKey('clients_id', 'clients', 'clients_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('client_tags_id', 'client_tags', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }

        // ── client_tags ──
        if ($this->hasTable('client_tags')) {
            $this->table('client_tags')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }

        // ── euer_bookings ──
        if ($this->hasTable('euer_bookings')) {
            $this->table('euer_bookings')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('euer_categories_id', 'euer_categories', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── euer_categories ──
        if ($this->hasTable('euer_categories')) {
            $this->table('euer_categories')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }

        // ── asset_availability_blocks ──
        if ($this->hasTable('asset_availability_blocks')) {
            $this->table('asset_availability_blocks')
                ->addForeignKey('assets_id', 'assets', 'assets_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── datev_exports ──
        if ($this->hasTable('datev_exports')) {
            $this->table('datev_exports')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── datev_account_mapping ──
        if ($this->hasTable('datev_account_mapping')) {
            $this->table('datev_account_mapping')
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }

        // ── partner_links ──
        if ($this->hasTable('partner_links')) {
            $this->table('partner_links')
                ->addForeignKey('instance_a_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('instance_b_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('invited_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── partner_requests ──
        if ($this->hasTable('partner_requests')) {
            $this->table('partner_requests')
                ->addForeignKey('from_instance_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('to_instance_id', 'instances', 'instances_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('projects_id', 'projects', 'projects_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // ── partner_request_items ──
        if ($this->hasTable('partner_request_items')) {
            $this->table('partner_request_items')
                ->addForeignKey('partner_requests_id', 'partner_requests', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('assetTypes_id', 'assetTypes', 'assetTypes_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->update();
        }
    }

    public function down()
    {
        // Drop foreign keys (Phinx handles this automatically when dropping FKs)
        $tables = [
            'dsgvo_log', 'document_lifecycle', 'document_status_history',
            'dunning_levels', 'dunning_history', 'client_contacts',
            'client_tag_assignments', 'client_tags', 'euer_bookings',
            'euer_categories', 'asset_availability_blocks', 'datev_exports',
            'datev_account_mapping', 'partner_links', 'partner_requests',
            'partner_request_items',
        ];
        foreach ($tables as $t) {
            if ($this->hasTable($t)) {
                $table = $this->table($t);
                foreach ($table->getForeignKeys() as $fk) {
                    $table->dropForeignKey($fk->getColumns());
                }
                $table->update();
            }
        }
    }
}
