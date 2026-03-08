<?php
/**
 * Kundenkontakte, Kategorien und Kreditlimit
 *
 * - client_contacts: Mehrere Ansprechpartner pro Kunde
 * - client_categories: Kundenkategorien / Tags pro Instanz
 * - client_category_assignments: Zuordnung Kategorie <-> Kunde
 * - clients_creditLimit / clients_currentBalance: Kreditlimit-Verwaltung
 */
use Phinx\Migration\AbstractMigration;

class ClientContactsCategories extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) Client Contacts (Ansprechpartner) ═══════
        if (!$this->hasTable('client_contacts')) {
            $this->table('client_contacts', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('clients_id', 'integer')
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('position', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('phone', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('mobile', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('is_primary', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['clients_id'])
                ->addForeignKey('clients_id', 'clients', 'clients_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // ═══════ 2) Client Categories (Kundenkategorien / Tags) ═══════
        if (!$this->hasTable('client_categories')) {
            $this->table('client_categories', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('color', 'string', ['limit' => 7, 'default' => '#6c757d'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // ═══════ 3) Client Category Assignments ═══════
        if (!$this->hasTable('client_category_assignments')) {
            $this->table('client_category_assignments', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('clients_id', 'integer')
                ->addColumn('category_id', 'integer')
                ->addIndex(['clients_id', 'category_id'], ['unique' => true])
                ->addForeignKey('clients_id', 'clients', 'clients_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('category_id', 'client_categories', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // ═══════ 4) Kreditlimit + aktueller Saldo auf clients ═══════
        $clientsTable = $this->table('clients');
        if (!$clientsTable->hasColumn('clients_creditLimit')) {
            $clientsTable->addColumn('clients_creditLimit', 'decimal', [
                'precision' => 10, 'scale' => 2, 'null' => true, 'after' => 'clients_notes'
            ]);
        }
        if (!$clientsTable->hasColumn('clients_currentBalance')) {
            $clientsTable->addColumn('clients_currentBalance', 'decimal', [
                'precision' => 10, 'scale' => 2, 'default' => 0, 'after' => 'clients_creditLimit'
            ]);
        }
        $clientsTable->update();
    }

    public function down()
    {
        if ($this->hasTable('client_category_assignments')) {
            $this->table('client_category_assignments')->drop()->save();
        }
        if ($this->hasTable('client_categories')) {
            $this->table('client_categories')->drop()->save();
        }
        if ($this->hasTable('client_contacts')) {
            $this->table('client_contacts')->drop()->save();
        }

        $clientsTable = $this->table('clients');
        if ($clientsTable->hasColumn('clients_creditLimit')) {
            $clientsTable->removeColumn('clients_creditLimit');
        }
        if ($clientsTable->hasColumn('clients_currentBalance')) {
            $clientsTable->removeColumn('clients_currentBalance');
        }
        $clientsTable->update();
    }
}
