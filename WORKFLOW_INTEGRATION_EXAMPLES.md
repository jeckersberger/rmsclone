# Workflow Engine - Integration Examples

Quick reference for integrating workflows into existing MyRMS code.

## 1. Triggering Event-Based Workflows

### Example: When Invoice is Created

```php
// In your invoice creation code (e.g., InvoiceService.php)

public function createInvoice(array $data, int $instanceId, int $userId): ?int
{
    // ... existing code to create invoice ...

    $invoiceId = $this->db->insert('document_exports', [
        'instances_id' => $instanceId,
        'clients_id' => $data['clients_id'],
        'document_exports_number' => $invoiceNumber,
        'document_exports_date' => date('Y-m-d'),
        'document_exports_gross' => $data['amount'],
        // ... other fields
    ]);

    if (!$invoiceId) {
        return null;
    }

    // Trigger event-based workflows
    try {
        $workflowService = new WorkflowEngineService($this->db);
        $workflowService->processEventTriggers('invoice_created', [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'clients_id' => (int)$data['clients_id'],
            'client_email' => $clientEmail,
            'client_name' => $clientName,
            'amount' => $data['amount'],
            'created_by' => $userId
        ], $instanceId);
    } catch (Exception $e) {
        error_log("Workflow trigger failed: " . $e->getMessage());
        // Continue with invoice creation even if workflow fails
    }

    return $invoiceId;
}
```

### Example: When Asset is Returned

```php
// In your asset management code (e.g., AssetService.php)

public function returnAsset(int $assetId, int $instanceId): bool
{
    $this->db->where('id', $assetId);
    $asset = $this->db->getOne('assets');

    if (!$asset) {
        return false;
    }

    // Update asset status
    $this->db->where('id', $assetId);
    $success = (bool)$this->db->update('assets', [
        'status' => 'returned',
        'return_date' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    if ($success) {
        // Trigger asset returned workflow
        try {
            $workflowService = new WorkflowEngineService($this->db);
            $workflowService->processEventTriggers('asset_returned', [
                'asset_id' => $assetId,
                'asset_name' => $asset['name'],
                'asset_type' => $asset['type'],
                'serial_number' => $asset['serial_number'],
                'condition' => 'as_returned',
                'return_date' => date('Y-m-d H:i:s')
            ], $instanceId);
        } catch (Exception $e) {
            error_log("Asset return workflow failed: " . $e->getMessage());
        }
    }

    return $success;
}
```

### Example: When New Client is Created

```php
// In your client management code (e.g., ClientService.php)

public function createClient(array $data, int $instanceId, int $userId): ?int
{
    $clientId = $this->db->insert('clients', [
        'instances_id' => $instanceId,
        'clients_name' => $data['name'],
        'clients_email' => $data['email'],
        'clients_phone' => $data['phone'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $userId
    ]);

    if (!$clientId) {
        return null;
    }

    // Trigger onboarding workflow
    try {
        $workflowService = new WorkflowEngineService($this->db);
        $workflowService->processEventTriggers('client_created', [
            'client_id' => $clientId,
            'client_name' => $data['name'],
            'client_email' => $data['email'],
            'client_phone' => $data['phone'] ?? null,
            'created_by' => $userId
        ], $instanceId);
    } catch (Exception $e) {
        error_log("Client onboarding workflow failed: " . $e->getMessage());
    }

    return $clientId;
}
```

## 2. Scheduling Cron-Based Workflows

### Option A: WordPress-style Cron Hook (if using cron system)

```php
// Register cron job in your application bootstrap
// Executes every hour
add_cron_job('process_workflows', 'hourly', function($instanceId) {
    $workflowService = new WorkflowEngineService($db);
    $stats = $workflowService->processCronTriggers($instanceId);

    // Log results
    error_log("Workflow cron: executed={$stats['executed']}, failed={$stats['failed']}");
});
```

### Option B: Standalone Cron Script

Create `/cron/process_workflows.php`:

```php
<?php
/**
 * Workflow Cron Processor
 *
 * Add to crontab:
 * 0 * * * * /usr/bin/php /path/to/rmsclone/cron/process_workflows.php
 */

require_once __DIR__ . '/../src/services/WorkflowEngineService.php';

// Get database connection
// Assuming $db is available in your bootstrap
require_once __DIR__ . '/../config/database.php';

// Get all instances that have active cron workflows
$query = "SELECT DISTINCT instances_id FROM workflows WHERE is_active=1 AND trigger_type='cron'";
$stmt = $db->prepare($query);
$stmt->execute();

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    $instanceId = (int)$row['instances_id'];

    try {
        $workflowService = new WorkflowEngineService($db);
        $stats = $workflowService->processCronTriggers($instanceId);

        error_log("Instance $instanceId: executed={$stats['executed']}, failed={$stats['failed']}");
    } catch (Exception $e) {
        error_log("Workflow cron error for instance $instanceId: " . $e->getMessage());
    }
}

echo "Workflow cron processing completed\n";
```

Add to crontab:
```bash
# Run every hour
0 * * * * /usr/bin/php /var/www/rmsclone/cron/process_workflows.php >> /var/log/rmsclone_workflows.log 2>&1
```

## 3. Creating Workflows Programmatically

### Create Dunning Workflow

```php
$workflowService = new WorkflowEngineService($db);

$dunningWorkflowId = $workflowService->createWorkflow([
    'instances_id' => 1,
    'name' => 'Automatic Invoice Reminders',
    'description' => 'Automatically send payment reminders for overdue invoices',
    'trigger_type' => 'cron',
    'trigger_config' => [
        'cron_expression' => '0 8 * * *'  // Daily at 8 AM
    ],
    'is_active' => true,
    'created_by' => 1
], [
    // Step 1: Check if overdue 30 days
    [
        'step_order' => 1,
        'action_type' => 'condition',
        'condition_config' => [
            'type' => 'days_overdue',
            'target_field' => 'days_overdue',
            'operator' => 'equals',
            'value' => 30
        ]
    ],
    // Step 2: Send first reminder
    [
        'step_order' => 2,
        'action_type' => 'send_email',
        'action_config' => [
            'recipient' => '{{client_email}}',
            'subject' => 'Invoice {{invoice_number}} - Payment Reminder',
            'template' => 'invoice_reminder_30',
            'body' => 'Your invoice {{invoice_number}} is now overdue by 30 days.'
        ]
    ],
    // Step 3: Notify manager
    [
        'step_order' => 3,
        'action_type' => 'send_notification',
        'action_config' => [
            'user_roles' => ['manager'],
            'title' => 'Overdue Invoice Alert',
            'message' => 'Invoice {{invoice_number}} from {{client_name}} is 30 days overdue'
        ]
    ]
]);

echo "Dunning workflow created with ID: $dunningWorkflowId\n";
```

### Create Custom Workflow with Webhook

```php
$customWorkflowId = $workflowService->createWorkflow([
    'instances_id' => 1,
    'name' => 'Project Completion Notification',
    'description' => 'Notify external system when project is completed',
    'trigger_type' => 'event',
    'trigger_config' => [
        'event_type' => 'project_completed'
    ],
    'is_active' => true,
    'created_by' => 1
], [
    [
        'step_order' => 1,
        'action_type' => 'webhook',
        'action_config' => [
            'url' => 'https://external-system.com/api/projects/complete',
            'method' => 'POST',
            'payload' => [
                'project_id' => '{{project_id}}',
                'project_name' => '{{project_name}}',
                'completed_date' => '{{completed_date}}',
                'client_id' => '{{client_id}}'
            ]
        ]
    ],
    [
        'step_order' => 2,
        'action_type' => 'send_notification',
        'action_config' => [
            'user_roles' => ['manager', 'admin'],
            'title' => 'Project Completed: {{project_name}}',
            'message' => 'External notification sent successfully'
        ]
    ]
]);

echo "Custom workflow created with ID: $customWorkflowId\n";
```

## 4. Manually Execute Workflow

```php
// Execute workflow directly from code
$workflowService = new WorkflowEngineService($db);

$executionId = $workflowService->executeWorkflow(
    $workflowId,
    [
        'client_id' => 123,
        'client_name' => 'Acme Corp',
        'client_email' => 'contact@acme.com',
        'invoice_id' => 456,
        'amount' => 5000.00
    ],
    $instanceId
);

if ($executionId) {
    // Get execution details
    $execution = $workflowService->getExecutionDetail($executionId);

    if ($execution['status'] === 'completed') {
        echo "Workflow executed successfully\n";
    } else {
        echo "Workflow failed: " . $execution['error_message'] . "\n";
    }
}
```

## 5. API Usage Examples

### Create Workflow via API

```javascript
// JavaScript fetch example
fetch('/api/workflows/create.php', {
    method: 'POST',
    body: new URLSearchParams({
        name: 'New Workflow',
        description: 'Test workflow',
        trigger_type: 'manual',
        trigger_config: JSON.stringify({}),
        steps: JSON.stringify([
            {
                step_order: 1,
                action_type: 'send_email',
                action_config: {
                    recipient: 'test@example.com',
                    subject: 'Test Email',
                    body: 'This is a test'
                }
            }
        ]),
        is_active: 1
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        console.log('Workflow created:', data.data.workflow.id);
    }
});
```

### Execute Workflow via API

```javascript
fetch('/api/workflows/execute.php', {
    method: 'POST',
    body: new URLSearchParams({
        id: 1,
        trigger_data: JSON.stringify({
            client_email: 'customer@example.com',
            client_name: 'John Doe',
            invoice_number: 'INV-2024-001'
        })
    })
})
.then(r => r.json())
.then(data => {
    console.log('Workflow execution:', data.data.execution);
});
```

### Get Execution History via API

```javascript
fetch('/api/workflows/executions.php?workflow_id=1&limit=20')
    .then(r => r.json())
    .then(data => {
        console.log('Recent executions:', data.data.executions);
    });
```

## 6. Error Handling Best Practices

### Try-Catch Pattern

```php
$workflowService = new WorkflowEngineService($db);

try {
    $executionId = $workflowService->executeWorkflow($workflowId, $triggerData, $instanceId);

    if (!$executionId) {
        throw new Exception('Failed to create execution record');
    }

    $execution = $workflowService->getExecutionDetail($executionId);

    if ($execution['status'] === 'failed') {
        error_log("Workflow $workflowId execution failed: " . $execution['error_message']);

        // Send alert to administrators
        $adminService->sendAlert('Workflow Execution Failed', [
            'workflow_id' => $workflowId,
            'error' => $execution['error_message'],
            'logs' => $execution['logs']
        ]);
    }

} catch (Exception $e) {
    error_log("Critical workflow error: " . $e->getMessage());
    // Handle exception appropriately
}
```

## 7. Monitoring Workflow Executions

### Get Recent Failures

```php
$workflowService = new WorkflowEngineService($db);

// Get all workflows for instance
$workflows = $workflowService->getWorkflows($instanceId, true); // active only

$failedCount = 0;
foreach ($workflows as $workflow) {
    $executions = $workflowService->getExecutions($workflow['id'], 10);

    foreach ($executions as $execution) {
        if ($execution['status'] === 'failed') {
            $failedCount++;

            echo "Failed execution ID {$execution['id']} for workflow {$workflow['name']}\n";
            echo "Error: {$execution['error_message']}\n";
            echo "Date: {$execution['started_at']}\n\n";
        }
    }
}

echo "Total failed executions: $failedCount\n";
```

### Generate Workflow Statistics

```php
$workflowService = new WorkflowEngineService($db);

$stats = [
    'total_workflows' => 0,
    'active_workflows' => 0,
    'total_executions' => 0,
    'successful_executions' => 0,
    'failed_executions' => 0,
    'average_execution_time' => 0
];

$workflows = $workflowService->getWorkflows($instanceId);
$stats['total_workflows'] = count($workflows);
$stats['active_workflows'] = count(array_filter($workflows, fn($w) => $w['is_active']));

foreach ($workflows as $workflow) {
    $executions = $workflowService->getExecutions($workflow['id'], 100);
    $stats['total_executions'] += count($executions);

    foreach ($executions as $execution) {
        if ($execution['status'] === 'completed') {
            $stats['successful_executions']++;
        } elseif ($execution['status'] === 'failed') {
            $stats['failed_executions']++;
        }
    }
}

echo json_encode($stats, JSON_PRETTY_PRINT);
```

## 8. Testing Workflows

### Unit Test Example

```php
class WorkflowEngineServiceTest extends PHPUnit\Framework\TestCase
{
    private $db;
    private $service;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->service = new WorkflowEngineService($this->db);
    }

    public function testCreateWorkflow()
    {
        $workflowId = $this->service->createWorkflow([
            'instances_id' => 1,
            'name' => 'Test Workflow',
            'trigger_type' => 'manual',
            'is_active' => true,
            'created_by' => 1
        ], [
            [
                'step_order' => 1,
                'action_type' => 'send_email',
                'action_config' => ['recipient' => 'test@example.com']
            ]
        ]);

        $this->assertNotNull($workflowId);

        $workflow = $this->service->getWorkflow($workflowId);
        $this->assertEquals('Test Workflow', $workflow['name']);
        $this->assertEquals(1, count($workflow['steps']));
    }

    public function testExecuteWorkflow()
    {
        $executionId = $this->service->executeWorkflow(1, [], 1);
        $this->assertNotNull($executionId);

        $execution = $this->service->getExecutionDetail($executionId);
        $this->assertNotNull($execution['status']);
    }
}
```

## Common Patterns Summary

| Task | Method | Notes |
|------|--------|-------|
| List workflows | `getWorkflows($instanceId)` | Optionally filter by active status |
| Get single | `getWorkflow($id)` | Returns workflow with all steps |
| Create | `createWorkflow($data, $steps)` | Takes data array and steps array |
| Update | `updateWorkflow($id, $data, $steps)` | Replaces all steps |
| Delete | `deleteWorkflow($id)` | Cascades to executions and logs |
| Execute | `executeWorkflow($id, $triggerData, $instanceId)` | Returns execution ID |
| Event trigger | `processEventTriggers($eventType, $data, $instanceId)` | Called when events occur |
| Cron trigger | `processCronTriggers($instanceId)` | Called by scheduler |
| Get history | `getExecutions($workflowId, $limit)` | Returns recent executions |
| Get details | `getExecutionDetail($executionId)` | Returns full execution with logs |
| Templates | `getTemplates()` | Returns all templates |
| From template | `createFromTemplate($templateId, $instanceId, $userId)` | Creates workflow from template |
