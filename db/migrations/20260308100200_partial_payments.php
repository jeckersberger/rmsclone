<?php
/**
 * Teilzahlungs-Tracking und Mahnsperre
 *
 * - invoice_payments: Einzelne Zahlungseingaenge pro Rechnung
 * - document_exports: paidAmount und paymentStatus
 * - dunning_history: paused-Flag und pauseReason
 */
use Phinx\Migration\AbstractMigration;

class PartialPayments extends AbstractMigration
{
    public function up()
    {
        // invoice_payments table
        if (!$this->hasTable('invoice_payments')) {
            $this->table('invoice_payments')
                ->addColumn('document_exports_id', 'integer', [
                    'signed' => false,
                    'comment' => 'FK zu document_exports'
                ])
                ->addColumn('amount', 'decimal', [
                    'precision' => 10,
                    'scale' => 2,
                    'comment' => 'Zahlungsbetrag'
                ])
                ->addColumn('payment_date', 'date', [
                    'comment' => 'Datum der Zahlung'
                ])
                ->addColumn('payment_method', 'enum', [
                    'values' => ['bank_transfer', 'cash', 'sepa', 'paypal', 'other'],
                    'default' => 'bank_transfer',
                    'comment' => 'Zahlungsart'
                ])
                ->addColumn('reference', 'string', [
                    'limit' => 255,
                    'null' => true,
                    'default' => null,
                    'comment' => 'Zahlungsreferenz / Verwendungszweck'
                ])
                ->addColumn('notes', 'text', [
                    'null' => true,
                    'default' => null,
                    'comment' => 'Bemerkungen zur Zahlung'
                ])
                ->addColumn('created_at', 'datetime', [
                    'default' => 'CURRENT_TIMESTAMP',
                    'comment' => 'Erstellungszeitpunkt'
                ])
                ->addIndex('document_exports_id')
                ->addForeignKey('document_exports_id', 'document_exports', 'document_exports_id', [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE'
                ])
                ->create();
        }

        // Add columns to document_exports
        $docExports = $this->table('document_exports');
        if (!$docExports->hasColumn('document_exports_paidAmount')) {
            $docExports->addColumn('document_exports_paidAmount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => 0,
                'comment' => 'Summe aller bisherigen Zahlungen'
            ]);
        }
        if (!$docExports->hasColumn('document_exports_paymentStatus')) {
            $docExports->addColumn('document_exports_paymentStatus', 'enum', [
                'values' => ['unpaid', 'partial', 'paid'],
                'default' => 'unpaid',
                'comment' => 'Zahlungsstatus: unbezahlt / teilweise / bezahlt'
            ]);
        }
        $docExports->save();

        // Add columns to dunning_history
        $dunning = $this->table('dunning_history');
        if (!$dunning->hasColumn('dunning_history_paused')) {
            $dunning->addColumn('dunning_history_paused', 'boolean', [
                'default' => false,
                'comment' => 'Mahnverfahren pausiert (z.B. bei Teilzahlung)'
            ]);
        }
        if (!$dunning->hasColumn('dunning_history_pauseReason')) {
            $dunning->addColumn('dunning_history_pauseReason', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'comment' => 'Grund der Mahnsperre'
            ]);
        }
        $dunning->save();
    }

    public function down()
    {
        if ($this->hasTable('invoice_payments')) {
            $this->table('invoice_payments')->drop()->save();
        }

        $docExports = $this->table('document_exports');
        if ($docExports->hasColumn('document_exports_paidAmount')) {
            $docExports->removeColumn('document_exports_paidAmount');
        }
        if ($docExports->hasColumn('document_exports_paymentStatus')) {
            $docExports->removeColumn('document_exports_paymentStatus');
        }
        $docExports->save();

        $dunning = $this->table('dunning_history');
        if ($dunning->hasColumn('dunning_history_paused')) {
            $dunning->removeColumn('dunning_history_paused');
        }
        if ($dunning->hasColumn('dunning_history_pauseReason')) {
            $dunning->removeColumn('dunning_history_pauseReason');
        }
        $dunning->save();
    }
}
