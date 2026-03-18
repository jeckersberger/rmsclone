<?php
/**
 * Workflow Engine Migration
 *
 * Implements automated workflows/trigger engine for MyRMS with support for:
 * - Event-based triggers (invoice created, asset returned, etc.)
 * - Cron-based triggers (scheduled actions)
 * - Manual execution
 * - Multiple action types (email, tasks, notifications, webhooks, conditions)
 * - Workflow templates for common patterns
 */

use Phinx\Migration\AbstractMigration;

class WorkflowEngine extends AbstractMigration
{
    public function change()
    {
        // Main workflows table
        $table = $this->table('workflows', ['id' => 'id', 'signed' => false]);
        $table->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('name', 'string', ['length' => 255, 'null' => false])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('trigger_type', 'enum', ['values' => ['event', 'cron', 'manual'], 'default' => 'manual'])
              ->addColumn('trigger_config', 'json', ['null' => true])
              ->addColumn('is_active', 'boolean', ['default' => false])
              ->addColumn('created_by', 'integer', ['signed' => false, 'null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['instances_id'])
              ->addIndex(['trigger_type'])
              ->addIndex(['is_active'])
              ->addIndex(['instances_id', 'is_active'])
              ->create();

        // Individual workflow steps/actions
        $table = $this->table('workflow_steps', ['id' => 'id', 'signed' => false]);
        $table->addColumn('workflow_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('step_order', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('action_type', 'enum', [
                  'values' => [
                      'send_email',
                      'create_task',
                      'change_status',
                      'send_notification',
                      'webhook',
                      'delay',
                      'condition'
                  ],
                  'default' => 'send_email'
              ])
              ->addColumn('action_config', 'json', ['null' => false])
              ->addColumn('condition_config', 'json', ['null' => true])
              ->addIndex(['workflow_id'])
              ->addIndex(['workflow_id', 'step_order'])
              ->addForeignKey('workflow_id', 'workflows', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();

        // Execution records
        $table = $this->table('workflow_executions', ['id' => 'id', 'signed' => false]);
        $table->addColumn('workflow_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('trigger_data', 'json', ['null' => true])
              ->addColumn('status', 'enum', [
                  'values' => ['running', 'completed', 'failed', 'cancelled'],
                  'default' => 'running'
              ])
              ->addColumn('started_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('completed_at', 'datetime', ['null' => true])
              ->addColumn('error_message', 'text', ['null' => true])
              ->addIndex(['workflow_id'])
              ->addIndex(['instances_id'])
              ->addIndex(['status'])
              ->addIndex(['started_at'])
              ->addIndex(['workflow_id', 'status'])
              ->addForeignKey('workflow_id', 'workflows', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();

        // Execution step logs
        $table = $this->table('workflow_execution_logs', ['id' => 'id', 'signed' => false]);
        $table->addColumn('execution_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('step_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('status', 'enum', [
                  'values' => ['success', 'failed', 'skipped'],
                  'default' => 'success'
              ])
              ->addColumn('input_data', 'json', ['null' => true])
              ->addColumn('output_data', 'json', ['null' => true])
              ->addColumn('error_message', 'text', ['null' => true])
              ->addColumn('executed_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['execution_id'])
              ->addIndex(['step_id'])
              ->addIndex(['status'])
              ->addForeignKey('execution_id', 'workflow_executions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();

        // Pre-built workflow templates
        $table = $this->table('workflow_templates', ['id' => 'id', 'signed' => false]);
        $table->addColumn('name', 'string', ['length' => 255, 'null' => false])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('category', 'string', ['length' => 100, 'null' => true])
              ->addColumn('workflow_json', 'json', ['null' => false])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['category'])
              ->create();

        // Insert default templates
        $this->table('workflow_templates')->insert([
            [
                'name' => 'Mahnwesen - Automatisiertes Dunning',
                'description' => 'Automatisches Mahnverfahren: 30d Mahnung, 60d Zweite Mahnung, 90d Notification an Geschäftsführung',
                'category' => 'Dunning',
                'workflow_json' => json_encode([
                    'name' => 'Mahnwesen - Automatisiertes Dunning',
                    'trigger_type' => 'cron',
                    'trigger_config' => ['cron_expression' => '0 8 * * *'],
                    'steps' => [
                        [
                            'step_order' => 1,
                            'action_type' => 'condition',
                            'condition_config' => [
                                'type' => 'days_overdue',
                                'operator' => 'equals',
                                'value' => 30
                            ]
                        ],
                        [
                            'step_order' => 2,
                            'action_type' => 'send_email',
                            'action_config' => [
                                'template' => 'dunning_first_notice',
                                'recipient_type' => 'client',
                                'subject' => 'Mahnung: Rechnungsausgleich erforderlich'
                            ]
                        ],
                        [
                            'step_order' => 3,
                            'action_type' => 'delay',
                            'action_config' => ['days' => 30]
                        ],
                        [
                            'step_order' => 4,
                            'action_type' => 'condition',
                            'condition_config' => [
                                'type' => 'days_overdue',
                                'operator' => 'equals',
                                'value' => 60
                            ]
                        ],
                        [
                            'step_order' => 5,
                            'action_type' => 'send_email',
                            'action_config' => [
                                'template' => 'dunning_second_notice',
                                'recipient_type' => 'client',
                                'subject' => 'Letzte Mahnung: Dringende Rechnungsausgleich erforderlich'
                            ]
                        ],
                        [
                            'step_order' => 6,
                            'action_type' => 'delay',
                            'action_config' => ['days' => 30]
                        ],
                        [
                            'step_order' => 7,
                            'action_type' => 'condition',
                            'condition_config' => [
                                'type' => 'days_overdue',
                                'operator' => 'equals',
                                'value' => 90
                            ]
                        ],
                        [
                            'step_order' => 8,
                            'action_type' => 'send_notification',
                            'action_config' => [
                                'user_roles' => ['manager'],
                                'title' => 'Überfällige Rechnung: Eskalation erforderlich',
                                'message' => 'Rechnung ist seit 90 Tagen überfällig'
                            ]
                        ]
                    ]
                ])
            ],
            [
                'name' => 'Rückgabe-Prüfung - Wartungscheck',
                'description' => 'Asset Rückgabe Trigger: Automatische Erstellung eines Wartungschecks',
                'category' => 'Maintenance',
                'workflow_json' => json_encode([
                    'name' => 'Rückgabe-Prüfung - Wartungscheck',
                    'trigger_type' => 'event',
                    'trigger_config' => ['event_type' => 'asset_returned'],
                    'steps' => [
                        [
                            'step_order' => 1,
                            'action_type' => 'create_task',
                            'action_config' => [
                                'title' => 'Wartungscheck erforderlich: {{asset_name}}',
                                'description' => 'Rückgegebenes Asset - Technische Prüfung durchführen',
                                'assigned_to_role' => 'technician',
                                'priority' => 'high'
                            ]
                        ],
                        [
                            'step_order' => 2,
                            'action_type' => 'send_notification',
                            'action_config' => [
                                'user_roles' => ['technician'],
                                'title' => 'Neue Wartungsaufgabe',
                                'message' => 'Asset {{asset_name}} benötigt Wartungscheck'
                            ]
                        ]
                    ]
                ])
            ],
            [
                'name' => 'Neukunden-Onboarding',
                'description' => 'Willkommens-Email bei Kundenerstellung, Follow-up nach 7 Tagen',
                'category' => 'CRM',
                'workflow_json' => json_encode([
                    'name' => 'Neukunden-Onboarding',
                    'trigger_type' => 'event',
                    'trigger_config' => ['event_type' => 'client_created'],
                    'steps' => [
                        [
                            'step_order' => 1,
                            'action_type' => 'send_email',
                            'action_config' => [
                                'template' => 'welcome_email',
                                'recipient_type' => 'client',
                                'subject' => 'Willkommen bei unserem Service'
                            ]
                        ],
                        [
                            'step_order' => 2,
                            'action_type' => 'delay',
                            'action_config' => ['days' => 7]
                        ],
                        [
                            'step_order' => 3,
                            'action_type' => 'send_email',
                            'action_config' => [
                                'template' => 'followup_email',
                                'recipient_type' => 'client',
                                'subject' => 'Wie läuft es mit Ihrem Account?'
                            ]
                        ]
                    ]
                ])
            ],
            [
                'name' => 'Projekt-Erinnerung - Projektende',
                'description' => 'Notification an Projektmanager 3 Tage vor Projektende',
                'category' => 'Projects',
                'workflow_json' => json_encode([
                    'name' => 'Projekt-Erinnerung - Projektende',
                    'trigger_type' => 'cron',
                    'trigger_config' => ['cron_expression' => '0 9 * * *'],
                    'steps' => [
                        [
                            'step_order' => 1,
                            'action_type' => 'condition',
                            'condition_config' => [
                                'type' => 'days_until',
                                'target_field' => 'project_end_date',
                                'operator' => 'equals',
                                'value' => 3
                            ]
                        ],
                        [
                            'step_order' => 2,
                            'action_type' => 'send_notification',
                            'action_config' => [
                                'user_roles' => ['manager'],
                                'title' => 'Projekt endet in 3 Tagen: {{project_name}}',
                                'message' => 'Bitte abschließende Maßnahmen durchführen'
                            ]
                        ]
                    ]
                ])
            ]
        ])->saveData();
    }
}
