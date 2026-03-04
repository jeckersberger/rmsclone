<?php
/**
 * Angebots-Vorlagen + Textbausteine
 *
 * Wiederverwendbare Textbausteine fuer Angebote, Rechnungen etc.
 * Koennen in Dokument-Templates per Platzhalter eingefuegt werden.
 */
use Phinx\Migration\AbstractMigration;

class QuoteTemplatesAndTextBlocks extends AbstractMigration
{
    public function up()
    {
        // Textbausteine (wiederverwendbare Absaetze)
        if (!$this->hasTable('text_blocks')) {
            $this->table('text_blocks', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('category', 'string', ['limit' => 50, 'comment' => 'greeting, scope, terms, closing, note, custom'])
                ->addColumn('title', 'string', ['limit' => 150, 'comment' => 'Interner Name des Bausteins'])
                ->addColumn('content', 'text', ['comment' => 'Inhalt mit Platzhaltern: {client_name}, {project_name}, {date}'])
                ->addColumn('doc_types', 'string', ['limit' => 100, 'default' => 'quote,invoice', 'comment' => 'Komma-getrennte Dokumenttypen'])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('is_default', 'boolean', ['default' => false, 'comment' => 'Automatisch in neue Dokumente einfuegen'])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['instances_id', 'category'])
                ->addIndex(['instances_id', 'doc_types'])
                ->addForeignKey('instances_id', 'instances', 'instances_id', ['delete' => 'CASCADE'])
                ->addForeignKey('created_by', 'users', 'users_userid', ['delete' => 'SET_NULL'])
                ->create();
        }

        // Default-Textbausteine fuer alle Instances
        $this->execute("
            INSERT IGNORE INTO text_blocks (instances_id, category, title, content, doc_types, sort_order, is_default, created_by)
            SELECT
                instances_id,
                'greeting',
                'Standard-Anrede',
                'Sehr geehrte Damen und Herren,\n\nvielen Dank fuer Ihre Anfrage. Gerne unterbreiten wir Ihnen folgendes Angebot:',
                'quote',
                1,
                1,
                1
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO text_blocks (instances_id, category, title, content, doc_types, sort_order, is_default, created_by)
            SELECT
                instances_id,
                'scope',
                'Leistungsumfang Standard',
                'Der Leistungsumfang umfasst die Bereitstellung und den Transport des oben genannten Equipments fuer den vereinbarten Zeitraum. Auf-/Abbau und technische Betreuung koennen auf Anfrage zusaetzlich gebucht werden.',
                'quote',
                2,
                1,
                1
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO text_blocks (instances_id, category, title, content, doc_types, sort_order, is_default, created_by)
            SELECT
                instances_id,
                'terms',
                'Standard-AGB-Verweis',
                'Es gelten unsere allgemeinen Geschaeftsbedingungen. Dieses Angebot ist 14 Tage gueltig.',
                'quote',
                3,
                1,
                1
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO text_blocks (instances_id, category, title, content, doc_types, sort_order, is_default, created_by)
            SELECT
                instances_id,
                'closing',
                'Standard-Schluss',
                'Wir freuen uns auf Ihre Rueckmeldung und stehen Ihnen fuer Rueckfragen gerne zur Verfuegung.\n\nMit freundlichen Gruessen',
                'quote',
                4,
                1,
                1
            FROM instances WHERE instances_deleted = 0
        ");
    }

    public function down()
    {
        $this->table('text_blocks')->drop()->save();
    }
}
