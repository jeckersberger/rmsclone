<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class PartnerFederation extends AbstractMigration
{
    public function change()
    {
        // Remote-Server-Registry: speichert verbundene Partner-Server
        $this->table('partner_servers', [
                'id' => false,
                'primary_key' => ['partner_servers_id'],
                'engine' => 'InnoDB',
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('partner_servers_id', 'integer', [
                'null' => false,
                'identity' => 'enable',
            ])
            ->addColumn('instances_id', 'integer', [
                'null' => false,
                'comment' => 'Lokale Instanz die diese Partnerschaft hat',
            ])
            ->addColumn('partner_servers_url', 'string', [
                'null' => false,
                'limit' => 500,
                'comment' => 'Basis-URL des Partner-Servers (z.B. https://firma-b.de)',
            ])
            ->addColumn('partner_servers_name', 'string', [
                'null' => true,
                'limit' => 255,
                'comment' => 'Name der Partner-Firma (vom Remote-Server uebermittelt)',
            ])
            ->addColumn('partner_servers_apiKey', 'string', [
                'null' => false,
                'limit' => 128,
                'comment' => 'API-Key den WIR dem Partner gegeben haben (er authentifiziert sich damit bei uns)',
            ])
            ->addColumn('partner_servers_remoteApiKey', 'string', [
                'null' => false,
                'limit' => 128,
                'comment' => 'API-Key den der Partner uns gegeben hat (wir authentifizieren uns damit bei ihm)',
            ])
            ->addColumn('partner_servers_remoteInstanceId', 'integer', [
                'null' => true,
                'comment' => 'instances_id auf dem Remote-Server',
            ])
            ->addColumn('partner_servers_status', 'string', [
                'null' => false,
                'limit' => 20,
                'default' => 'pending',
                'comment' => 'pending, active, rejected, revoked',
            ])
            ->addColumn('partner_servers_lastSeen', 'datetime', [
                'null' => true,
                'comment' => 'Letzter erfolgreicher Kontakt',
            ])
            ->addColumn('partner_servers_created', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('partner_servers_deleted', 'boolean', [
                'null' => false,
                'default' => 0,
            ])
            ->addIndex(['instances_id'])
            ->addIndex(['partner_servers_apiKey'], ['unique' => true])
            ->create();

        // Log-Tabelle fuer Federation-Kommunikation
        $this->table('partner_federation_log', [
                'id' => false,
                'primary_key' => ['partner_federation_log_id'],
                'engine' => 'InnoDB',
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('partner_federation_log_id', 'integer', [
                'null' => false,
                'identity' => 'enable',
            ])
            ->addColumn('partner_servers_id', 'integer', [
                'null' => true,
            ])
            ->addColumn('partner_federation_log_direction', 'string', [
                'null' => false,
                'limit' => 10,
                'comment' => 'incoming oder outgoing',
            ])
            ->addColumn('partner_federation_log_endpoint', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('partner_federation_log_status', 'string', [
                'null' => false,
                'limit' => 20,
                'comment' => 'success, error, timeout',
            ])
            ->addColumn('partner_federation_log_message', 'text', [
                'null' => true,
            ])
            ->addColumn('partner_federation_log_created', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['partner_servers_id'])
            ->create();

        // partner_requests: Spalten fuer Federation anpassen
        $table = $this->table('partner_requests');
        if (!$table->hasColumn('partner_servers_id')) {
            $table->addColumn('partner_servers_id', 'integer', [
                'null' => true,
                'comment' => 'NULL = lokale Anfrage, sonst Remote-Server',
                'after' => 'deleted',
            ])
            ->changeColumn('from_instance_id', 'integer', ['null' => true])
            ->changeColumn('projects_id', 'integer', ['null' => true])
            ->changeColumn('created_by', 'integer', ['null' => true])
            ->addIndex(['partner_servers_id'])
            ->update();
        }
    }
}
