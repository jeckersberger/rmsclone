<?php
use Phinx\Migration\AbstractMigration;

class ExternalItems extends AbstractMigration
{
    public function change()
    {
        // external_items - Fremdmaterial (borrowed/rented from others)
        $table = $this->table('external_items', ['signed' => false]);
        $table
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('owner_name', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('owner_contact', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('quantity', 'integer', ['default' => 1])
            ->addColumn('barcode', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('rfid_tag', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('project_id', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('location_id', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('location_custom', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['bei_uns', 'zurueckgegeben', 'verloren'], 'default' => 'bei_uns'])
            ->addColumn('return_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('received_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('returned_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('returned_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id'])
            ->addIndex(['barcode'])
            ->addIndex(['rfid_tag'])
            ->addIndex(['status'])
            ->addIndex(['project_id'])
            ->addIndex(['owner_name'])
            ->create();

        // Also create label_templates table for the Label Designer
        $templates = $this->table('label_templates', ['signed' => false]);
        $templates
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('entity_type', 'enum', ['values' => ['asset', 'stock', 'external', 'custom'], 'default' => 'custom'])
            ->addColumn('label_width', 'integer', ['default' => 464, 'comment' => 'dots at 203dpi, 464=2.28inch'])
            ->addColumn('label_height', 'integer', ['default' => 200, 'comment' => 'dots at 203dpi, 200=1inch'])
            ->addColumn('elements', 'text', ['comment' => 'JSON array of label elements'])
            ->addColumn('is_default', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id'])
            ->addIndex(['entity_type'])
            ->create();

        // Insert default templates
        if ($this->isMigratingUp()) {
            // Default Asset template
            $assetElements = json_encode([
                ['type' => 'text', 'x' => 10, 'y' => 10, 'font_size' => 28, 'bold' => true, 'field' => 'type_name', 'label' => 'Gerätetyp'],
                ['type' => 'text', 'x' => 370, 'y' => 10, 'font_size' => 20, 'field' => 'rfid_indicator', 'label' => 'RF'],
                ['type' => 'text', 'x' => 10, 'y' => 42, 'font_size' => 24, 'field' => 'asset_tag', 'prefix' => '#', 'label' => 'Asset-Nr.'],
                ['type' => 'barcode', 'x' => 10, 'y' => 72, 'height' => 50, 'field' => 'barcode_data', 'label' => 'Barcode'],
                ['type' => 'text', 'x' => 10, 'y' => 128, 'font_size' => 18, 'field' => 'barcode_data', 'label' => 'Barcode-Text'],
                ['type' => 'text', 'x' => 10, 'y' => 150, 'font_size' => 18, 'field' => 'location', 'prefix' => 'Lager: ', 'label' => 'Lagerort'],
                ['type' => 'text', 'x' => 200, 'y' => 150, 'font_size' => 16, 'field' => 'rfid_tag', 'prefix' => 'RFID:', 'label' => 'RFID-Tag']
            ]);

            // Default Stock template
            $stockElements = json_encode([
                ['type' => 'text', 'x' => 10, 'y' => 10, 'font_size' => 28, 'bold' => true, 'field' => 'item_name', 'label' => 'Artikelname'],
                ['type' => 'text', 'x' => 370, 'y' => 10, 'font_size' => 20, 'field' => 'rfid_indicator', 'label' => 'RF'],
                ['type' => 'text', 'x' => 10, 'y' => 42, 'font_size' => 22, 'field' => 'category', 'label' => 'Kategorie'],
                ['type' => 'text', 'x' => 350, 'y' => 42, 'font_size' => 22, 'field' => 'instance_number', 'prefix' => '#', 'label' => 'Instanz-Nr.'],
                ['type' => 'barcode', 'x' => 10, 'y' => 72, 'height' => 50, 'field' => 'barcode_data', 'label' => 'Barcode'],
                ['type' => 'text', 'x' => 10, 'y' => 128, 'font_size' => 18, 'field' => 'barcode_data', 'label' => 'Barcode-Text'],
                ['type' => 'text', 'x' => 10, 'y' => 150, 'font_size' => 18, 'field' => 'sku', 'prefix' => 'SKU: ', 'label' => 'SKU']
            ]);

            // Default External item template
            $externalElements = json_encode([
                ['type' => 'text', 'x' => 10, 'y' => 5, 'font_size' => 24, 'bold' => true, 'text' => 'FREMDMATERIAL', 'label' => 'Überschrift'],
                ['type' => 'line', 'x' => 10, 'y' => 32, 'x2' => 454, 'y2' => 32, 'thickness' => 2],
                ['type' => 'text', 'x' => 10, 'y' => 38, 'font_size' => 26, 'bold' => true, 'field' => 'description', 'label' => 'Beschreibung'],
                ['type' => 'text', 'x' => 10, 'y' => 68, 'font_size' => 20, 'field' => 'owner_name', 'prefix' => 'Eigent.: ', 'label' => 'Eigentümer'],
                ['type' => 'barcode', 'x' => 10, 'y' => 95, 'height' => 45, 'field' => 'barcode_data', 'label' => 'Barcode'],
                ['type' => 'text', 'x' => 10, 'y' => 146, 'font_size' => 16, 'field' => 'barcode_data', 'label' => 'Barcode-Text'],
                ['type' => 'text', 'x' => 10, 'y' => 168, 'font_size' => 16, 'field' => 'return_date', 'prefix' => 'Rueckgabe: ', 'label' => 'Rückgabedatum'],
                ['type' => 'text', 'x' => 250, 'y' => 168, 'font_size' => 16, 'field' => 'project_name', 'prefix' => 'Projekt: ', 'label' => 'Projekt']
            ]);

            $this->execute("INSERT INTO label_templates (instances_id, name, description, entity_type, label_width, label_height, elements, is_default) VALUES
                (0, 'Standard Geraete-Label', 'Standard-Label fuer Assets mit Barcode und RFID', 'asset', 464, 200, '" . addslashes($assetElements) . "', 1),
                (0, 'Standard Artikel-Label', 'Standard-Label fuer Artikel-Instanzen', 'stock', 464, 200, '" . addslashes($stockElements) . "', 1),
                (0, 'Standard Fremdmaterial-Label', 'Label fuer Fremdmaterial mit Eigentuemer', 'external', 464, 200, '" . addslashes($externalElements) . "', 1)
            ");
        }
    }
}
