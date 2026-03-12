<?php

use Phinx\Migration\AbstractMigration;

class FinalFeatures extends AbstractMigration
{
    public function change(): void
    {
        // SMS/WhatsApp Log
        if (!$this->hasTable('sms_log')) {
            $this->table('sms_log')
                ->addColumn('phone_number', 'string', ['limit' => 20])
                ->addColumn('message', 'string', ['limit' => 500])
                ->addColumn('channel', 'string', ['limit' => 10, 'default' => 'sms'])
                ->addColumn('success', 'boolean')
                ->addColumn('error', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('sent_at', 'datetime')
                ->addIndex(['sent_at'])
                ->create();
        }

        // Payment Links (Stripe/PayPal)
        if (!$this->hasTable('payment_links')) {
            $this->table('payment_links')
                ->addColumn('document_id', 'integer')
                ->addColumn('provider', 'string', ['limit' => 20])
                ->addColumn('session_id', 'string', ['limit' => 255])
                ->addColumn('payment_url', 'string', ['limit' => 500])
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
                ->addColumn('paid_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['document_id'])
                ->addIndex(['session_id'])
                ->addIndex(['status'])
                ->create();
        }

        // Shipments (DHL/DPD)
        if (!$this->hasTable('shipments')) {
            $this->table('shipments')
                ->addColumn('provider', 'string', ['limit' => 20])
                ->addColumn('tracking_number', 'string', ['limit' => 50])
                ->addColumn('label_url', 'string', ['limit' => 500, 'default' => ''])
                ->addColumn('projects_id', 'integer', ['default' => 0])
                ->addColumn('recipient_name', 'string', ['limit' => 100])
                ->addColumn('recipient_address', 'string', ['limit' => 300])
                ->addColumn('weight_kg', 'decimal', ['precision' => 6, 'scale' => 2])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'created'])
                ->addColumn('created_at', 'datetime')
                ->addIndex(['projects_id'])
                ->addIndex(['tracking_number'])
                ->create();
        }

        // Delivery Note Tokens (QR-Code Zugriff)
        if (!$this->hasTable('delivery_note_tokens')) {
            $this->table('delivery_note_tokens')
                ->addColumn('delivery_note_id', 'integer')
                ->addColumn('token', 'string', ['limit' => 32])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'datetime')
                ->addColumn('expires_at', 'datetime')
                ->addIndex(['token'], ['unique' => true])
                ->addIndex(['delivery_note_id'])
                ->create();
        }
    }
}
