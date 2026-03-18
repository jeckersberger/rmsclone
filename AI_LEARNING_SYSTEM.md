# KI-Lernsystem (I10) - AI Learning System Implementation

## Overview

The AI Learning System (KI-Lernsystem) is a comprehensive framework for collecting feedback on AI outputs, learning from user interactions, and continuously improving AI performance through prompt optimization and few-shot example curation.

**Component**: I10
**Status**: Implementation Complete
**Tech Stack**: PHP 8.3, MysqliDb, Phinx, Twig, AdminLTE3

## Key Features

### 1. Feedback Collection
- User ratings: positive, negative, neutral
- Feedback reasons with predefined options
- User corrections (edited versions)
- Time tracking (how long users spend editing)
- Token tracking (input/output tokens from providers)

### 2. Acceptance Rate Tracking
- Calculate acceptance rates by task type
- Trend analysis (30-day vs 60-90 day comparison)
- Identify improving and declining task types
- Weekly trend data for charting

### 3. Few-Shot Example Management
- Curate high-quality input/output examples
- Community voting system (positive/negative votes)
- Usage tracking (how often each example is used)
- Automatic inclusion in prompts for in-context learning

### 4. Prompt Version Control
- Create and manage prompt versions
- A/B testing support (split traffic between versions)
- Acceptance rate tracking per version
- Change source tracking (manual, automatic, A/B test)
- Activation/deactivation of versions

### 5. Learning Profile
- Analyze user feedback patterns
- Detect preferences (length, style, detail level)
- Confidence scoring (0.0-1.0)
- Automatic enrichment of prompts with learned preferences

### 6. Automatic Optimization Detection
- Monitor feedback patterns
- Flag task types with >70% negative feedback and same reason
- Suggest prompt changes when optimization is needed

### 7. Dashboard & Visualization
- Acceptance rate trend charts (6-month history)
- Top improvements list
- Areas needing attention
- Few-shot examples library
- Prompt version history
- Learning profile display

## Database Schema

### ai_feedback
Records user feedback on AI outputs with context for learning.

```sql
ai_feedback (
  id INT PRIMARY KEY,
  user_id INT,
  instance_id INT,
  task_type VARCHAR(50),
  ai_output TEXT,
  user_edited TEXT nullable,
  rating ENUM('positive','negative','neutral'),
  feedback_reason VARCHAR(100) nullable,
  feedback_text TEXT nullable,
  accepted BOOLEAN DEFAULT FALSE,
  edit_time_ms INT nullable,
  provider VARCHAR(50),
  model VARCHAR(100),
  prompt_version INT DEFAULT 1,
  tokens_input INT,
  tokens_output INT,
  created_at TIMESTAMP
)
```

### ai_few_shot_examples
High-quality input/output pairs for in-context learning.

```sql
ai_few_shot_examples (
  id INT PRIMARY KEY,
  instance_id INT,
  task_type VARCHAR(50),
  input_context TEXT,
  output_example TEXT,
  positive_votes INT DEFAULT 0,
  negative_votes INT DEFAULT 0,
  usage_count INT DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP
)
```

### ai_prompt_versions
Version control for system prompts with A/B testing.

```sql
ai_prompt_versions (
  id INT PRIMARY KEY,
  task_type VARCHAR(50),
  version INT,
  system_prompt TEXT,
  change_reason TEXT nullable,
  change_source ENUM('manual','automatic','ab_test'),
  acceptance_rate DECIMAL(5,2) nullable,
  total_uses INT DEFAULT 0,
  is_active BOOLEAN DEFAULT FALSE,
  is_ab_test BOOLEAN DEFAULT FALSE,
  ab_test_traffic_pct INT DEFAULT 50,
  created_at TIMESTAMP
)
```

### ai_learning_profile
Learned preferences and patterns about users/instances.

```sql
ai_learning_profile (
  id INT PRIMARY KEY,
  instance_id INT,
  profile_key VARCHAR(100),
  profile_value TEXT,
  confidence DECIMAL(3,2),
  data_points INT DEFAULT 0,
  last_updated TIMESTAMP,
  UNIQUE(instance_id, profile_key)
)
```

## Service Classes

### FeedbackLearningService

Main service for managing feedback and learning. Located at:
`/src/services/AI/FeedbackLearningService.php` (~400 lines)

#### Key Methods

```php
// Record feedback on an AI output
recordFeedback(
    int $userId,
    string $taskType,
    string $aiOutput,
    ?string $userEdited,
    string $rating,
    ?string $reason,
    ?string $feedbackText,
    string $provider,
    string $model,
    int $instanceId,
    ?int $editTimeMs = null,
    int $tokensInput = 0,
    int $tokensOutput = 0,
): int

// Get acceptance rate for a task type
getAcceptanceRate(
    string $taskType,
    int $instanceId,
    ?int $days = 30,
): float

// Get comprehensive dashboard statistics
getFeedbackStats(int $instanceId): array

// Add a new few-shot example
addFewShotCandidate(
    string $taskType,
    string $inputContext,
    string $output,
    int $instanceId,
): int

// Get best few-shot examples for a task type
getBestFewShotExamples(
    string $taskType,
    int $limit = 3,
    int $instanceId = 0,
): array

// Get active prompt for a task type
getPromptVersion(string $taskType, int $instanceId): array

// Create new prompt version
createPromptVersion(
    string $taskType,
    string $systemPrompt,
    string $changeReason,
    string $source,
    int $instanceId = 1,
): int

// Activate a prompt version
activatePromptVersion(int $versionId): bool

// Build enriched prompt with system prompt + examples + learnings
buildEnrichedPrompt(
    int $userId,
    string $taskType,
    string $userQuery,
    int $instanceId,
): array

// Update learning profile based on feedback patterns
updateLearningProfile(
    string $taskType,
    string $rating,
    ?string $reason,
    ?string $userEdited,
    int $instanceId,
): void

// Get learning profile for an instance
getLearningProfile(int $instanceId): array

// Get complete dashboard data
getDashboardData(int $instanceId): array

// Check if optimization is recommended
checkAutoOptimization(string $taskType, int $instanceId): bool

// Delete all user feedback (GDPR)
deleteUserFeedback(int $userId): int
```

## API Endpoints

All endpoints require authentication and `AI:VIEW` permission. Admin features require `AI:CONFIGURE`.

### POST /api/ai/feedback
Record feedback on an AI output.

**Request:**
```json
{
  "task_type": "email_draft",
  "ai_output": "...",
  "user_edited": "..." (optional),
  "rating": "positive|negative|neutral",
  "reason": "too_short" (optional),
  "feedback_text": "..." (optional),
  "provider": "openai",
  "model": "gpt-4",
  "tokens_input": 150,
  "tokens_output": 280,
  "edit_time_ms": 5000 (optional)
}
```

**Response:**
```json
{
  "success": true,
  "feedback_id": 42
}
```

### GET /api/ai/feedback_stats
Get feedback statistics for dashboard.

**Response:**
```json
{
  "success": true,
  "data": {
    "total_30_days": 150,
    "total_90_days": 450,
    "by_rating": {"positive": 120, "negative": 20, "neutral": 10},
    "acceptance_rate_30": 0.8,
    "acceptance_rate_60": 0.75,
    "trend_direction": "up|down|flat",
    "task_types": {...},
    "top_negative_reasons": {...}
  }
}
```

### GET /api/ai/few_shot_examples?task_type=email_draft&limit=5
Get best few-shot examples for a task type.

**Response:**
```json
{
  "success": true,
  "task_type": "email_draft",
  "examples": [...]
}
```

### POST /api/ai/few_shot_examples
Add new few-shot example (admin only).

**Request:**
```json
{
  "task_type": "email_draft",
  "input_context": "...",
  "output_example": "..."
}
```

### PUT /api/ai/few_shot_examples/{id}
Vote on or activate a few-shot example (admin only).

**Request:**
```json
{
  "id": 5,
  "vote": "positive|negative",
  "is_active": true
}
```

### GET /api/ai/prompt_versions?task_type=email_draft
Get all prompt versions for a task type.

**Response:**
```json
{
  "success": true,
  "task_type": "email_draft",
  "versions": [...]
}
```

### POST /api/ai/prompt_versions
Create new prompt version (admin only).

**Request:**
```json
{
  "task_type": "email_draft",
  "system_prompt": "...",
  "change_reason": "Improved clarity",
  "change_source": "manual|automatic|ab_test"
}
```

### POST /api/ai/prompt_activate
Activate a prompt version (admin only).

**Request:**
```json
{
  "version_id": 5
}
```

### GET /api/ai/learning_profile
Get learning profile for current instance.

**Response:**
```json
{
  "success": true,
  "profile": [
    {
      "profile_key": "length_preference",
      "profile_value": "concise",
      "confidence": 0.85,
      "data_points": 42
    }
  ]
}
```

### GET /api/ai/learning_dashboard
Get complete dashboard data.

**Response:**
```json
{
  "success": true,
  "data": {
    "stats": {...},
    "trend_data": [...],
    "improvements": [...],
    "needs_attention": [...],
    "learning_profile": [...]
  }
}
```

## Frontend Components

### Feedback Widget (Reusable)
Located at: `/src/templates/ai/feedback_widget.twig`

Include in any template where AI output is shown:
```twig
{% include 'ai/feedback_widget.twig' with {
    'task_type': 'email_draft',
    'ai_output': ai_generated_email
} %}
```

Features:
- Thumbs up/down/neutral buttons
- Feedback reason dropdown
- Optional edited version textarea
- Direct API submission
- Success confirmation

### Dashboard
Located at: `/src/templates/ai/ai_learning.twig`

Displays:
- Key metrics (acceptance rate, feedback count, task types)
- 6-month acceptance rate trend chart
- Top improvements table
- Areas needing attention table
- Few-shot examples library with voting
- Prompt version history with activation
- Learning profile display
- Admin modals for adding examples and creating prompts

### Controller
Located at: `/src/ai/learning.php`

Routes to `/ai/learning` (or configure via URL routing)

## Integration Examples

### 1. Adding Feedback to an Email Draft Feature

```php
// After user accepts/edits an AI-generated email

$service = new FeedbackLearningService($db, new AiProviderRegistry($db));

$feedbackId = $service->recordFeedback(
    userId: $user['users_id'],
    taskType: 'email_draft',
    aiOutput: $originalEmail,
    userEdited: $usersCorrectedEmail,
    rating: 'positive',
    reason: null,
    feedbackText: 'Good start, but needs more detail',
    provider: 'openai',
    model: 'gpt-4',
    instanceId: $instanceId,
    editTimeMs: 3500,
    tokensInput: 320,
    tokensOutput: 256,
);
```

### 2. Building an Enriched Prompt

```php
$service = new FeedbackLearningService($db, new AiProviderRegistry($db));

$enriched = $service->buildEnrichedPrompt(
    userId: $user['users_id'],
    taskType: 'email_draft',
    userQuery: 'Write a professional email requesting budget approval',
    instanceId: $instanceId,
);

// Use in AI request
$handler = new AiRequestHandler($db, $registry, $tracker);
$response = $handler->processRequest(
    taskType: 'email_draft',
    prompt: [
        ['role' => 'system', 'content' => $enriched['system']],
        ['role' => 'user', 'content' => $enriched['user']],
    ],
    userId: $user['users_id'],
    instanceId: $instanceId,
);
```

### 3. Creating a New Prompt Version

```php
$service = new FeedbackLearningService($db, new AiProviderRegistry($db));

// Create new version based on feedback analysis
$versionId = $service->createPromptVersion(
    taskType: 'invoice_scan',
    systemPrompt: 'New improved system prompt...',
    changeReason: 'Too many incorrect line item extractions',
    source: 'automatic',
    instanceId: $instanceId,
);

// After testing, activate it
$service->activatePromptVersion($versionId);
```

### 4. Checking for Auto-Optimization

```php
$service = new FeedbackLearningService($db, new AiProviderRegistry($db));

if ($service->checkAutoOptimization('document_check', $instanceId)) {
    // Alert admin: "document_check has 70%+ negative feedback with same reason"
    // Suggest creating a new prompt version
}
```

## Migration

Run Phinx migration to create tables:

```bash
./vendor/bin/phinx migrate -e production
```

Migration file: `db/migrations/20260318220000_ai_learning_system.php`

## Permissions

Create permission entries in your system:

- `AI:VIEW` - View AI learning dashboard, feedback stats, profiles
- `AI:CONFIGURE` - Create/activate prompts, manage few-shot examples

## Configuration

No additional configuration required. The system auto-initializes on first use.

## Performance Considerations

### Indexes
- `ai_feedback`: task_type, rating, created_at, provider
- `ai_few_shot_examples`: task_type, is_active
- `ai_prompt_versions`: task_type, version, is_active
- `ai_learning_profile`: instance_id, profile_key

### Data Cleanup
Consider implementing a cron job to archive old feedback (>1 year):

```php
// Archive feedback older than 1 year
$since = date('Y-m-d H:i:s', strtotime('-1 year'));
$db->where('created_at', $since, '<');
$db->delete('ai_feedback');
```

## GDPR Compliance

The system supports right-to-deletion via:

```php
$service->deleteUserFeedback($userId);
```

This removes all feedback records for a user while preserving aggregated statistics.

## Best Practices

1. **Record Feedback Consistently**: Always call `recordFeedback()` when users interact with AI outputs
2. **Review Dashboard Weekly**: Monitor trends and address low-performing task types
3. **Curate Examples**: Manually review and activate the best few-shot examples
4. **Version Control**: Always document change reasons when creating prompt versions
5. **A/B Test Prompts**: Use the A/B test feature to safely test improvements
6. **Monitor Confidence**: Only use learning profile insights with >70% confidence

## Troubleshooting

### Low Acceptance Rates
1. Check `needs_attention` section in dashboard
2. Review top negative reasons
3. Consider creating a new prompt version
4. Test with few-shot examples

### Few-Shot Examples Not Used
1. Ensure examples are marked `is_active = true`
2. Check `usage_count` - may need more variety
3. Review positive/negative votes

### Learning Profile Not Updating
1. Need at least 3 feedback records
2. Need 3+ feedback records to detect patterns
3. Check confidence threshold (>50%)

## File Listing

```
Migration:
- db/migrations/20260318220000_ai_learning_system.php

Services:
- src/services/AI/FeedbackLearningService.php

API Endpoints:
- src/api/ai/feedback.php
- src/api/ai/feedback_stats.php
- src/api/ai/few_shot_examples.php
- src/api/ai/prompt_versions.php
- src/api/ai/prompt_activate.php
- src/api/ai/learning_profile.php
- src/api/ai/learning_dashboard.php

Templates:
- src/templates/ai/feedback_widget.twig
- src/templates/ai/ai_learning.twig

Controllers:
- src/ai/learning.php
```

## Related Features

- **AiRequestHandler**: Process AI requests with fallback and logging
- **AiProviderRegistry**: Manage multiple AI providers
- **AiUsageTracker**: Track token usage and costs
- **AnonymizationService**: Anonymize data before sending to AI providers

## Next Steps

1. Run migration: `./vendor/bin/phinx migrate -e production`
2. Set up permissions for `AI:VIEW` and `AI:CONFIGURE`
3. Integrate feedback widget into AI output areas
4. Create navigation link to `/ai/learning` dashboard
5. Monitor feedback collection and trends
6. Create/test new prompt versions
7. Activate improvements to production

---

**Implementation Date**: 2026-03-18
**Version**: 1.0.0
**Last Updated**: 2026-03-18
