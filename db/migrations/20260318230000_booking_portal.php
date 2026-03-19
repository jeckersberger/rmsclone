<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Online-Buchungsportal (L3)
 *
 * Creates tables for:
 * - Portal configuration (per instance)
 * - Portal inquiries (guest/registered client bookings)
 * - Portal sessions (guest/client authentication)
 */
final class BookingPortal extends AbstractMigration
{
    public function change(): void
    {
        // Portal Configuration
        if (!$this->hasTable('portal_config')) {
            $portalConfig = $this->table('portal_config', ['id' => false, 'primary_key' => ['instances_id']]);
            $portalConfig
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('is_active', 'boolean', ['default' => false, 'null' => false])
                ->addColumn('portal_title', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('portal_description', 'text', ['null' => true])
                ->addColumn('logo_path', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('primary_color', 'string', ['limit' => 7, 'default' => '#2563eb'])
                ->addColumn('show_prices', 'boolean', ['default' => true, 'null' => false])
                ->addColumn('require_registration', 'boolean', ['default' => true, 'null' => false])
                ->addColumn('require_admin_approval', 'boolean', ['default' => true, 'null' => false])
                ->addColumn('terms_html', 'longtext', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Portal Inquiries
        if (!$this->hasTable('portal_inquiries')) {
            $portalInquiries = $this->table('portal_inquiries');
            $portalInquiries
                ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('client_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('guest_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('guest_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('guest_phone', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('guest_company', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('items', 'json', ['null' => false])
                ->addColumn('rental_start', 'date', ['null' => false])
                ->addColumn('rental_end', 'date', ['null' => false])
                ->addColumn('message', 'text', ['null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['new', 'reviewed', 'quoted', 'accepted', 'rejected', 'cancelled'],
                    'default' => 'new'
                ])
                ->addColumn('project_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['client_id'])
                ->addIndex(['status'])
                ->addIndex(['project_id'])
                ->create();
        }

        // Portal Sessions (for guest/unregistered client access)
        if (!$this->hasTable('portal_sessions')) {
            $portalSessions = $this->table('portal_sessions');
            $portalSessions
                ->addColumn('token', 'string', ['limit' => 64, 'null' => false])
                ->addColumn('client_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => false])
                ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('expires_at', 'datetime', ['null' => false])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex('token', ['unique' => true])
                ->addIndex(['client_id'])
                ->addIndex(['expires_at'])
                ->create();
        }
    }
}
