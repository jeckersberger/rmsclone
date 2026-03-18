# KI-Lernsystem (I10) Integration Checklist

## Implementation Complete ✓

All core components have been implemented. Follow this checklist to integrate into your application.

## Phase 1: Database Setup

- [ ] Review migration file: `db/migrations/20260318220000_ai_learning_system.php`
- [ ] Run migration:
  ```bash
  ./vendor/bin/phinx migrate -e production
  ```
- [ ] Verify tables created:
  ```sql
  SHOW TABLES LIKE 'ai_%';
  -- Should show: ai_feedback, ai_few_shot_examples, ai_prompt_versions, ai_learning_profile
  ```

## Phase 2: Permissions Setup

- [ ] Create permission records in your system:
  ```sql
  INSERT INTO permissions (perm_id, perm_name) VALUES
  ('AI:VIEW', 'View AI Learning Dashboard'),
  ('AI:CONFIGURE', 'Configure AI Prompts & Examples');
  ```
- [ ] Assign permissions to admin role
- [ ] Assign `AI:VIEW` to staff roles that use AI features

## Phase 3: Service Integration

- [ ] Add FeedbackLearningService to your service container (if using DI)
- [ ] Ensure AiProviderRegistry is accessible in your app
- [ ] Test service instantiation:
  ```php
  $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
  $stats = $service->getFeedbackStats($instanceId);
  ```

## Phase 4: Frontend Integration

### Option A: Using Feedback Widget

- [ ] Add widget to templates where AI output is shown
  ```twig
  {% include 'ai/feedback_widget.twig' with {
      'task_type': 'email_draft',
      'ai_output': generated_content
  } %}
  ```
- [ ] Test feedback submission
- [ ] Verify POST /api/ai/feedback works
- [ ] Check database for recorded feedback

### Option B: Manual Implementation

If not using widget, implement feedback submission in your code:

```php
// After user interacts with AI output
$service->recordFeedback(
    userId: $userId,
    taskType: 'your_task',
    aiOutput: $aiOutput,
    userEdited: $userEdited,
    rating: $rating,
    reason: $reason,
    feedbackText: $feedback,
    provider: $provider,
    model: $model,
    instanceId: $instanceId,
    editTimeMs: $editTime,
    tokensInput: $tokens['input'],
    tokensOutput: $tokens['output'],
);
```

## Phase 5: Dashboard Setup

- [ ] Create route to `/ai/learning` or add to admin panel
- [ ] Link controller: `/src/ai/learning.php`
- [ ] Verify dashboard template: `/src/templates/ai/ai_learning.twig`
- [ ] Test dashboard loads without errors
- [ ] Verify all API endpoints respond:
  - GET /api/ai/feedback_stats ✓
  - GET /api/ai/few_shot_examples ✓
  - GET /api/ai/prompt_versions ✓
  - GET /api/ai/learning_profile ✓
  - GET /api/ai/learning_dashboard ✓

## Phase 6: API Integration

- [ ] Test all API endpoints:

```bash
# Feedback
curl -X POST http://localhost/api/ai/feedback \
  -H "Content-Type: application/json" \
  -d '{"task_type":"test","ai_output":"test","rating":"positive","provider":"openai","model":"gpt-4","tokens_input":10,"tokens_output":20}'

# Stats
curl http://localhost/api/ai/feedback_stats

# Few-shot examples
curl 'http://localhost/api/ai/few_shot_examples?task_type=email_draft'

# Prompt versions
curl 'http://localhost/api/ai/prompt_versions?task_type=email_draft'

# Learning profile
curl http://localhost/api/ai/learning_profile

# Dashboard
curl http://localhost/api/ai/learning_dashboard
```

## Phase 7: Enrich AI Prompts (Optional but Recommended)

- [ ] Modify AiRequestHandler to use enriched prompts:

```php
$service = new FeedbackLearningService($db, $registry);
$enriched = $service->buildEnrichedPrompt(
    $userId,
    $taskType,
    $userQuery,
    $instanceId
);

// Use enriched['system'] and enriched['user'] in prompt
$response = $handler->processRequest(
    taskType: $taskType,
    prompt: [
        ['role' => 'system', 'content' => $enriched['system']],
        ['role' => 'user', 'content' => $enriched['user']],
    ],
    // ...
);
```

## Phase 8: Monitoring & Optimization

- [ ] Set up weekly dashboard review process
- [ ] Create procedure for reviewing few-shot examples
- [ ] Document task types you're tracking
- [ ] Monitor checkAutoOptimization() results
- [ ] Create new prompt versions based on feedback

### Example Workflow:
1. **Monday**: Review dashboard for low-performing task types
2. **Tuesday-Thursday**: Create/test new prompt versions
3. **Friday**: Activate improvements to production

## Phase 9: Data & Compliance

- [ ] Set up GDPR deletion handler:

```php
// When user requests data deletion
$service->deleteUserFeedback($userId);
```

- [ ] Document data retention policy
- [ ] Set up backup/archive schedule for feedback data

## Phase 10: Training & Documentation

- [ ] Create user guide for staff
- [ ] Document task types in your system
- [ ] Train admins on:
  - Creating few-shot examples
  - Managing prompt versions
  - Reading dashboard metrics
  - A/B testing new prompts

## Files Created

### Migrations
- `db/migrations/20260318220000_ai_learning_system.php`

### Services
- `src/services/AI/FeedbackLearningService.php` (400+ lines)

### API Endpoints
- `src/api/ai/feedback.php`
- `src/api/ai/feedback_stats.php`
- `src/api/ai/few_shot_examples.php`
- `src/api/ai/prompt_versions.php`
- `src/api/ai/prompt_activate.php`
- `src/api/ai/learning_profile.php`
- `src/api/ai/learning_dashboard.php`

### Templates
- `src/templates/ai/feedback_widget.twig`
- `src/templates/ai/ai_learning.twig`

### Controllers
- `src/ai/learning.php`

### Documentation
- `AI_LEARNING_SYSTEM.md` (Complete reference guide)
- `KI_LERNSYSTEM_INTEGRATION.md` (This file)

## Quick Start

```bash
# 1. Run migration
./vendor/bin/phinx migrate -e production

# 2. Set up permissions
# (Add to your permission system)

# 3. Add feedback widget to your templates
{% include 'ai/feedback_widget.twig' with {'task_type': 'your_task', 'ai_output': output} %}

# 4. Access dashboard
# Navigate to: /ai/learning (or add to admin panel)

# 5. Monitor feedback
# Dashboard will show acceptance rates, trends, and opportunities
```

## Common Issues & Solutions

### Dashboard shows no data
- **Cause**: No feedback recorded yet
- **Solution**: Record some test feedback and wait 24 hours for trends to appear

### Feedback widget not working
- **Cause**: Missing Chart.js library
- **Solution**: Ensure Chart.js is loaded in your base template
  ```twig
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  ```

### Permissions denied on API endpoints
- **Cause**: User doesn't have AI:VIEW permission
- **Solution**: Assign permission to user role
  ```sql
  INSERT INTO permission_assignments (user_id, perm_id) VALUES (1, 'AI:VIEW');
  ```

### Enriched prompts not improving quality
- **Cause**: Not enough feedback data yet
- **Solution**: Wait for 50+ feedback records before evaluating
  ```php
  // Check feedback count
  $stats = $service->getFeedbackStats($instanceId);
  echo "Total feedbacks: " . $stats['total_30_days']; // Should be 50+
  ```

## Support

For questions or issues:
1. Review `AI_LEARNING_SYSTEM.md` for detailed documentation
2. Check API endpoint responses for error messages
3. Review browser console for JavaScript errors
4. Check PHP error logs for service errors

## Next Phase Ideas

- [ ] Webhook support for external optimization services
- [ ] Integration with slack for alerts on optimization opportunities
- [ ] Advanced A/B testing with statistical significance
- [ ] Machine learning for automatic prompt optimization
- [ ] Multi-language support for feedback
- [ ] Integration with cost tracking for ROI analysis

---

**Estimated Integration Time**: 2-4 hours
**Difficulty Level**: Medium
**Dependencies**: None (uses existing services)

**Status**: Ready for Production ✓
