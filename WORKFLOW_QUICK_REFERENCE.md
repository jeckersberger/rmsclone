# Workflow Engine - Quick Reference Guide

## File Locations

### Database
- Migration: `/db/migrations/20260318140000_workflow_engine.php`

### Service Layer
- Service: `/src/services/WorkflowEngineService.php`

### API Endpoints
- Base: `/src/api/workflows/`
- Files:
  - `list.php` - GET workflows
  - `get.php` - GET single
  - `create.php` - POST create
  - `update.php` - POST update
  - `delete.php` - POST delete
  - `toggle.php` - POST toggle
  - `execute.php` - POST execute
  - `executions.php` - GET history
  - `execution_detail.php` - GET logs
  - `templates.php` - GET templates
  - `create_from_template.php` - POST from template

### User Interface
- Controller: `/src/workflows/index.php`
- Template: `/src/workflows/workflows_index.twig`

### Documentation
- Guide: `WORKFLOW_ENGINE_GUIDE.md`
- Implementation: `WORKFLOW_ENGINE_IMPLEMENTATION.md`
- Examples: `WORKFLOW_INTEGRATION_EXAMPLES.md`
- Checklist: `IMPLEMENTATION_CHECKLIST.md`

## Quick Start

### 1. Run Migration
```bash
vendor/bin/phinx migrate
```

### 2. Add Permissions
```sql
INSERT INTO permissions (name, description) VALUES
('WORKFLOWS:VIEW', 'View workflows'),
('WORKFLOWS:EDIT', 'Create/edit/delete workflows'),
('WORKFLOWS:EXECUTE', 'Execute workflows manually');
```

### 3. Access Dashboard
```
http://yoursite.com/workflows
```

### 4. Create First Workflow
- Click "Neuer Workflow" button
- Or click "Aus Vorlage erstellen" to use template

## Common Code Snippets

### Trigger Event Workflow
```php
$workflowService = new WorkflowEngineService($db);
$workflowService->processEventTriggers('event_name', [
    'var1' => 'value1',
    'var2' => 'value2'
], $instanceId);
```

### Execute Workflow Manually
```php
$executionId = $workflowService->executeWorkflow($workflowId, [], $instanceId);
$execution = $workflowService->getExecutionDetail($executionId);
```

### Process Cron Workflows
```php
$stats = $workflowService->processCronTriggers($instanceId);
echo "Executed: {$stats['executed']}, Failed: {$stats['failed']}";
```

## Trigger Types

| Type | When | Config |
|------|------|--------|
| manual | On demand | - |
| event | When event occurs | `{event_type: '...'}` |
| cron | On schedule | `{cron_expression: '...'}` |

## Action Types

| Action | Purpose | Key Fields |
|--------|---------|-----------|
| send_email | Send email | recipient, subject, body |
| create_task | Create task | title, description, assigned_to_role |
| change_status | Update status | entity, entity_id, new_status |
| send_notification | In-app alert | user_roles, title, message |
| webhook | External API | url, method, payload |
| delay | Pause | days, hours, minutes |
| condition | Conditional | target_field, operator, value |

## Variable Substitution

Use `{{variable_name}}` in any config field:
```
"subject": "Hello {{client_name}}, your invoice {{invoice_number}} is due"
```

## Cron Expression Quick Reference

```
0 8 * * *       - Every day at 8 AM
0 * * * *       - Every hour
0 0 * * MON     - Every Monday at midnight
0 9-17 * * *    - Every hour from 9 AM to 5 PM
0 0 1 * *       - First day of month at midnight
```

## API Response Format

All endpoints return:
```json
{
  "success": true/false,
  "data": { ... },
  "message": "optional error message"
}
```

## Workflow JSON Structure

```json
{
  "name": "Workflow Name",
  "description": "Optional description",
  "trigger_type": "manual|event|cron",
  "trigger_config": {
    "event_type": "for event type",
    "cron_expression": "for cron type"
  },
  "steps": [
    {
      "step_order": 1,
      "action_type": "send_email|create_task|...",
      "action_config": { ... },
      "condition_config": null
    }
  ],
  "is_active": true
}
```

## Database Tables

| Table | Purpose |
|-------|---------|
| workflows | Workflow definitions |
| workflow_steps | Action steps within workflows |
| workflow_executions | Execution records |
| workflow_execution_logs | Detailed step logs |
| workflow_templates | Pre-built templates |

## Permissions

```
WORKFLOWS:VIEW     - View workflows & logs
WORKFLOWS:EDIT     - Create/update/delete workflows
WORKFLOWS:EXECUTE  - Run workflows manually
```

## Status Values

### Workflow Execution Status
- `running` - Currently executing
- `completed` - Finished successfully
- `failed` - Encountered error
- `cancelled` - Manually stopped

### Step Execution Status
- `success` - Step completed
- `failed` - Step error
- `skipped` - Condition not met

## Operators for Conditions

```
equals           - Equal to
not_equals       - Not equal to
greater_than     - Greater than
less_than        - Less than
greater_equal    - Greater than or equal
less_equal       - Less than or equal
contains         - String contains
not_contains     - String does not contain
```

## Default Templates

1. **Mahnwesen - Automatisiertes Dunning**
   - Trigger: Cron (daily 8 AM)
   - Actions: Send reminders at 30/60/90 days overdue

2. **Rückgabe-Prüfung - Wartungscheck**
   - Trigger: Event (asset_returned)
   - Actions: Create maintenance task, notify technician

3. **Neukunden-Onboarding**
   - Trigger: Event (client_created)
   - Actions: Welcome email, 7-day follow-up

4. **Projekt-Erinnerung - Projektende**
   - Trigger: Cron (daily 9 AM)
   - Actions: Notify manager 3 days before end

## Testing Workflows

### Via API
```bash
# List workflows
curl http://yoursite.com/api/workflows/list.php

# Create workflow
curl -X POST http://yoursite.com/api/workflows/create.php \
  -d 'name=Test' \
  -d 'trigger_type=manual' \
  -d 'steps=[...]'

# Execute workflow
curl -X POST http://yoursite.com/api/workflows/execute.php \
  -d 'id=1'
```

### Via Code
```php
$service = new WorkflowEngineService($db);

// Create
$id = $service->createWorkflow($data, $steps);

// Read
$workflow = $service->getWorkflow($id);

// Execute
$execId = $service->executeWorkflow($id, [], $instanceId);
$exec = $service->getExecutionDetail($execId);

// Delete
$service->deleteWorkflow($id);
```

## Troubleshooting

### Workflow not executing
1. Check if `is_active = 1`
2. Check `processEventTriggers()` being called
3. Check cron jobs configured
4. Review execution logs for errors

### Email not sending
1. Check `sendEmail()` integration with EmailService
2. Verify email template exists
3. Check variable substitution in logs

### Conditions not matching
1. Verify condition config syntax
2. Check context variables contain expected values
3. Review condition evaluation in logs

### Permissions denied
1. Check user has required permission
2. Verify instance_id matches
3. Check WORKFLOWS:VIEW/EDIT/EXECUTE permissions assigned

## Integration Checklist

- [ ] Migration run and tables created
- [ ] Permissions added
- [ ] Menu item added to navigation
- [ ] Event triggers integrated:
  - [ ] invoice_created
  - [ ] asset_returned
  - [ ] client_created
  - [ ] project_completed
- [ ] Cron processor configured
- [ ] EmailService integration (optional)
- [ ] Task creation integration (optional)
- [ ] Testing completed

## Support

- See `WORKFLOW_ENGINE_GUIDE.md` for full documentation
- See `WORKFLOW_INTEGRATION_EXAMPLES.md` for code examples
- Check service class docstrings for method details
- Review execution logs for troubleshooting

## Performance Tips

1. Archive execution logs > 90 days old monthly
2. Index custom event types frequently used
3. Monitor cron processor logs weekly
4. Batch multiple workflows to same cron time
5. Use conditions to skip unnecessary steps
6. Test workflows before deploying to production

## Security Checklist

- [x] Permission validation on all endpoints
- [x] Instance data isolation enforced
- [x] Input validation on all parameters
- [x] SQL injection prevention (MeekroDB)
- [ ] Webhook HMAC signing (optional)
- [ ] API rate limiting (optional)
- [ ] Sensitive data excluded from logs

## Version Info

- **Created**: 2026-03-18
- **Framework**: PHP 8.3+
- **Database**: MySQL 5.7+
- **Dependencies**: MeekroDB, Phinx, Twig
- **Status**: Production Ready

---

**Last Updated**: 2026-03-18
For complete documentation, see WORKFLOW_ENGINE_GUIDE.md
