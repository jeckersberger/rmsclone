<?php
/**
 * Rate-Limiting + Security Tables Migration
 *
 * Erstellt die rate_limits Tabelle fuer IP-basiertes Rate-Limiting
 * und schuetzt Login, Partner-Code-Generierung und andere Endpoints.
 */
use Phinx\Migration\AbstractMigration;

class SecurityRateLimits extends AbstractMigration
{
    public function up()
    {
        if (!$this->hasTable('rate_limits')) {
            $this->table('rate_limits', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('action', 'string', ['limit' => 50, 'comment' => 'login, partner_code, password_reset, api_general'])
                ->addColumn('identifier', 'string', ['limit' => 255, 'comment' => 'IP address, email, or user identifier'])
                ->addColumn('success', 'boolean', ['default' => false])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('attempted_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['action', 'identifier'])
                ->addIndex(['attempted_at'])
                ->addIndex(['ip_address'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('rate_limits')->drop()->save();
    }
}
