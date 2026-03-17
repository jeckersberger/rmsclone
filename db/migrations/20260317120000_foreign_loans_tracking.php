<?php
use Phinx\Migration\AbstractMigration;

/**
 * Foreign Loans Tracking
 *
 * Tracking von Ausleihen von Partnerunternehmen (verbunden via Federation/Freundescode).
 * Da wir diese Assets nicht besitzen, können wir keine assetsAssignments erstellen —
 * stattdessen protokollieren wir "Fremd-Ausleihen" in dieser separaten Tabelle.
 *
 * Diese Tabelle ermöglicht:
 * - Tracking von Check-in/Check-out Aktionen für fremde Assets
 * - Lokale Partner (via Freundescode) und Remote-Partner (Federation)
 * - Verknüpfung mit Projekten und Benutzern
 * - Rückverfolgung über RFID-Tags (TID)
 */
class ForeignLoansTracking extends AbstractMigration
{
    public function change()
    {
        // Create foreign_loans table
        if (!$this->hasTable('foreign_loans')) {
            $this->table('foreign_loans', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
                ->addColumn('id', 'integer', [
                    'autoIncrement' => true,
                    'signed' => false,
                ])
                ->addColumn('instances_id', 'integer', [
                    'signed' => false,
                    'null' => false,
                    'comment' => 'Our instance ID (the borrower)',
                ])
                ->addColumn('partner_instance_id', 'integer', [
                    'signed' => false,
                    'null' => true,
                    'comment' => 'Partner instance ID (owner, if local)',
                ])
                ->addColumn('partner_server_name', 'string', [
                    'limit' => 255,
                    'null' => true,
                    'comment' => 'Federation server name (if remote)',
                ])
                ->addColumn('partner_server_url', 'string', [
                    'limit' => 500,
                    'null' => true,
                    'comment' => 'Federation server URL (if remote)',
                ])
                ->addColumn('source', 'enum', [
                    'values' => ['local_partner', 'federation'],
                    'null' => false,
                ])
                ->addColumn('entity_type', 'string', [
                    'limit' => 50,
                    'null' => false,
                    'comment' => 'asset, stock_instance, external',
                ])
                ->addColumn('entity_display_name', 'string', [
                    'limit' => 255,
                    'null' => false,
                ])
                ->addColumn('owner_name', 'string', [
                    'limit' => 255,
                    'null' => true,
                    'comment' => 'Name of the owning company/instance',
                ])
                ->addColumn('rfid_tag', 'string', [
                    'limit' => 255,
                    'null' => true,
                ])
                ->addColumn('rfid_tid', 'string', [
                    'limit' => 255,
                    'null' => true,
                ])
                ->addColumn('action', 'enum', [
                    'values' => ['checkout', 'checkin', 'locate', 'inventory'],
                    'null' => false,
                ])
                ->addColumn('projects_id', 'integer', [
                    'signed' => false,
                    'null' => true,
                    'comment' => 'Project this item was checked out for',
                ])
                ->addColumn('users_userid', 'integer', [
                    'signed' => false,
                    'null' => false,
                    'comment' => 'User who performed the action',
                ])
                ->addColumn('scanned_at', 'datetime', [
                    'default' => 'CURRENT_TIMESTAMP',
                    'null' => false,
                ])
                ->addColumn('returned_at', 'datetime', [
                    'null' => true,
                    'comment' => 'When item was returned (checkin)',
                ])
                ->addColumn('notes', 'text', [
                    'null' => true,
                    'limit' => 65535,
                ])
                ->addIndex(['instances_id'], ['name' => 'idx_foreign_loans_instance'])
                ->addIndex(['partner_instance_id'], ['name' => 'idx_foreign_loans_partner'])
                ->addIndex(['action'], ['name' => 'idx_foreign_loans_action'])
                ->addIndex(['rfid_tid'], ['name' => 'idx_foreign_loans_tid'])
                ->addIndex(['instances_id', 'action', 'returned_at'], ['name' => 'idx_foreign_loans_active'])
                ->create();
        }
    }
}
