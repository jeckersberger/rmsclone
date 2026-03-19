<?php
use Phinx\Migration\AbstractMigration;

class LabelSizes extends AbstractMigration
{
    public function change()
    {
        // Configurable label size presets per instance
        if (!$this->hasTable('label_size_presets')) {
            $table = $this->table('label_size_presets', ['signed' => false]);
            $table
                ->addColumn('instances_id', 'integer', ['signed' => false, 'default' => 0, 'comment' => '0 = global preset'])
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('width_mm', 'decimal', ['precision' => 6, 'scale' => 2])
                ->addColumn('height_mm', 'decimal', ['precision' => 6, 'scale' => 2])
                ->addColumn('width_dots', 'integer', ['comment' => 'at 203dpi: mm * 8'])
                ->addColumn('height_dots', 'integer', ['comment' => 'at 203dpi: mm * 8'])
                ->addColumn('dpi', 'integer', ['default' => 203])
                ->addColumn('is_system', 'boolean', ['default' => false, 'comment' => 'system presets cannot be deleted'])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }

        if ($this->isMigratingUp()) {
            // System presets for Zebra LP2824 (203dpi, max 58mm width)
            $this->execute("INSERT INTO label_size_presets (instances_id, name, width_mm, height_mm, width_dots, height_dots, dpi, is_system, sort_order) VALUES
                (0, '57 x 32 mm (Standard breit)', 57.0, 32.0, 464, 260, 203, 1, 1),
                (0, '57 x 25 mm (Standard)', 57.0, 25.0, 464, 200, 203, 1, 2),
                (0, '57 x 19 mm (Schmal)', 57.0, 19.0, 464, 152, 203, 1, 3),
                (0, '57 x 51 mm (Groß)', 57.0, 51.0, 464, 410, 203, 1, 4),
                (0, '57 x 13 mm (Mini)', 57.0, 13.0, 464, 104, 203, 1, 5),
                (0, '50 x 25 mm', 50.0, 25.0, 406, 200, 203, 1, 6),
                (0, '50 x 30 mm', 50.0, 30.0, 406, 244, 203, 1, 7),
                (0, '40 x 20 mm (Kabel-Label)', 40.0, 20.0, 325, 162, 203, 1, 8),
                (0, '40 x 12 mm (Kabel mini)', 40.0, 12.0, 325, 98, 203, 1, 9),
                (0, '57 x 76 mm (Versand)', 57.0, 76.0, 464, 616, 203, 1, 10)
            ");
        }
    }
}
