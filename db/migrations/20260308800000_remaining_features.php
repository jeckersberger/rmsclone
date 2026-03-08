<?php

use Phinx\Migration\AbstractMigration;

class RemainingFeatures extends AbstractMigration
{
    public function change(): void
    {
        // Equipment Lifecycle Felder
        if ($this->table('assets')->hasColumn('assets_id')) {
            $assets = $this->table('assets');
            if (!$assets->hasColumn('assets_lifecycle_status')) {
                $assets
                    ->addColumn('assets_lifecycle_status', 'string', ['limit' => 30, 'default' => 'active', 'after' => 'assets_deleted'])
                    ->addColumn('assets_lifecycle_ordered_date', 'datetime', ['null' => true])
                    ->addColumn('assets_lifecycle_received_date', 'datetime', ['null' => true])
                    ->addColumn('assets_lifecycle_commissioned_date', 'datetime', ['null' => true])
                    ->addColumn('assets_lifecycle_decommissioned_date', 'datetime', ['null' => true])
                    ->addColumn('assets_lifecycle_disposed_date', 'datetime', ['null' => true])
                    ->addColumn('assets_lifecycle_notes', 'text', ['null' => true])
                    ->addColumn('assets_lifecycle_purchase_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                    ->addColumn('assets_lifecycle_sale_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                    ->addIndex(['assets_lifecycle_status'])
                    ->save();
            }
        }

        // Asset Lifecycle Log
        if (!$this->hasTable('asset_lifecycle_log')) {
            $this->table('asset_lifecycle_log')
                ->addColumn('asset_id', 'integer')
                ->addColumn('from_status', 'string', ['limit' => 30])
                ->addColumn('to_status', 'string', ['limit' => 30])
                ->addColumn('user_id', 'integer')
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['asset_id'])
                ->addIndex(['created_at'])
                ->create();
        }

        // Mindestmietdauer
        if ($this->table('assetTypes')->hasColumn('assetTypes_id')) {
            $at = $this->table('assetTypes');
            if (!$at->hasColumn('assetTypes_minRentalDays')) {
                $at->addColumn('assetTypes_minRentalDays', 'integer', ['default' => 0])
                    ->save();
            }
        }

        // Zuschlag-Konfiguration
        if ($this->table('instances')->hasColumn('instances_id')) {
            $inst = $this->table('instances');
            if (!$inst->hasColumn('instances_surchargeEnabled')) {
                $inst
                    ->addColumn('instances_surchargeEnabled', 'boolean', ['default' => false])
                    ->addColumn('instances_weekendSurchargeRate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 15.00])
                    ->addColumn('instances_holidaySurchargeRate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 25.00])
                    ->save();
            }
        }

        // Rabattcodes
        if (!$this->hasTable('discount_codes')) {
            $this->table('discount_codes')
                ->addColumn('instances_id', 'integer')
                ->addColumn('code', 'string', ['limit' => 30])
                ->addColumn('type', 'string', ['limit' => 10, 'default' => 'percent'])
                ->addColumn('value', 'decimal', ['precision' => 10, 'scale' => 2])
                ->addColumn('description', 'string', ['limit' => 255, 'default' => ''])
                ->addColumn('valid_from', 'date', ['null' => true])
                ->addColumn('valid_until', 'date', ['null' => true])
                ->addColumn('max_uses', 'integer', ['default' => 0])
                ->addColumn('current_uses', 'integer', ['default' => 0])
                ->addColumn('min_order_value', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('asset_type_ids', 'text', ['null' => true])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['instances_id', 'code'], ['unique' => true])
                ->addIndex(['active'])
                ->create();
        }

        // E-Mail-Vorlagen
        if (!$this->hasTable('email_templates')) {
            $this->table('email_templates')
                ->addColumn('instances_id', 'integer')
                ->addColumn('type', 'string', ['limit' => 50])
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('subject', 'string', ['limit' => 255])
                ->addColumn('body', 'text')
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addColumn('created_at', 'datetime')
                ->addColumn('updated_at', 'datetime')
                ->addIndex(['instances_id', 'type'])
                ->create();
        }

        // Projekt-Benachrichtigungsflags
        if ($this->table('projects')->hasColumn('projects_id')) {
            $proj = $this->table('projects');
            if (!$proj->hasColumn('projects_reminder_sent')) {
                $proj
                    ->addColumn('projects_reminder_sent', 'boolean', ['default' => false])
                    ->addColumn('projects_feedback_sent', 'boolean', ['default' => false])
                    ->save();
            }
        }

        // Digitale Unterschriften
        if (!$this->hasTable('digital_signatures')) {
            $this->table('digital_signatures')
                ->addColumn('document_id', 'integer')
                ->addColumn('document_type', 'string', ['limit' => 30])
                ->addColumn('signature_data', 'text', ['limit' => 16777215]) // MEDIUMTEXT
                ->addColumn('signer_name', 'string', ['limit' => 100])
                ->addColumn('signer_ip', 'string', ['limit' => 45])
                ->addColumn('user_id', 'integer')
                ->addColumn('signed_at', 'datetime')
                ->addColumn('hash', 'string', ['limit' => 64])
                ->addIndex(['document_id', 'document_type'])
                ->addIndex(['hash'])
                ->create();
        }

        // Kunden-Portal Tokens
        if (!$this->hasTable('customer_portal_tokens')) {
            $this->table('customer_portal_tokens')
                ->addColumn('client_id', 'integer')
                ->addColumn('token', 'string', ['limit' => 64])
                ->addColumn('expires_at', 'datetime')
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['token'])
                ->addIndex(['client_id'])
                ->create();
        }

        // Projekt-Feedback
        if (!$this->hasTable('project_feedback')) {
            $this->table('project_feedback')
                ->addColumn('projects_id', 'integer')
                ->addColumn('clients_id', 'integer')
                ->addColumn('rating', 'integer')
                ->addColumn('comment', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['projects_id'])
                ->create();
        }

        // Webhooks
        if (!$this->hasTable('webhooks')) {
            $this->table('webhooks')
                ->addColumn('instances_id', 'integer')
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('url', 'string', ['limit' => 500])
                ->addColumn('secret', 'string', ['limit' => 64])
                ->addColumn('events', 'text')
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addColumn('last_triggered_at', 'datetime', ['null' => true])
                ->addColumn('last_error', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('failure_count', 'integer', ['default' => 0])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['instances_id', 'active'])
                ->create();
        }

        // Webhook Delivery Log
        if (!$this->hasTable('webhook_deliveries')) {
            $this->table('webhook_deliveries')
                ->addColumn('webhook_id', 'integer')
                ->addColumn('event', 'string', ['limit' => 50])
                ->addColumn('http_code', 'integer')
                ->addColumn('success', 'boolean')
                ->addColumn('error', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['webhook_id'])
                ->addIndex(['created_at'])
                ->create();
        }

        // Kalender-Feed Tokens
        if (!$this->hasTable('calendar_feeds')) {
            $this->table('calendar_feeds')
                ->addColumn('user_id', 'integer')
                ->addColumn('instances_id', 'integer')
                ->addColumn('token', 'string', ['limit' => 64])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['token'])
                ->addIndex(['user_id', 'instances_id'])
                ->create();
        }

        // RFID Tags
        if (!$this->hasTable('rfid_tags')) {
            $this->table('rfid_tags')
                ->addColumn('asset_id', 'integer')
                ->addColumn('tag_epc', 'string', ['limit' => 48])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
                ->addColumn('assigned_by', 'integer')
                ->addColumn('assigned_at', 'datetime')
                ->addIndex(['tag_epc'], ['unique' => true])
                ->addIndex(['asset_id'])
                ->addIndex(['status'])
                ->create();
        }

        // RFID Gateways
        if (!$this->hasTable('rfid_gateways')) {
            $this->table('rfid_gateways')
                ->addColumn('instances_id', 'integer')
                ->addColumn('gateway_id', 'string', ['limit' => 50])
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('location', 'string', ['limit' => 200, 'default' => ''])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('last_seen_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['instances_id', 'gateway_id'], ['unique' => true])
                ->create();
        }

        // RFID Scan Log
        if (!$this->hasTable('rfid_scan_log')) {
            $this->table('rfid_scan_log')
                ->addColumn('gateway_id', 'string', ['limit' => 50])
                ->addColumn('instances_id', 'integer')
                ->addColumn('tags_scanned', 'integer')
                ->addColumn('tags_found', 'integer')
                ->addColumn('tags_unknown', 'integer')
                ->addColumn('scan_data', 'text', ['limit' => 16777215])
                ->addColumn('scanned_at', 'datetime')
                ->addIndex(['instances_id'])
                ->addIndex(['scanned_at'])
                ->create();
        }

        // Partner Orders
        if (!$this->hasTable('partner_orders')) {
            $this->table('partner_orders')
                ->addColumn('partnership_id', 'integer')
                ->addColumn('project_id', 'integer')
                ->addColumn('requesting_instance_id', 'integer')
                ->addColumn('providing_instance_id', 'integer')
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
                ->addColumn('items', 'text')
                ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0])
                ->addColumn('created_at', 'datetime')
                ->addColumn('updated_at', 'datetime')
                ->addIndex(['partnership_id'])
                ->addIndex(['status'])
                ->create();
        }

        // AI Lookup Cache
        if (!$this->hasTable('ai_lookup_cache')) {
            $this->table('ai_lookup_cache')
                ->addColumn('cache_key', 'string', ['limit' => 32])
                ->addColumn('product_name', 'string', ['limit' => 200])
                ->addColumn('manufacturer', 'string', ['limit' => 100, 'default' => ''])
                ->addColumn('response_data', 'text')
                ->addColumn('created_at', 'datetime')
                ->addIndex(['cache_key'], ['unique' => true])
                ->addIndex(['created_at'])
                ->create();
        }
    }
}
