<?php
/**
 * Abschlagsrechnungen (Teilrechnungen)
 *
 * Erweitert document_lifecycle um Felder fuer Abschlagsrechnungen:
 * - partial_invoice_number: Laufende Nummer der Abschlagsrechnung (1, 2, 3...)
 * - partial_invoice_total: Gesamtanzahl geplanter Abschlagsrechnungen
 * - partial_invoice_pct: Prozentsatz der Gesamtsumme fuer diese Abschlagsrechnung
 * - is_final_invoice: Flag fuer Schlussrechnung (verrechnet alle Abschlagsrechnungen)
 * - parent_project_amount: Gesamtbetrag des Projekts zum Zeitpunkt der Erstellung
 *
 * Dokumenttypen:
 * - partial_invoice: Abschlagsrechnung (Teilzahlung vor Projektende)
 * - invoice (is_final_invoice=1): Schlussrechnung (verrechnet Abschlaege)
 */
use Phinx\Migration\AbstractMigration;

class PartialInvoices extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('document_lifecycle');

        if (!$table->hasColumn('partial_invoice_number')) {
            $table
                ->addColumn('partial_invoice_number', 'integer', [
                    'null' => true,
                    'after' => 'notes',
                    'comment' => 'Laufende Nr. der Abschlagsrechnung (1, 2, 3...)'
                ])
                ->addColumn('partial_invoice_total', 'integer', [
                    'null' => true,
                    'after' => 'partial_invoice_number',
                    'comment' => 'Geplante Gesamtanzahl Abschlagsrechnungen'
                ])
                ->addColumn('partial_invoice_pct', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'null' => true,
                    'after' => 'partial_invoice_total',
                    'comment' => 'Prozentsatz der Gesamtsumme (z.B. 30.00)'
                ])
                ->addColumn('is_final_invoice', 'boolean', [
                    'default' => false,
                    'after' => 'partial_invoice_pct',
                    'comment' => 'Schlussrechnung die alle Abschlaege verrechnet'
                ])
                ->addColumn('parent_project_amount', 'decimal', [
                    'precision' => 12,
                    'scale' => 2,
                    'null' => true,
                    'after' => 'is_final_invoice',
                    'comment' => 'Gesamtbetrag des Projekts bei Erstellung'
                ])
                ->save();
        }
    }

    public function down()
    {
        $table = $this->table('document_lifecycle');
        foreach (['partial_invoice_number', 'partial_invoice_total', 'partial_invoice_pct',
                   'is_final_invoice', 'parent_project_amount'] as $col) {
            if ($table->hasColumn($col)) {
                $table->removeColumn($col);
            }
        }
        $table->save();
    }
}
