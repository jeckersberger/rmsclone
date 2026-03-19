<?php
use Phinx\Migration\AbstractMigration;

class IncomingInvoicesHospitality extends AbstractMigration
{
    public function up()
    {
        // Add hospitality receipt fields to incoming_invoices table
        if ($this->hasTable('incoming_invoices')) {
            $table = $this->table('incoming_invoices');

            if (!$table->hasColumn('is_hospitality')) {
                $table->addColumn('is_hospitality', 'integer', [
                    'limit' => 1,
                    'default' => 0,
                    'comment' => 'Is this a hospitality/meal receipt (Bewirtungsbeleg)?'
                ]);
            }

            if (!$table->hasColumn('hospitality_occasion')) {
                $table->addColumn('hospitality_occasion', 'string', [
                    'limit' => 500,
                    'null' => true,
                    'comment' => 'Anlass der Bewirtung (reason for hospitality - required for tax deduction)'
                ]);
            }

            if (!$table->hasColumn('hospitality_attendees')) {
                $table->addColumn('hospitality_attendees', 'text', [
                    'null' => true,
                    'comment' => 'Teilnehmer mit Firma (attendees and their company - required for tax deduction)'
                ]);
            }

            if (!$table->hasColumn('hospitality_business_relation')) {
                $table->addColumn('hospitality_business_relation', 'string', [
                    'limit' => 500,
                    'null' => true,
                    'comment' => 'Geschäftliche Beziehung (business relationship to attendees)'
                ]);
            }

            if (!$table->hasColumn('hospitality_tip_amount')) {
                $table->addColumn('hospitality_tip_amount', 'decimal', [
                    'precision' => 12,
                    'scale' => 2,
                    'null' => true,
                    'comment' => 'Trinkgeld amount (separate from invoice amount, up to €X)'
                ]);
            }

            $table->update();
        }
    }

    public function down()
    {
        if ($this->hasTable('incoming_invoices')) {
            $table = $this->table('incoming_invoices');

            if ($table->hasColumn('is_hospitality')) {
                $table->removeColumn('is_hospitality');
            }

            if ($table->hasColumn('hospitality_occasion')) {
                $table->removeColumn('hospitality_occasion');
            }

            if ($table->hasColumn('hospitality_attendees')) {
                $table->removeColumn('hospitality_attendees');
            }

            if ($table->hasColumn('hospitality_business_relation')) {
                $table->removeColumn('hospitality_business_relation');
            }

            if ($table->hasColumn('hospitality_tip_amount')) {
                $table->removeColumn('hospitality_tip_amount');
            }

            $table->update();
        }
    }
}
