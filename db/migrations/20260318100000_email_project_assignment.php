<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * E-Mail-zu-Projekt-Zuordnung: Tracking von Zuweisungen
 * Fügt Metadaten zur Verfolgung hinzu, wer eine E-Mail wann einem Projekt zugeordnet hat.
 */
final class EmailProjectAssignment extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('emailReceived');
        if (!$table->hasColumn('assigned_by')) {
            $table
                ->addColumn('assigned_by', 'integer', [
                    'null' => true,
                    'after' => 'projects_id',
                    'comment' => 'User der die E-Mail zugeordnet hat',
                ])
                ->addColumn('assigned_at', 'datetime', [
                    'null' => true,
                    'after' => 'assigned_by',
                    'comment' => 'Zeitpunkt der Zuordnung',
                ])
                ->addIndex(['assigned_at'])
                ->update();
        }
    }
}
