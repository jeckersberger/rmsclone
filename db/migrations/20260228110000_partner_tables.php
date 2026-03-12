<?php
/**
 * Partner-Kooperation Tabellen
 *
 * Ermoeglicht die Zusammenarbeit zwischen zwei Kleingewerben:
 * - partner_links: Partnerschaften zwischen Instances
 * - partner_price_rules: Sonderpreise fuer Partnerverleih
 * - partner_requests: Equipment-Anfragen zwischen Partnern
 * - partner_request_items: Einzelne Positionen einer Anfrage
 */
use Phinx\Migration\AbstractMigration;

class PartnerTables extends AbstractMigration
{
    public function up()
    {
        // Partner-Code Feld an instances anfuegen
        if ($this->hasTable('instances')) {
            $table = $this->table('instances');
            if (!$table->hasColumn('instances_partnerCode')) {
                $table->addColumn('instances_partnerCode', 'string', ['limit' => 20, 'null' => true])
                      ->addIndex(['instances_partnerCode'], ['unique' => true])
                      ->update();
            }
        }

        // Partnerschaften zwischen zwei Instances
        if (!$this->hasTable('partner_links')) {
            $this->table('partner_links', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instance_a_id', 'integer', ['comment' => 'Einladende Instance'])
                ->addColumn('instance_b_id', 'integer', ['comment' => 'Eingeladene Instance'])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => 'pending, active, rejected'])
                ->addColumn('invited_by', 'integer', ['comment' => 'users_userid des Einladenden'])
                ->addColumn('accepted_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['instance_a_id', 'instance_b_id'], ['unique' => true])
                ->addIndex(['status'])
                ->create();
        }

        // Sonderpreise fuer Partnerverleih
        if (!$this->hasTable('partner_price_rules')) {
            $this->table('partner_price_rules', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('owner_instance_id', 'integer', ['comment' => 'Instance die das Equipment besitzt'])
                ->addColumn('partner_instance_id', 'integer', ['comment' => 'Instance die den Sonderpreis bekommt'])
                ->addColumn('assetTypes_id', 'integer', ['null' => true, 'comment' => 'NULL = globaler Rabatt'])
                ->addColumn('day_rate', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('week_rate', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('discount_pct', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['owner_instance_id', 'partner_instance_id', 'assetTypes_id'])
                ->create();
        }

        // Equipment-Anfragen zwischen Partnern
        if (!$this->hasTable('partner_requests')) {
            $this->table('partner_requests', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('from_instance_id', 'integer')
                ->addColumn('to_instance_id', 'integer')
                ->addColumn('projects_id', 'integer')
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => 'pending, accepted, rejected, cancelled'])
                ->addColumn('date_start', 'date')
                ->addColumn('date_end', 'date')
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('response_notes', 'text', ['null' => true])
                ->addColumn('responded_at', 'datetime', ['null' => true])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['from_instance_id'])
                ->addIndex(['to_instance_id'])
                ->addIndex(['status'])
                ->create();
        }

        // Einzelne Equipment-Positionen einer Anfrage
        if (!$this->hasTable('partner_request_items')) {
            $this->table('partner_request_items', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('partner_requests_id', 'integer')
                ->addColumn('assetTypes_id', 'integer')
                ->addColumn('quantity', 'integer', ['default' => 1])
                ->addColumn('day_rate', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addIndex(['partner_requests_id'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('partner_request_items')->drop()->save();
        $this->table('partner_requests')->drop()->save();
        $this->table('partner_price_rules')->drop()->save();
        $this->table('partner_links')->drop()->save();

        if ($this->hasTable('instances')) {
            $table = $this->table('instances');
            if ($table->hasColumn('instances_partnerCode')) {
                $table->removeColumn('instances_partnerCode')->update();
            }
        }
    }
}
