<?php
/**
 * BWA, Kassenbuch und UStVA Tabellen
 *
 * - bwa_reports: Gespeicherte BWA-Auswertungen (monatlich/jaehrlich)
 * - kassenbuch_entries: Kassenbuch-Eintraege
 * - ustva_reports: Umsatzsteuer-Voranmeldungen
 */
use Phinx\Migration\AbstractMigration;

class BwaKassenbuchUstva extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) BWA Reports ═══════
        if (!$this->hasTable('bwa_reports')) {
            $this->table('bwa_reports', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('year', 'integer')
                ->addColumn('month', 'integer', ['null' => true, 'comment' => 'NULL = Jahresuebersicht'])
                ->addColumn('generated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('data', 'json', ['comment' => 'Vollstaendige BWA-Daten als JSON'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'year', 'month'], ['unique' => true])
                ->addIndex(['instances_id'])
                ->create();
        }

        // ═══════ 2) Kassenbuch Entries ═══════
        if (!$this->hasTable('kassenbuch_entries')) {
            $this->table('kassenbuch_entries', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('entry_date', 'date')
                ->addColumn('description', 'text')
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
                ->addColumn('type', 'enum', ['values' => ['einnahme', 'ausgabe']])
                ->addColumn('category', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('receipt_number', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('payment_method', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'entry_date'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // ═══════ 3) UStVA Reports ═══════
        if (!$this->hasTable('ustva_reports')) {
            $this->table('ustva_reports', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('year', 'integer')
                ->addColumn('month', 'integer')
                ->addColumn('tax_base_19', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('tax_amount_19', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('tax_base_7', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('tax_amount_7', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('input_tax', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('prepayment', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('status', 'enum', ['values' => ['draft', 'submitted'], 'default' => 'draft'])
                ->addColumn('elster_reference', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('submitted_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'year', 'month'], ['unique' => true])
                ->addIndex(['instances_id'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('ustva_reports')->drop()->save();
        $this->table('kassenbuch_entries')->drop()->save();
        $this->table('bwa_reports')->drop()->save();
    }
}
