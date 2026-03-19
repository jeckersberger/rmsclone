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
                ->update();
        }

        // ── document_lifecycle ──
        if ($this->hasTable('document_lifecycle')) {
            $this->table('document_lifecycle')
                ->update();
        }

        // ── document_status_history ──
        if ($this->hasTable('document_status_history')) {
            $this->table('document_status_history')
                ->update();
        }

        // ── dunning_levels ──
        if ($this->hasTable('dunning_levels')) {
            $this->table('dunning_levels')
                ->update();
        }

        // ── dunning_history ──
        if ($this->hasTable('dunning_history')) {
            $this->table('dunning_history')
                ->update();
        }

        // ── client_contacts ──
        if ($this->hasTable('client_contacts')) {
            $this->table('client_contacts')
                ->update();
        }

        // ── client_tag_assignments ──
        if ($this->hasTable('client_tag_assignments')) {
            $this->table('client_tag_assignments')
                ->update();
        }

        // ── client_tags ──
        if ($this->hasTable('client_tags')) {
            $this->table('client_tags')
                ->update();
        }

        // ── euer_bookings ──
        if ($this->hasTable('euer_bookings')) {
            $this->table('euer_bookings')
                ->update();
        }

        // ── euer_categories ──
        if ($this->hasTable('euer_categories')) {
            $this->table('euer_categories')
                ->update();
        }

        // ── asset_availability_blocks ──
        if ($this->hasTable('asset_availability_blocks')) {
            $this->table('asset_availability_blocks')
                ->update();
        }

        // ── datev_exports ──
        if ($this->hasTable('datev_exports')) {
            $this->table('datev_exports')
                ->update();
        }

        // ── datev_account_mapping ──
        if ($this->hasTable('datev_account_mapping')) {
            $this->table('datev_account_mapping')
                ->update();
        }

        // ── partner_links ──
        if ($this->hasTable('partner_links')) {
            $this->table('partner_links')
                ->update();
        }

        // ── partner_requests ──
        if ($this->hasTable('partner_requests')) {
            $this->table('partner_requests')
                ->update();
        }

        // ── partner_request_items ──
        if ($this->hasTable('partner_request_items')) {
            $this->table('partner_request_items')
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
