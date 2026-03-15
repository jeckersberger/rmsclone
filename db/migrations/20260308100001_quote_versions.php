<?php
/**
 * Angebots-Versionen (Quote Versioning)
 *
 * Erweitert document_exports um Versionierungsfelder:
 * - document_exports_version: Versionsnummer (1, 2, 3...)
 * - document_exports_parentVersionId: Verweis auf das Original-Angebot (erste Version)
 * - document_exports_versionNotes: Aenderungsnotizen fuer die neue Version
 */
use Phinx\Migration\AbstractMigration;

class QuoteVersions extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('document_exports');

        if (!$table->hasColumn('document_exports_version')) {
            $table
                ->addColumn('document_exports_version', 'integer', [
                    'default' => 1,
                    'after' => 'generated_at',
                    'comment' => 'Versionsnummer des Angebots (1, 2, 3...)'
                ])
                ->addColumn('document_exports_parentVersionId', 'integer', [
                    'null' => true,
                    'after' => 'document_exports_version',
                    'comment' => 'Verweis auf das Original-Angebot (erste Version)'
                ])
                ->addColumn('document_exports_versionNotes', 'text', [
                    'null' => true,
                    'after' => 'document_exports_parentVersionId',
                    'comment' => 'Aenderungsnotizen fuer diese Version'
                ])
                ->save();
        }
    }

    public function down()
    {
        $table = $this->table('document_exports');
        foreach (['document_exports_version', 'document_exports_parentVersionId',
                   'document_exports_versionNotes'] as $col) {
            if ($table->hasColumn($col)) {
                $table->removeColumn($col);
            }
        }
        $table->save();
    }
}
