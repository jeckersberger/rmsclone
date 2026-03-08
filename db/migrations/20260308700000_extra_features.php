<?php
/**
 * Extra Features Migration:
 * - In-App Notifications
 * - User Favorites/Bookmarks
 * - Project Checklists
 * - Project Comments/Notes
 * - Project Photos
 * - Equipment Serial Numbers
 * - Equipment Photos
 * - Tiered Pricing
 */
use Phinx\Migration\AbstractMigration;

class ExtraFeatures extends AbstractMigration
{
    public function up()
    {
        // In-App Benachrichtigungen
        if (!$this->hasTable('notifications')) {
            $this->table('notifications', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('users_userid', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true, 'null' => true])
                ->addColumn('type', 'string', ['limit' => 50])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('message', 'text', ['null' => true])
                ->addColumn('link', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('icon', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('read_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['users_userid'])
                ->addIndex(['instances_id'])
                ->addIndex(['read_at'])
                ->addIndex(['created_at'])
                ->create();
        }

        // Favoriten / Lesezeichen
        if (!$this->hasTable('user_favorites')) {
            $this->table('user_favorites', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('users_userid', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('url', 'string', ['limit' => 500])
                ->addColumn('icon', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['users_userid', 'instances_id'])
                ->create();
        }

        // Projekt-Checklisten
        if (!$this->hasTable('project_checklists')) {
            $this->table('project_checklists', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('projects_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('completed', 'boolean', ['default' => 0])
                ->addColumn('completed_by', 'integer', ['signed' => true, 'null' => true])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('created_by', 'integer', ['signed' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['projects_id'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // Projekt-Kommentare/Notizen
        if (!$this->hasTable('project_comments')) {
            $this->table('project_comments', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('projects_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('users_userid', 'integer', ['signed' => true])
                ->addColumn('comment', 'text')
                ->addColumn('is_internal', 'boolean', ['default' => 1])
                ->addColumn('parent_id', 'integer', ['signed' => true, 'null' => true])
                ->addColumn('deleted', 'boolean', ['default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['null' => true])
                ->addIndex(['projects_id'])
                ->addIndex(['users_userid'])
                ->create();
        }

        // Projekt-Fotos
        if (!$this->hasTable('project_photos')) {
            $this->table('project_photos', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('projects_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('s3files_id', 'integer', ['signed' => true, 'null' => true])
                ->addColumn('file_path', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('caption', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('photo_type', 'enum', ['values' => ['before', 'during', 'after', 'other'], 'default' => 'other'])
                ->addColumn('uploaded_by', 'integer', ['signed' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['projects_id'])
                ->create();
        }

        // Equipment-Fotos (mehrere pro Asset)
        if (!$this->hasTable('asset_photos')) {
            $this->table('asset_photos', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('assets_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('s3files_id', 'integer', ['signed' => true, 'null' => true])
                ->addColumn('file_path', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('caption', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('is_primary', 'boolean', ['default' => 0])
                ->addColumn('uploaded_by', 'integer', ['signed' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['assets_id'])
                ->create();
        }

        // Seriennummern
        $assetsTable = $this->table('assets');
        if (!$assetsTable->hasColumn('assets_serialNumber')) {
            $assetsTable
                ->addColumn('assets_serialNumber', 'string', ['limit' => 100, 'null' => true, 'after' => 'assets_tag'])
                ->update();
        }

        // Staffelpreise (Tiered Pricing)
        if (!$this->hasTable('tiered_pricing')) {
            $this->table('tiered_pricing', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('assetTypes_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('min_days', 'integer')
                ->addColumn('max_days', 'integer', ['null' => true])
                ->addColumn('day_rate', 'decimal', ['precision' => 10, 'scale' => 2])
                ->addColumn('discount_pct', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['assetTypes_id', 'instances_id'])
                ->create();
        }

        // Equipment-Handbuecher/Datenblaetter
        if (!$this->hasTable('asset_documents')) {
            $this->table('asset_documents', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('assetTypes_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('doc_type', 'enum', ['values' => ['manual', 'datasheet', 'certificate', 'warranty', 'other'], 'default' => 'other'])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('file_path', 'string', ['limit' => 500])
                ->addColumn('s3files_id', 'integer', ['signed' => true, 'null' => true])
                ->addColumn('uploaded_by', 'integer', ['signed' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['assetTypes_id'])
                ->create();
        }

        // Mindestbestand-Warnung
        $assetTypesTable = $this->table('assetTypes');
        if (!$assetTypesTable->hasColumn('assetTypes_minStock')) {
            $assetTypesTable
                ->addColumn('assetTypes_minStock', 'integer', ['null' => true, 'default' => null, 'after' => 'assetTypes_mass'])
                ->update();
        }
    }

    public function down()
    {
        $tables = ['notifications', 'user_favorites', 'project_checklists', 'project_comments',
                    'project_photos', 'asset_photos', 'tiered_pricing', 'asset_documents'];
        foreach ($tables as $t) {
            if ($this->hasTable($t)) $this->table($t)->drop()->save();
        }

        $assetsTable = $this->table('assets');
        if ($assetsTable->hasColumn('assets_serialNumber')) {
            $assetsTable->removeColumn('assets_serialNumber')->update();
        }
        $assetTypesTable = $this->table('assetTypes');
        if ($assetTypesTable->hasColumn('assetTypes_minStock')) {
            $assetTypesTable->removeColumn('assetTypes_minStock')->update();
        }
    }
}
