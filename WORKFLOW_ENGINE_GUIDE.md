# Automatisierte Workflows / Trigger-Engine (K1)

Vollständige Implementierung eines leistungsstarken Workflow-Management-Systems für MyRMS mit Event-, Cron- und manuellen Triggern.

## Architektur-Überblick

### Komponenten

```
Workflow Engine System
├── Database Layer (Phinx Migration)
│   ├── workflows
│   ├── workflow_steps
│   ├── workflow_executions
│   ├── workflow_execution_logs
│   └── workflow_templates
├── Service Layer (WorkflowEngineService)
│   ├── CRUD Operations
│   ├── Execution Engine
│   ├── Step Processors
│   ├── Action Handlers
│   └── Condition Evaluator
├── API Layer (REST Endpoints)
│   ├── /workflows/list.php
│   ├── /workflows/get.php
│   ├── /workflows/create.php
│   ├── /workflows/update.php
│   ├── /workflows/delete.php
│   ├── /workflows/toggle.php
│   ├── /workflows/execute.php
│   ├── /workflows/executions.php
│   ├── /workflows/execution_detail.php
│   ├── /workflows/templates.php
│   └── /workflows/create_from_template.php
├── UI Layer (Twig Template + JavaScript)
│   ├── Workflow Dashboard
│   ├── Statistics & Analytics
│   ├── Template Picker Modal
│   └── Execution Log Viewer
└── Controller (PHP Router)
    └── /src/workflows/index.php
```

## Datenbank-Schema

### workflows
```sql
- id (PK)
- instances_id (FK)
- name VARCHAR(255)
- description TEXT
- trigger_type ENUM('event', 'cron', 'manual')
- trigger_config JSON
- is_active BOOLEAN
- created_by INT
- created_at DATETIME
- updated_at DATETIME

Indizes: (instances_id), (trigger_type), (is_active), (instances_id, is_active)
```

### workflow_steps
```sql
- id (PK)
- workflow_id (FK) -> workflows.id
- step_order INT
- action_type ENUM('send_email', 'create_task', 'change_status',
                    'send_notification', 'webhook', 'delay', 'condition')
- action_config JSON
- condition_config JSON (nullable)

Indizes: (workflow_id), (workflow_id, step_order)
```

### workflow_executions
```sql
- id (PK)
- workflow_id (FK) -> workflows.id
- instances_id INT
- trigger_data JSON
- status ENUM('running', 'completed', 'failed', 'cancelled')
- started_at DATETIME
- completed_at DATETIME (nullable)
- error_message TEXT (nullable)

Indizes: (workflow_id), (instances_id), (status), (started_at),
         (workflow_id, status)
```

### workflow_execution_logs
```sql
- id (PK)
- execution_id (FK) -> workflow_executions.id
- step_id INT
- status ENUM('success', 'failed', 'skipped')
- input_data JSON
- output_data JSON
- error_message TEXT (nullable)
- executed_at DATETIME

Indizes: (execution_id), (step_id), (status)
```

### workflow_templates
```sql
- id (PK)
- name VARCHAR(255)
- description TEXT
- category VARCHAR(100)
- workflow_json JSON

Indizes: (category)
```

## Service-Layer API

### WorkflowEngineService

**Konstruktor:**
```php
$service = new WorkflowEngineService($db);
```

**CRUD Operationen:**

```php
// Get all workflows
$workflows = $service->getWorkflows(int $instanceId, ?bool $activeOnly = null): array

// Get single workflow with steps
$workflow = $service->getWorkflow(int $id): ?array

// Create workflow
$id = $service->createWorkflow(array $data, array $steps): ?int
// $data: ['name', 'description', 'trigger_type', 'trigger_config', 'instances_id', 'created_by', 'is_active']
// $steps: array of step configs with ['step_order', 'action_type', 'action_config', 'condition_config']

// Update workflow
$success = $service->updateWorkflow(int $id, array $data, array $steps): bool

// Delete workflow
$success = $service->deleteWorkflow(int $id): bool

// Toggle active status
$success = $service->toggleActive(int $id, bool $active): bool
```

**Execution Operationen:**

```php
// Manual execution of a workflow
$executionId = $service->executeWorkflow(int $workflowId, array $triggerData, int $instanceId): ?int

// Execute a single step
$result = $service->executeStep(int $executionId, array $step, array $context): array
// Returns: ['success' => bool, 'skipped' => bool, 'error' => string|null, 'output' => array]

// Process event-based triggers
$service->processEventTriggers(string $eventType, array $eventData, int $instanceId): void

// Process cron-based triggers
$stats = $service->processCronTriggers(int $instanceId): array
// Returns: ['executed' => count, 'failed' => count]
```

**History & Templates:**

```php
// Get execution history
$executions = $service->getExecutions(int $workflowId, int $limit = 50): array

// Get execution details with logs
$execution = $service->getExecutionDetail(int $executionId): ?array

// Get available templates
$templates = $service->getTemplates(): array

// Create workflow from template
$id = $service->createFromTemplate(int $templateId, int $instanceId, int $userId): ?int
```

## Action Types

### 1. send_email
Sends email to specified recipient

```php
[
    'action_type' => 'send_email',
    'action_config' => [
        'recipient' => 'user@example.com',  // or {{client_email}} for variable substitution
        'subject' => 'Your Invoice: {{invoice_number}}',
        'template' => 'invoice_notification',  // optional
        'body' => 'Email body...'  // optional
    ]
]
```

### 2. create_task
Creates a new task and assigns it

```php
[
    'action_type' => 'create_task',
    'action_config' => [
        'title' => 'Maintenance check: {{asset_name}}',
        'description' => 'Returned asset requires inspection',
        'assigned_to_role' => 'technician',  // role-based assignment
        'priority' => 'high'  // low, medium, high, critical
    ]
]
```

### 3. change_status
Updates entity status in database

```php
[
    'action_type' => 'change_status',
    'action_config' => [
        'entity' => 'projects',  // table name
        'entity_id' => '{{project_id}}',
        'new_status' => 'completed'
    ]
]
```

### 4. send_notification
Sends in-app notification to users by role

```php
[
    'action_type' => 'send_notification',
    'action_config' => [
        'user_roles' => ['manager', 'admin'],
        'title' => 'Project ending soon: {{project_name}}',
        'message' => 'Action required before {{end_date}}',
        'icon' => 'fa-exclamation-triangle'
    ]
]
```

### 5. webhook
Calls external webhook endpoint

```php
[
    'action_type' => 'webhook',
    'action_config' => [
        'url' => 'https://external-system.com/api/notify',
        'method' => 'POST',  // POST, PUT, GET
        'payload' => [
            'invoice_id' => '{{invoice_id}}',
            'client_name' => '{{client_name}}',
            'amount' => '{{amount}}'
        ]
    ]
]
```

### 6. delay
Pauses execution before next step

```php
[
    'action_type' => 'delay',
    'action_config' => [
        'days' => 7,
        'hours' => 0,
        'minutes' => 0
    ]
]
```

### 7. condition
Evaluates condition to determine step execution

```php
[
    'action_type' => 'condition',
    'condition_config' => [
        'type' => 'days_overdue',
        'target_field' => 'days_overdue',
        'operator' => 'equals',  // equals, not_equals, greater_than, less_than,
                                 // greater_equal, less_equal, contains, not_contains
        'value' => 30
    ]
]
```

## Trigger Types

### 1. Manual (trigger_type: 'manual')
Workflow executed on-demand via API or UI

```php
$executionId = $service->executeWorkflow($workflowId, [], $instanceId);
```

### 2. Event (trigger_type: 'event')
Triggered when specific system events occur

```php
[
    'trigger_type' => 'event',
    'trigger_config' => [
        'event_type' => 'invoice_created'  // or 'asset_returned', 'client_created', etc.
    ]
]
```

Integration with event system:
```php
// When event occurs in code:
$workflowService->processEventTriggers('invoice_created', [
    'invoice_id' => 123,
    'invoice_number' => 'INV-2024-001',
    'client_id' => 45,
    'client_email' => 'client@example.com',
    'amount' => 1500.00
], $instanceId);
```

### 3. Cron (trigger_type: 'cron')
Scheduled execution via cron expression

```php
[
    'trigger_type' => 'cron',
    'trigger_config' => [
        'cron_expression' => '0 8 * * *'  // 8:00 AM daily
    ]
]
```

Integration with scheduler:
```php
// In cron job or scheduler:
$workflowService->processCronTriggers($instanceId);
```

## Pre-built Templates

Four default templates are provided:

### 1. Mahnwesen - Automatisiertes Dunning
Automatic dunning process with escalating reminders:
- Day 30: First notice email
- Day 60: Second notice email
- Day 90: Notification to management

### 2. Rückgabe-Prüfung - Wartungscheck
Triggered when asset is returned:
- Creates maintenance check task
- Notifies technician

### 3. Neukunden-Onboarding
Triggered when new client is created:
- Sends welcome email immediately
- Sends follow-up email after 7 days

### 4. Projekt-Erinnerung - Projektende
Scheduled daily check:
- 3 days before project end date
- Notifies project manager

## REST API Endpoints

### GET /api/workflows/list.php
List all workflows for instance

**Query Params:**
- `active_only` (bool, optional): Filter to active workflows

**Response:**
```json
{
  "success": true,
  "data": {
    "workflows": [
      {
        "id": 1,
        "name": "Dunning Process",
        "description": "Automatic invoice reminders",
        "trigger_type": "cron",
        "is_active": true,
        "execution_count": 45,
        "last_execution": { ... }
      }
    ],
    "count": 1
  }
}
```

### GET /api/workflows/get.php
Get single workflow with all steps

**Query Params:**
- `id` (int, required): Workflow ID

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow": {
      "id": 1,
      "name": "...",
      "steps": [
        {
          "id": 1,
          "step_order": 1,
          "action_type": "condition",
          "action_config": { ... },
          "condition_config": { ... }
        }
      ]
    }
  }
}
```

### POST /api/workflows/create.php
Create new workflow

**Form Params:**
- `name` (string, required)
- `description` (string)
- `trigger_type` (enum: manual, event, cron)
- `trigger_config` (JSON object)
- `steps` (JSON array)
- `is_active` (bool)

### POST /api/workflows/update.php
Update existing workflow

**Form Params:**
- `id` (int, required)
- `name` (string)
- `description` (string)
- `trigger_type` (enum)
- `trigger_config` (JSON)
- `steps` (JSON array)
- `is_active` (bool)

### POST /api/workflows/delete.php
Delete workflow

**Form Params:**
- `id` (int, required)

### POST /api/workflows/toggle.php
Enable/disable workflow

**Form Params:**
- `id` (int, required)
- `active` (bool, required)

### POST /api/workflows/execute.php
Manually execute workflow

**Form Params:**
- `id` (int, required): Workflow ID
- `trigger_data` (JSON, optional): Context data

### GET /api/workflows/executions.php
Get execution history

**Query Params:**
- `workflow_id` (int, required)
- `limit` (int, optional): Default 50, max 200

### GET /api/workflows/execution_detail.php
Get execution details with logs

**Query Params:**
- `id` (int, required): Execution ID

### GET /api/workflows/templates.php
Get all available templates

### POST /api/workflows/create_from_template.php
Create workflow from template

**Form Params:**
- `template_id` (int, required)

## Variable Substitution

Use `{{variable_name}}` syntax in action configs to substitute context values:

```php
[
    'action_config' => [
        'subject' => 'Invoice {{invoice_number}} from {{client_name}}',
        'recipient' => '{{client_email}}',
        'body' => 'Amount due: {{amount}}'
    ]
]
```

Available context variables depend on trigger type and data passed.

## Permissions

Three workflow-related permissions:

- `WORKFLOWS:VIEW` - View workflows and execution history
- `WORKFLOWS:EDIT` - Create, update, delete workflows
- `WORKFLOWS:EXECUTE` - Manually execute workflows

## Usage Examples

### Example 1: Create Dunning Workflow Manually

```php
$workflowService = new WorkflowEngineService($db);

$workflow = $workflowService->createWorkflow([
    'instances_id' => 1,
    'name' => 'Custom Dunning',
    'description' => 'Automatic invoice reminders',
    'trigger_type' => 'cron',
    'trigger_config' => ['cron_expression' => '0 8 * * *'],
    'is_active' => true,
    'created_by' => $userId
], [
    [
        'step_order' => 1,
        'action_type' => 'send_email',
        'action_config' => [
            'recipient' => '{{client_email}}',
            'subject' => 'Invoice {{invoice_number}} is now overdue',
            'template' => 'overdue_notice'
        ]
    ],
    [
        'step_order' => 2,
        'action_type' => 'send_notification',
        'action_config' => [
            'user_roles' => ['manager'],
            'title' => 'Overdue Invoice Alert',
            'message' => 'Client {{client_name}} has overdue invoice'
        ]
    ]
]);
```

### Example 2: Execute Workflow from Event

```php
// In your invoice creation code:
$invoiceId = createInvoice($data);

// Trigger any event-based workflows
$workflowService->processEventTriggers('invoice_created', [
    'invoice_id' => $invoiceId,
    'invoice_number' => $data['number'],
    'client_id' => $data['client_id'],
    'client_email' => $clientEmail,
    'amount' => $data['amount']
], $instanceId);
```

### Example 3: Schedule Cron Processing

```php
// In your cron job:
$workflowService = new WorkflowEngineService($db);
$stats = $workflowService->processCronTriggers($instanceId);
echo "Executed: {$stats['executed']}, Failed: {$stats['failed']}\n";
```

## UI Features

- **Workflow Dashboard**: Overview with statistics and action buttons
- **Workflow List**: All workflows with status, trigger type, last execution
- **Template Picker**: Modal to select and create from pre-built templates
- **Execution Logs**: Detailed view of each workflow execution with step logs
- **Quick Actions**: Execute, edit, toggle, delete workflows inline
- **Statistics Cards**: Count of active, inactive, today's, and failed workflows

## MeekroDB Compatibility

The service uses MeekroDB query builder patterns:

```php
// Get one record
$this->db->where('id', $id);
$record = $this->db->getOne('table');

// Get multiple records
$this->db->where('column', 'value');
$records = $this->db->get('table', $limit);

// Insert
$id = $this->db->insert('table', $data);

// Update
$this->db->where('id', $id);
$this->db->update('table', $data);

// Delete
$this->db->where('id', $id);
$this->db->delete('table');

// Count
$count = $this->db->getValue('table', 'COUNT(*)');
```

## Integration Points

### Event System Integration
Call `processEventTriggers()` when events occur:

```php
// When invoice created
$workflowService->processEventTriggers('invoice_created', $invoiceData, $instanceId);

// When asset returned
$workflowService->processEventTriggers('asset_returned', $assetData, $instanceId);

// When client created
$workflowService->processEventTriggers('client_created', $clientData, $instanceId);
```

### Scheduler Integration
Call `processCronTriggers()` from your scheduler:

```php
// In crontab or scheduler
0 * * * * php /path/to/cron_processor.php
```

### Email Integration
Extend action handlers to use existing EmailService:

```php
private function sendEmail(array $config, array $context): array {
    $mailer = new EmailService();
    $mailResult = $mailer->send(
        $config['recipient'],
        $config['subject'],
        $config['body']
    );
    // ... handle result
}
```

## Performance Considerations

- **Execution Limits**: Long-running workflows may timeout; use async processing for delays
- **Database Indexes**: All key tables are indexed for fast queries
- **Batch Operations**: Cron trigger processing executes all workflows in sequence
- **Logging**: Every step is logged; consider archiving old logs periodically

## Future Enhancements

- Async execution queue for long-running workflows
- Workflow versioning and rollback
- Advanced condition builder UI
- Workflow analytics and reporting
- Workflow templates marketplace
- Multi-step approval chains
- Parallel step execution
- Workflow cloning and duplication

## File Structure

```
/sessions/vibrant-kind-edison/rmsclone/
├── db/migrations/
│   └── 20260318140000_workflow_engine.php
├── src/
│   ├── services/
│   │   └── WorkflowEngineService.php
│   ├── api/
│   │   └── workflows/
│   │       ├── list.php
│   │       ├── get.php
│   │       ├── create.php
│   │       ├── update.php
│   │       ├── delete.php
│   │       ├── toggle.php
│   │       ├── execute.php
│   │       ├── executions.php
│   │       ├── execution_detail.php
│   │       ├── templates.php
│   │       └── create_from_template.php
│   └── workflows/
│       ├── index.php
│       └── workflows_index.twig
└── WORKFLOW_ENGINE_GUIDE.md
```
