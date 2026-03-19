<?php

use Phinx\Migration\AbstractMigration;

class PricingEngine extends AbstractMigration
{
    public function change()
    {
        // pricing_tiers - Staffelpreise (duration-based pricing)
        if (!$this->hasTable('pricing_tiers')) {
            $table = $this->table('pricing_tiers', ['signed' => false]);
            $table->addColumn('asset_type_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('min_days', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('max_days', 'integer', ['unsigned' => true, 'null' => true])
                ->addColumn('price_per_day', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('instances_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['asset_type_id', 'min_days', 'max_days'], ['name' => 'idx_tier_range'])
                ->addIndex(['instances_id'], ['name' => 'idx_tiers_instance'])
                ->create();
        }

        // pricing_volume_discounts - Mengenrabatte (quantity-based discounts)
        if (!$this->hasTable('pricing_volume_discounts')) {
            $table = $this->table('pricing_volume_discounts', ['signed' => false]);
            $table->addColumn('min_quantity', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('max_quantity', 'integer', ['unsigned' => true, 'null' => true])
                ->addColumn('discount_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false])
                ->addColumn('instances_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['min_quantity', 'max_quantity'], ['name' => 'idx_volume_range'])
                ->addIndex(['instances_id'], ['name' => 'idx_volume_instance'])
                ->create();
        }

        // pricing_seasonal_surcharges - Saisonzuschläge (seasonal price increases)
        if (!$this->hasTable('pricing_seasonal_surcharges')) {
            $table = $this->table('pricing_seasonal_surcharges', ['signed' => false]);
            $table->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('start_month', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('start_day', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('end_month', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('end_day', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('surcharge_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false])
                ->addColumn('instances_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'], ['name' => 'idx_surcharge_instance'])
                ->create();
        }

        // pricing_bundles - Paketpreise (package/bundle pricing)
        if (!$this->hasTable('pricing_bundles')) {
            $table = $this->table('pricing_bundles', ['signed' => false]);
            $table->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('bundle_price_per_day', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('instances_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'is_active'], ['name' => 'idx_bundle_active'])
                ->create();
        }

        // pricing_bundle_items - Bundle-Positionen (items in bundles)
        if (!$this->hasTable('pricing_bundle_items')) {
            $table = $this->table('pricing_bundle_items', ['signed' => false]);
            $table->addColumn('bundle_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('asset_type_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('quantity', 'integer', ['unsigned' => true, 'null' => false, 'default' => 1])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['bundle_id'], ['name' => 'idx_bundle_items_bundle'])
                ->create();
        }

        // pricing_customer_lists - Kundenspezifische Preislisten
        if (!$this->hasTable('pricing_customer_lists')) {
            $table = $this->table('pricing_customer_lists', ['signed' => false]);
            $table->addColumn('client_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('discount_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
                ->addColumn('name', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('instances_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['client_id', 'instances_id'], ['name' => 'idx_customer_list_lookup'])
                ->create();
        }

        // pricing_customer_list_items - Einzelpreise pro Kunde
        if (!$this->hasTable('pricing_customer_list_items')) {
            $table = $this->table('pricing_customer_list_items', ['signed' => false]);
            $table->addColumn('list_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('asset_type_id', 'integer', ['unsigned' => true, 'null' => false])
                ->addColumn('custom_price_per_day', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['list_id', 'asset_type_id'], ['name' => 'idx_customer_item_lookup'])
                ->create();
        }
    }
}
