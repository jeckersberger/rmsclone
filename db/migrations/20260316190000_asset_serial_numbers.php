<?php
use Phinx\Migration\AbstractMigration;

class AssetSerialNumbers extends AbstractMigration
{
    public function up()
    {
        // Add serial number columns to assets table
        if ($this->hasTable('assets')) {
            $table = $this->table('assets');

            if (!$this->hasColumn('assets', 'assets_serialInternal')) {
                $table->addColumn('assets_serialInternal', 'string', [
                    'limit' => 50,
                    'null' => true,
                    'comment' => 'Company internal serial number (short, for label)'
                ]);
            }

            if (!$this->hasColumn('assets', 'assets_serialManufacturer')) {
                $table->addColumn('assets_serialManufacturer', 'string', [
                    'limit' => 100,
                    'null' => true,
                    'comment' => 'Manufacturer serial number (for insurance/warranty)'
                ]);
            }

            if (!$this->hasColumn('assets', 'assets_purchaseDate')) {
                $table->addColumn('assets_purchaseDate', 'date', [
                    'null' => true,
                    'comment' => 'Purchase date (for warranty tracking)'
                ]);
            }

            if (!$this->hasColumn('assets', 'assets_purchasePrice')) {
                $table->addColumn('assets_purchasePrice', 'decimal', [
                    'precision' => 12,
                    'scale' => 2,
                    'null' => true,
                    'comment' => 'Purchase price (for insurance/depreciation)'
                ]);
            }

            if (!$this->hasColumn('assets', 'assets_warrantyUntil')) {
                $table->addColumn('assets_warrantyUntil', 'date', [
                    'null' => true,
                    'comment' => 'Warranty expiry date'
                ]);
            }

            if (!$this->hasColumn('assets', 'assets_insuranceRef')) {
                $table->addColumn('assets_insuranceRef', 'string', [
                    'limit' => 100,
                    'null' => true,
                    'comment' => 'Insurance reference number'
                ]);
            }

            // Add indexes for serial numbers
            $table->addIndex(['assets_serialInternal']);
            $table->addIndex(['assets_serialManufacturer']);

            $table->update();
        }
    }

    public function down()
    {
        if ($this->hasTable('assets')) {
            $table = $this->table('assets');

            if ($this->hasColumn('assets', 'assets_serialInternal')) {
                $table->removeColumn('assets_serialInternal');
            }

            if ($this->hasColumn('assets', 'assets_serialManufacturer')) {
                $table->removeColumn('assets_serialManufacturer');
            }

            if ($this->hasColumn('assets', 'assets_purchaseDate')) {
                $table->removeColumn('assets_purchaseDate');
            }

            if ($this->hasColumn('assets', 'assets_purchasePrice')) {
                $table->removeColumn('assets_purchasePrice');
            }

            if ($this->hasColumn('assets', 'assets_warrantyUntil')) {
                $table->removeColumn('assets_warrantyUntil');
            }

            if ($this->hasColumn('assets', 'assets_insuranceRef')) {
                $table->removeColumn('assets_insuranceRef');
            }

            $table->update();
        }
    }
}
