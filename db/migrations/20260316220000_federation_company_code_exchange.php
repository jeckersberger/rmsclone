<?php
use Phinx\Migration\AbstractMigration;

class FederationCompanyCodeExchange extends AbstractMigration
{
    public function change()
    {
        // Add remote company code to partner_servers for collision detection
        $table = $this->table('partner_servers');
        if (!$table->hasColumn('partner_servers_remoteCompanyCode')) {
            $table->addColumn('partner_servers_remoteCompanyCode', 'string', [
                'limit' => 10,
                'null' => true,
                'after' => 'partner_servers_remoteInstanceId',
                'comment' => 'Company code of the remote partner (exchanged during handshake for collision detection)',
            ])
            ->update();
        }
    }
}
