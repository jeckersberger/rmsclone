<?php
/**
 * Migration: Reverse-Charge-Felder fuer Kunden + Mahnbrief-Felder in dunning_history
 *
 * - clients_isEU:            Kennzeichnung als EU-Kunde
 * - clients_reverseCharge:   Reverse-Charge-Verfahren aktiviert
 * - dunning_history.letter_s3files_id:  Verweis auf generiertes PDF
 * - dunning_history.letter_sent_at:     Zeitpunkt des Versands
 */
use Phinx\Migration\AbstractMigration;

class ReverseChargeAndDunningLetters extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) Reverse-Charge-Felder in clients ═══════
        if ($this->hasTable('clients')) {
            $table = $this->table('clients');
            if (!$table->hasColumn('clients_isEU')) {
                $table->addColumn('clients_isEU', 'boolean', [
                    'default' => 0,
                    'null'    => false,
                    'after'   => 'clients_vatId',
                    'comment' => 'Kunde ist EU-Ausland (nicht Inland)',
                ]);
            }
            if (!$table->hasColumn('clients_reverseCharge')) {
                $table->addColumn('clients_reverseCharge', 'boolean', [
                    'default' => 0,
                    'null'    => false,
                    'after'   => 'clients_isEU',
                    'comment' => 'Reverse-Charge-Verfahren anwenden (EU B2B, Art. 196 MwSt-RL)',
                ]);
            }
            $table->update();
        }

        // ═══════ 2) Mahnbrief-Felder in dunning_history ═══════
        if ($this->hasTable('dunning_history')) {
            $table = $this->table('dunning_history');
            if (!$table->hasColumn('letter_s3files_id')) {
                $table->addColumn('letter_s3files_id', 'integer', [
                    'null'    => true,
                    'after'   => 's3files_id',
                    'comment' => 'Verweis auf generiertes Mahnbrief-PDF in s3files',
                ]);
            }
            if (!$table->hasColumn('letter_sent_at')) {
                $table->addColumn('letter_sent_at', 'datetime', [
                    'null'    => true,
                    'after'   => 'letter_s3files_id',
                    'comment' => 'Zeitpunkt des Mahnbrief-Versands',
                ]);
            }
            $table->update();
        }
    }

    public function down()
    {
        if ($this->hasTable('clients')) {
            $table = $this->table('clients');
            foreach (['clients_isEU', 'clients_reverseCharge'] as $col) {
                if ($table->hasColumn($col)) $table->removeColumn($col);
            }
            $table->update();
        }

        if ($this->hasTable('dunning_history')) {
            $table = $this->table('dunning_history');
            foreach (['letter_s3files_id', 'letter_sent_at'] as $col) {
                if ($table->hasColumn($col)) $table->removeColumn($col);
            }
            $table->update();
        }
    }
}
