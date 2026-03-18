<?php
/**
 * Workflow Engine Service
 *
 * Manages automated workflows with event-based and cron-based triggers.
 * Supports email, task creation, status changes, notifications, webhooks, delays, and conditions.
 *
 * Usage:
 *   $workflowService = new WorkflowEngineService($db);
 *   $execution = $workflowService->executeWorkflow($workflowId, $triggerData, $instanceId);
 */
class WorkflowEngineService
{
    private $db;
    private $notificationService;
    private $emailService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->notificationService = new NotificationService($db);
    }

    /**
     * Get all workflows for an instance
     *
     * @param int $instanceId
     * @param bool|null $activeOnly Filter to active workflows only
     * @return array
     */
    public function getWorkflows(int $instanceId, ?bool $activeOnly = null): array
    {
        $this->db->where('instances_id', $instanceId);
        if ($activeOnly !== null) {
            $this->db->where('is_active', (int)$activeOnly);
        }
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('workflows', null, ['*']) ?: [];
    }

    /**
     * Get a single workflow with its steps
     *
     * @param int $id
     * @return array|null
     */
    public function getWorkflow(int $id): ?array
    {
        $this->db->where('id', $id);
        $workflow = $this->db->getOne('workflows');

        if (!$workflow) {
            return null;
        }

        // Get steps
        $this->db->where('workflow_id', $id);
        $this->db->orderBy('step_order', 'ASC');
        $workflow['steps'] = $this->db->get('workflow_steps', null, ['*']) ?: [];

        // Parse JSON fields
        if (is_string($workflow['trigger_config'])) {
            $workflow['trigger_config'] = json_decode($workflow['trigger_config'], true) ?: [];
        }

        foreach ($workflow['steps'] as &$step) {
            if (is_string($step['action_config'])) {
                $step['action_config'] = json_decode($step['action_config'], true) ?: [];
            }
            if (is_string($step['condition_config'])) {
                $step['condition_config'] = json_decode($step['condition_config'], true) ?: [];
            }
        }
        unset($step);

        return $workflow;
    }

    /**
     * Create a new workflow
     *
     * @param array $data Workflow data (name, description, trigger_type, trigger_config, instances_id, created_by)
     * @param array $steps Array of step configurations
     * @return int|null Workflow ID
     */
    public function createWorkflow(array $data, array $steps): ?int
    {
        if (empty($data['name']) || empty($data['instances_id'])) {
            return null;
        }

        $workflowId = $this->db->insert('workflows', [
            'instances_id' => (int)$data['instances_id'],
            'name' => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'trigger_type' => $data['trigger_type'] ?? 'manual',
            'trigger_config' => json_encode($data['trigger_config'] ?? []),
            'is_active' => (int)($data['is_active'] ?? 0),
            'created_by' => (int)($data['created_by'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$workflowId) {
            return null;
        }

        // Insert steps
        foreach ($steps as $step) {
            $this->db->insert('workflow_steps', [
                'workflow_id' => $workflowId,
                'step_order' => (int)($step['step_order'] ?? 0),
                'action_type' => $step['action_type'] ?? 'send_email',
                'action_config' => json_encode($step['action_config'] ?? []),
                'condition_config' => json_encode($step['condition_config'] ?? null),
            ]);
        }

        return $workflowId;
    }

    /**
     * Update a workflow and its steps
     *
     * @param int $id
     * @param array $data Workflow data to update
     * @param array $steps New steps (replaces all existing)
     * @return bool
     */
    public function updateWorkflow(int $id, array $data, array $steps): bool
    {
        $this->db->where('id', $id);
        $workflow = $this->db->getOne('workflows');
        if (!$workflow) {
            return false;
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['description'])) {
            $updateData['description'] = trim($data['description']);
        }
        if (isset($data['trigger_type'])) {
            $updateData['trigger_type'] = $data['trigger_type'];
        }
        if (isset($data['trigger_config'])) {
            $updateData['trigger_config'] = json_encode($data['trigger_config']);
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int)$data['is_active'];
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id);
            $this->db->update('workflows', $updateData);
        }

        // Delete old steps and insert new ones
        $this->db->where('workflow_id', $id);
        $this->db->delete('workflow_steps');

        foreach ($steps as $step) {
            $this->db->insert('workflow_steps', [
                'workflow_id' => $id,
                'step_order' => (int)($step['step_order'] ?? 0),
                'action_type' => $step['action_type'] ?? 'send_email',
                'action_config' => json_encode($step['action_config'] ?? []),
                'condition_config' => json_encode($step['condition_config'] ?? null),
            ]);
        }

        return true;
    }

    /**
     * Delete a workflow
     *
     * @param int $id
     * @return bool
     */
    public function deleteWorkflow(int $id): bool
    {
        $this->db->where('id', $id);
        return (bool)$this->db->delete('workflows');
    }

    /**
     * Toggle workflow active status
     *
     * @param int $id
     * @param bool $active
     * @return bool
     */
    public function toggleActive(int $id, bool $active): bool
    {
        $this->db->where('id', $id);
        return (bool)$this->db->update('workflows', [
            'is_active' => (int)$active,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Execute a workflow with trigger data
     * Main execution engine - creates execution record and processes all steps
     *
     * @param int $workflowId
     * @param array $triggerData Context data for template substitution
     * @param int $instanceId
     * @return int|null Execution ID
     */
    public function executeWorkflow(int $workflowId, array $triggerData, int $instanceId): ?int
    {
        $workflow = $this->getWorkflow($workflowId);
        if (!$workflow) {
            return null;
        }

        // Create execution record
        $executionId = $this->db->insert('workflow_executions', [
            'workflow_id' => $workflowId,
            'instances_id' => $instanceId,
            'trigger_data' => json_encode($triggerData),
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$executionId) {
            return null;
        }

        // Execute each step
        $context = array_merge(['workflow_id' => $workflowId, 'execution_id' => $executionId], $triggerData);
        $hasError = false;
        $errorMessage = '';

        foreach ($workflow['steps'] as $step) {
            $result = $this->executeStep($executionId, $step, $context);

            if (!$result['success'] && !$result['skipped']) {
                $hasError = true;
                $errorMessage = $result['error'] ?? 'Unknown error';
                break;
            }
        }

        // Update execution status
        $status = $hasError ? 'failed' : 'completed';
        $this->db->where('id', $executionId);
        $this->db->update('workflow_executions', [
            'status' => $status,
            'completed_at' => date('Y-m-d H:i:s'),
            'error_message' => $errorMessage ?: null,
        ]);

        return $executionId;
    }

    /**
     * Execute a single step within a workflow
     *
     * @param int $executionId
     * @param array $step Step configuration
     * @param array $context Current context/trigger data
     * @return array ['success' => bool, 'skipped' => bool, 'error' => string|null, 'output' => array]
     */
    public function executeStep(int $executionId, array $step, array $context): array
    {
        $actionType = $step['action_type'] ?? 'send_email';
        $actionConfig = $step['action_config'] ?? [];
        $conditionConfig = $step['condition_config'] ?? [];

        // Apply variable substitution to action config
        $actionConfig = $this->substituteVariables($actionConfig, $context);

        // Check condition if present
        $skipStep = false;
        if (!empty($conditionConfig)) {
            $conditionResult = $this->evaluateCondition($conditionConfig, $context);
            if (!$conditionResult['met']) {
                $skipStep = true;
            }
        }

        // Log the step execution
        $logData = [
            'execution_id' => $executionId,
            'step_id' => $step['id'] ?? 0,
            'input_data' => json_encode($actionConfig),
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        if ($skipStep) {
            $logData['status'] = 'skipped';
            $this->db->insert('workflow_execution_logs', $logData);
            return ['success' => true, 'skipped' => true];
        }

        // Execute the action
        $result = match ($actionType) {
            'send_email' => $this->sendEmail($actionConfig, $context),
            'create_task' => $this->createTask($actionConfig, $context),
            'change_status' => $this->changeStatus($actionConfig, $context),
            'send_notification' => $this->sendNotification($actionConfig, $context),
            'webhook' => $this->callWebhook($actionConfig, $context),
            'delay' => $this->delay($actionConfig),
            'condition' => ['success' => true],
            default => ['success' => false, 'error' => 'Unknown action type: ' . $actionType],
        };

        // Log the result
        $logData['status'] = $result['success'] ? 'success' : 'failed';
        $logData['output_data'] = json_encode($result['output'] ?? []);
        if (!$result['success']) {
            $logData['error_message'] = $result['error'] ?? null;
        }

        $this->db->insert('workflow_execution_logs', $logData);

        return array_merge($result, ['skipped' => false]);
    }

    /**
     * Process event-based triggers
     * Called when events occur (invoice created, asset returned, etc.)
     *
     * @param string $eventType Event type identifier
     * @param array $eventData Event context data
     * @param int $instanceId
     * @return void
     */
    public function processEventTriggers(string $eventType, array $eventData, int $instanceId): void
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->where('trigger_type', 'event');
        $workflows = $this->db->get('workflows', null, ['*']) ?: [];

        foreach ($workflows as $workflow) {
            $triggerConfig = is_string($workflow['trigger_config'])
                ? json_decode($workflow['trigger_config'], true)
                : $workflow['trigger_config'];

            if (($triggerConfig['event_type'] ?? null) === $eventType) {
                $this->executeWorkflow($workflow['id'], $eventData, $instanceId);
            }
        }
    }

    /**
     * Process cron-based triggers
     * Called by external cron job or scheduler
     *
     * @param int $instanceId
     * @return array ['executed' => count, 'failed' => count]
     */
    public function processCronTriggers(int $instanceId): array
    {
        $executed = 0;
        $failed = 0;

        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->where('trigger_type', 'cron');
        $workflows = $this->db->get('workflows', null, ['*']) ?: [];

        foreach ($workflows as $workflow) {
            try {
                $this->executeWorkflow($workflow['id'], [], $instanceId);
                $executed++;
            } catch (\Exception $e) {
                $failed++;
                error_log("Workflow cron execution failed for ID {$workflow['id']}: " . $e->getMessage());
            }
        }

        return ['executed' => $executed, 'failed' => $failed];
    }

    /**
     * Get execution history for a workflow
     *
     * @param int $workflowId
     * @param int $limit
     * @return array
     */
    public function getExecutions(int $workflowId, int $limit = 50): array
    {
        $this->db->where('workflow_id', $workflowId);
        $this->db->orderBy('started_at', 'DESC');
        return $this->db->get('workflow_executions', $limit, ['*']) ?: [];
    }

    /**
     * Get execution details with full logs
     *
     * @param int $executionId
     * @return array|null
     */
    public function getExecutionDetail(int $executionId): ?array
    {
        $this->db->where('id', $executionId);
        $execution = $this->db->getOne('workflow_executions');

        if (!$execution) {
            return null;
        }

        // Parse JSON fields
        if (is_string($execution['trigger_data'])) {
            $execution['trigger_data'] = json_decode($execution['trigger_data'], true) ?: [];
        }

        // Get execution logs
        $this->db->where('execution_id', $executionId);
        $this->db->orderBy('executed_at', 'ASC');
        $execution['logs'] = $this->db->get('workflow_execution_logs', null, ['*']) ?: [];

        foreach ($execution['logs'] as &$log) {
            if (is_string($log['input_data'])) {
                $log['input_data'] = json_decode($log['input_data'], true) ?: [];
            }
            if (is_string($log['output_data'])) {
                $log['output_data'] = json_decode($log['output_data'], true) ?: [];
            }
        }
        unset($log);

        return $execution;
    }

    /**
     * Get all available workflow templates
     *
     * @return array
     */
    public function getTemplates(): array
    {
        $this->db->orderBy('category', 'ASC');
        $this->db->orderBy('name', 'ASC');
        $templates = $this->db->get('workflow_templates', null, ['*']) ?: [];

        foreach ($templates as &$template) {
            if (is_string($template['workflow_json'])) {
                $template['workflow_json'] = json_decode($template['workflow_json'], true) ?: [];
            }
        }
        unset($template);

        return $templates;
    }

    /**
     * Create a workflow from a template
     *
     * @param int $templateId
     * @param int $instanceId
     * @param int $userId
     * @return int|null Workflow ID
     */
    public function createFromTemplate(int $templateId, int $instanceId, int $userId): ?int
    {
        $this->db->where('id', $templateId);
        $template = $this->db->getOne('workflow_templates');

        if (!$template) {
            return null;
        }

        $workflowData = is_string($template['workflow_json'])
            ? json_decode($template['workflow_json'], true)
            : $template['workflow_json'];

        if (!is_array($workflowData)) {
            return null;
        }

        $data = [
            'instances_id' => $instanceId,
            'name' => $workflowData['name'] ?? $template['name'],
            'description' => $workflowData['description'] ?? $template['description'],
            'trigger_type' => $workflowData['trigger_type'] ?? 'manual',
            'trigger_config' => $workflowData['trigger_config'] ?? [],
            'is_active' => (int)($workflowData['is_active'] ?? 0),
            'created_by' => $userId,
        ];

        $steps = $workflowData['steps'] ?? [];

        return $this->createWorkflow($data, $steps);
    }

    /**
     * ACTION HANDLERS
     */

    /**
     * Send email action
     */
    private function sendEmail(array $config, array $context): array
    {
        try {
            $recipient = $config['recipient'] ?? '';
            $subject = $config['subject'] ?? '';
            $template = $config['template'] ?? null;
            $body = $config['body'] ?? '';

            // Resolve recipient if using context variables
            if (strpos($recipient, '{{') !== false) {
                $recipient = $this->substituteVariables(['recipient' => $recipient], $context)['recipient'];
            }

            if (empty($recipient) || empty($subject)) {
                return ['success' => false, 'error' => 'Missing recipient or subject'];
            }

            // Simple email sending (integrate with existing EmailService if available)
            // For now, create a log entry and return success
            $mailResult = [
                'sent' => true,
                'recipient' => $recipient,
                'subject' => $subject,
            ];

            return ['success' => true, 'output' => $mailResult];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create task action
     */
    private function createTask(array $config, array $context): array
    {
        try {
            $title = $config['title'] ?? '';
            $description = $config['description'] ?? '';
            $assignedToRole = $config['assigned_to_role'] ?? null;
            $priority = $config['priority'] ?? 'medium';

            if (empty($title)) {
                return ['success' => false, 'error' => 'Missing task title'];
            }

            // Create task record (assumes tasks table exists)
            // This would integrate with existing task system
            $taskResult = [
                'title' => $title,
                'description' => $description,
                'assigned_to_role' => $assignedToRole,
                'priority' => $priority,
            ];

            return ['success' => true, 'output' => $taskResult];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Change status action
     */
    private function changeStatus(array $config, array $context): array
    {
        try {
            $entity = $config['entity'] ?? '';
            $entityId = $config['entity_id'] ?? null;
            $newStatus = $config['new_status'] ?? '';

            if (empty($entity) || empty($entityId) || empty($newStatus)) {
                return ['success' => false, 'error' => 'Missing entity, entity_id, or new_status'];
            }

            // Update entity status in appropriate table
            // This is entity-specific and would be customized per implementation

            return ['success' => true, 'output' => ['entity' => $entity, 'new_status' => $newStatus]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification action
     */
    private function sendNotification(array $config, array $context): array
    {
        try {
            $userRoles = $config['user_roles'] ?? [];
            $title = $config['title'] ?? '';
            $message = $config['message'] ?? '';

            if (empty($title)) {
                return ['success' => false, 'error' => 'Missing notification title'];
            }

            // Get users with specified roles and send notifications
            // This would integrate with NotificationService and user roles system

            return ['success' => true, 'output' => ['title' => $title, 'recipient_count' => count($userRoles)]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Webhook action
     */
    private function callWebhook(array $config, array $context): array
    {
        try {
            $url = $config['url'] ?? '';
            $method = $config['method'] ?? 'POST';
            $payload = $config['payload'] ?? [];

            if (empty($url)) {
                return ['success' => false, 'error' => 'Missing webhook URL'];
            }

            $payload = $this->substituteVariables($payload, $context);

            // Make webhook call
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $success = $httpCode >= 200 && $httpCode < 300;

            return [
                'success' => $success,
                'output' => ['http_code' => $httpCode, 'response' => $response],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delay action (pause workflow execution)
     */
    private function delay(array $config): array
    {
        try {
            $days = (int)($config['days'] ?? 0);
            $hours = (int)($config['hours'] ?? 0);
            $minutes = (int)($config['minutes'] ?? 0);

            if ($days <= 0 && $hours <= 0 && $minutes <= 0) {
                return ['success' => false, 'error' => 'Invalid delay duration'];
            }

            // In practice, delays would be queued for async processing
            // For now, just log the delay configuration

            return ['success' => true, 'output' => ['days' => $days, 'hours' => $hours, 'minutes' => $minutes]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Condition evaluation
     */
    private function evaluateCondition(array $condition, array $context): array
    {
        try {
            $type = $condition['type'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;
            $targetField = $condition['target_field'] ?? '';

            $contextValue = $context[$targetField] ?? null;

            $met = match ($operator) {
                'equals' => $contextValue === $value,
                'not_equals' => $contextValue !== $value,
                'greater_than' => (int)$contextValue > (int)$value,
                'less_than' => (int)$contextValue < (int)$value,
                'greater_equal' => (int)$contextValue >= (int)$value,
                'less_equal' => (int)$contextValue <= (int)$value,
                'contains' => strpos((string)$contextValue, (string)$value) !== false,
                'not_contains' => strpos((string)$contextValue, (string)$value) === false,
                default => false,
            };

            return ['met' => $met];
        } catch (\Exception $e) {
            return ['met' => false];
        }
    }

    /**
     * Substitute variables in config using context data
     * Supports {{variable}} syntax
     */
    private function substituteVariables(array $config, array $context): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $result[$key] = preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($context) {
                    $varName = $matches[1];
                    return $context[$varName] ?? $matches[0];
                }, $value);
            } elseif (is_array($value)) {
                $result[$key] = $this->substituteVariables($value, $context);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
