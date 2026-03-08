<?php
/**
 * Projekt- und Kommunikations-Features
 *
 * Erstellt Tabellen fuer:
 *   - project_checklists: Checklisten-Eintraege pro Projekt
 *   - project_comments: Kommentare pro Projekt (strukturiert)
 *   - email_templates: E-Mail-Vorlagen pro Instanz
 */
use Phinx\Migration\AbstractMigration;

class ProjectCommFeatures extends AbstractMigration
{
    public function up()
    {
        // project_checklists
        if (!$this->hasTable('project_checklists')) {
            $this->table('project_checklists')
                ->addColumn('projects_id', 'integer', ['signed' => true])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('is_completed', 'boolean', ['default' => 0])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('completed_at', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('completed_by', 'integer', ['null' => true, 'default' => null])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['projects_id'])
                ->create();
        }

        // project_comments
        if (!$this->hasTable('project_comments')) {
            $this->table('project_comments')
                ->addColumn('projects_id', 'integer', ['signed' => true])
                ->addColumn('users_id', 'integer', ['signed' => true])
                ->addColumn('comment', 'text')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['projects_id'])
                ->addIndex(['users_id'])
                ->create();
        }

        // email_templates
        if (!$this->hasTable('email_templates')) {
            $this->table('email_templates')
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('subject', 'string', ['limit' => 255])
                ->addColumn('body', 'text')
                ->addColumn('template_type', 'enum', [
                    'values' => ['invoice', 'quote', 'reminder', 'dunning', 'project_confirm', 'return_reminder', 'custom'],
                ])
                ->addColumn('variables', 'text', ['null' => true, 'comment' => 'JSON of available template vars'])
                ->addColumn('is_default', 'boolean', ['default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['template_type'])
                ->create();
        }
    }

    public function down()
    {
        if ($this->hasTable('project_checklists')) {
            $this->table('project_checklists')->drop()->save();
        }
        if ($this->hasTable('project_comments')) {
            $this->table('project_comments')->drop()->save();
        }
        if ($this->hasTable('email_templates')) {
            $this->table('email_templates')->drop()->save();
        }
    }
}
