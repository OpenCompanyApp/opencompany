# QA Strategy

> Comprehensive quality assurance strategy for the OpenCompany application.
>
> This document defines testing standards, coverage requirements, and implementation guidelines.

---

## Table of Contents

1. [Overview](#overview)
2. [Testing Pyramid](#1-testing-pyramid)
3. [Backend Testing](#2-backend-testing)
4. [Frontend Testing](#3-frontend-testing)
5. [End-to-End Testing](#4-end-to-end-testing)
6. [CI/CD Pipeline](#5-cicd-pipeline)
7. [Test Data Management](#6-test-data-management)
8. [Code Quality](#7-code-quality)
9. [Performance Testing](#8-performance-testing)
10. [Implementation Roadmap](#9-implementation-roadmap)

---

## Overview

### Quality Goals

| Metric | Target | Current |
|--------|--------|---------|
| **Test Coverage** | 80% | ~30% (282 tests, 1063 assertions) |
| **API Test Coverage** | 100% | ~40% (controllers have feature tests) |
| **Model Test Coverage** | 100% | ~20% (key models tested via feature tests) |
| **Frontend Component Coverage** | 70% | 0% (Vitest not yet configured) |
| **E2E Critical Paths** | 100% | ~60% |
| **CI Pipeline Pass Rate** | 100% | N/A |
| **Build Time** | < 10 min | N/A |
| **Static Analysis** | Level 5 | Level 5 (PHPStan + Larastan, 0 errors) |

### Testing Philosophy

1. **Test Behavior, Not Implementation**: Tests should verify what the code does, not how it does it
2. **Fast Feedback Loop**: Unit tests run in seconds, integration tests in minutes
3. **Confidence Over Coverage**: Focus on critical paths first, then expand
4. **Maintainable Tests**: Tests should be easy to read, write, and maintain
5. **Realistic Test Data**: Use factories that mirror production data shapes

### Current State Analysis

**Existing Infrastructure:**
- PHPUnit 11.5.3 configured
- Laravel Dusk 8.3 for browser tests
- 13 model factories
- 11 database seeders
- 13 browser test files

**What's been built (as of Feb 2026):**
- 282 passing tests with 1,063 assertions
- PHPStan level 5 enforced with Larastan (0 errors across `app/`)
- Feature tests for: AgentRespondJob, AgentPermissionService, ToolRegistry, ApprovalWrappedTool, ContactAgent, ExecuteAgentTaskJob, all tool classes, API controllers
- Tests cover agent execution pipeline, task lifecycle, inter-agent communication, approval workflows

**Remaining gaps:**
- NO frontend component tests (Vitest not yet configured)
- NO CI/CD pipeline (tests run locally only)
- Incomplete API controller coverage (~40% of endpoints)
- No dedicated unit tests for models (tested indirectly via feature tests)

---

## 1. Testing Pyramid

```
                    ┌─────────────────┐
                    │     E2E (10%)   │  Slow, expensive, high confidence
                    │   Dusk Browser  │
                    └────────┬────────┘
                             │
               ┌─────────────┴─────────────┐
               │    Integration (20%)       │  API endpoints, database
               │    Feature Tests           │
               └─────────────┬─────────────┘
                             │
        ┌────────────────────┴────────────────────┐
        │              Unit (70%)                  │  Fast, isolated, models/services
        │         PHPUnit + Vitest                 │
        └──────────────────────────────────────────┘
```

### Distribution by Component

| Layer | Test Type | Tools | Coverage Target |
|-------|-----------|-------|-----------------|
| **Models (29)** | Unit | PHPUnit | 100% |
| **Services** | Unit | PHPUnit | 90% |
| **Controllers (35)** | Integration | PHPUnit | 100% |
| **Vue Components (50+)** | Unit | Vitest | 70% |
| **Composables (10+)** | Unit | Vitest | 100% |
| **Critical Flows** | E2E | Dusk | 100% |

---

## 2. Backend Testing

### 2.1 API Integration Tests

Every controller endpoint must have tests covering:
- **Happy path**: Successful request/response
- **Validation**: Invalid input rejection
- **Authorization**: Unauthenticated and unauthorized access
- **Edge cases**: Empty results, pagination boundaries

#### Test File Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── UserTest.php
│   │   ├── TaskTest.php
│   │   ├── ChannelTest.php
│   │   └── ... (29 model tests)
│   └── Services/
│       ├── AgentExecutionServiceTest.php
│       └── ...
├── Feature/
│   ├── Api/
│   │   ├── UserControllerTest.php
│   │   ├── TaskControllerTest.php
│   │   ├── ChannelControllerTest.php
│   │   ├── MessageControllerTest.php
│   │   └── ... (35 controller tests)
│   └── Auth/
│       └── ... (existing)
└── Browser/
    └── ... (existing Dusk tests)
```

#### Controller Test Template

```php
// tests/Feature/Api/TaskControllerTest.php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Task;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $agent;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['type' => 'human']);
        $this->agent = User::factory()->create(['type' => 'agent', 'agent_type' => 'coder']);
        $this->channel = Channel::factory()->create();
    }

    // ==================== INDEX ====================

    public function test_index_returns_paginated_tasks(): void
    {
        Task::factory()->count(25)->create(['channel_id' => $this->channel->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'description', 'status', 'priority', 'assignee', 'created_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(15, 'data'); // Default pagination
    }

    public function test_index_filters_by_status(): void
    {
        Task::factory()->count(5)->create(['status' => 'backlog']);
        Task::factory()->count(3)->create(['status' => 'in_progress']);
        Task::factory()->count(2)->create(['status' => 'done']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks?status=in_progress');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/tasks');

        $response->assertUnauthorized();
    }

    // ==================== STORE ====================

    public function test_store_creates_task(): void
    {
        $taskData = [
            'title' => 'Implement feature X',
            'description' => 'As a user, I want to...',
            'priority' => 'high',
            'channel_id' => $this->channel->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', $taskData);

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'Implement feature X']);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Implement feature X',
            'creator_id' => $this->user->id,
            'status' => 'backlog', // Default status
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'channel_id']);
    }

    public function test_store_validates_priority_enum(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', [
                'title' => 'Test',
                'channel_id' => $this->channel->id,
                'priority' => 'invalid_priority',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    // ==================== SHOW ====================

    public function test_show_returns_task_with_relationships(): void
    {
        $task = Task::factory()->create([
            'assignee_id' => $this->agent->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'description', 'status', 'priority',
                    'assignee' => ['id', 'name', 'type'],
                    'creator' => ['id', 'name'],
                    'comments',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_task(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks/nonexistent-uuid');

        $response->assertNotFound();
    }

    // ==================== UPDATE ====================

    public function test_update_modifies_task(): void
    {
        $task = Task::factory()->create(['status' => 'backlog']);

        $response = $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", [
                'status' => 'in_progress',
                'assignee_id' => $this->agent->id,
            ]);

        $response->assertOk();

        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals($this->agent->id, $task->assignee_id);
    }

    public function test_update_records_started_at_when_starting(): void
    {
        $task = Task::factory()->create(['status' => 'backlog', 'started_at' => null]);

        $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress']);

        $task->refresh();
        $this->assertNotNull($task->started_at);
    }

    public function test_update_records_completed_at_when_completing(): void
    {
        $task = Task::factory()->create(['status' => 'in_progress', 'completed_at' => null]);

        $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", ['status' => 'done']);

        $task->refresh();
        $this->assertNotNull($task->completed_at);
    }

    // ==================== DESTROY ====================

    public function test_destroy_deletes_task(): void
    {
        $task = Task::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    // ==================== REORDER ====================

    public function test_reorder_updates_positions(): void
    {
        $tasks = Task::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks/reorder', [
                'tasks' => [
                    ['id' => $tasks[2]->id, 'position' => 0],
                    ['id' => $tasks[0]->id, 'position' => 1],
                    ['id' => $tasks[1]->id, 'position' => 2],
                ],
            ]);

        $response->assertOk();

        $this->assertEquals(0, $tasks[2]->fresh()->position);
        $this->assertEquals(1, $tasks[0]->fresh()->position);
        $this->assertEquals(2, $tasks[1]->fresh()->position);
    }
}
```

### 2.2 Model Unit Tests

Every model should have tests for:
- **Relationships**: Verify all defined relationships work
- **Scopes**: Test query scopes return expected results
- **Accessors/Mutators**: Test computed attributes
- **Business Logic**: Any methods on the model

#### Model Test Template

```php
// tests/Unit/Models/TaskTest.php

namespace Tests\Unit\Models;

use App\Models\Task;
use App\Models\User;
use App\Models\Channel;
use App\Models\ListItemComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    // ==================== RELATIONSHIPS ====================

    public function test_belongs_to_creator(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['creator_id' => $user->id]);

        $this->assertInstanceOf(User::class, $task->creator);
        $this->assertEquals($user->id, $task->creator->id);
    }

    public function test_belongs_to_assignee(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $task = Task::factory()->create(['assignee_id' => $agent->id]);

        $this->assertInstanceOf(User::class, $task->assignee);
        $this->assertEquals($agent->id, $task->assignee->id);
    }

    public function test_assignee_can_be_null(): void
    {
        $task = Task::factory()->create(['assignee_id' => null]);

        $this->assertNull($task->assignee);
    }

    public function test_belongs_to_channel(): void
    {
        $channel = Channel::factory()->create();
        $task = Task::factory()->create(['channel_id' => $channel->id]);

        $this->assertInstanceOf(Channel::class, $task->channel);
    }

    public function test_has_many_comments(): void
    {
        $task = Task::factory()->create();
        ListItemComment::factory()->count(3)->create(['list_item_id' => $task->id]);

        $this->assertCount(3, $task->comments);
        $this->assertInstanceOf(ListItemComment::class, $task->comments->first());
    }

    // ==================== SCOPES ====================

    public function test_scope_backlog(): void
    {
        Task::factory()->count(3)->create(['status' => 'backlog']);
        Task::factory()->count(2)->create(['status' => 'in_progress']);

        $this->assertCount(3, Task::backlog()->get());
    }

    public function test_scope_in_progress(): void
    {
        Task::factory()->count(3)->create(['status' => 'backlog']);
        Task::factory()->count(2)->create(['status' => 'in_progress']);

        $this->assertCount(2, Task::inProgress()->get());
    }

    public function test_scope_completed(): void
    {
        Task::factory()->count(3)->create(['status' => 'done']);
        Task::factory()->count(2)->create(['status' => 'in_progress']);

        $this->assertCount(3, Task::completed()->get());
    }

    public function test_scope_high_priority(): void
    {
        Task::factory()->count(2)->create(['priority' => 'high']);
        Task::factory()->count(2)->create(['priority' => 'urgent']);
        Task::factory()->count(3)->create(['priority' => 'low']);

        $this->assertCount(4, Task::highPriority()->get());
    }

    public function test_scope_assigned_to(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        Task::factory()->count(3)->create(['assignee_id' => $agent->id]);
        Task::factory()->count(2)->create();

        $this->assertCount(3, Task::assignedTo($agent->id)->get());
    }

    // ==================== ACCESSORS ====================

    public function test_duration_accessor_returns_null_when_not_completed(): void
    {
        $task = Task::factory()->create([
            'started_at' => now()->subHours(2),
            'completed_at' => null,
        ]);

        $this->assertNull($task->duration);
    }

    public function test_duration_accessor_calculates_correctly(): void
    {
        $task = Task::factory()->create([
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);

        $this->assertEquals(2 * 60, $task->duration); // Duration in minutes
    }

    public function test_is_overdue_accessor(): void
    {
        $overdueTask = Task::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'in_progress',
        ]);

        $futureTask = Task::factory()->create([
            'due_date' => now()->addDay(),
            'status' => 'in_progress',
        ]);

        $completedTask = Task::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'done',
        ]);

        $this->assertTrue($overdueTask->is_overdue);
        $this->assertFalse($futureTask->is_overdue);
        $this->assertFalse($completedTask->is_overdue); // Completed tasks aren't overdue
    }

    // ==================== MUTATORS ====================

    public function test_status_mutator_sets_timestamps(): void
    {
        $task = Task::factory()->create(['status' => 'backlog']);

        $task->update(['status' => 'in_progress']);
        $this->assertNotNull($task->started_at);

        $task->update(['status' => 'done']);
        $this->assertNotNull($task->completed_at);
    }

    // ==================== BUSINESS LOGIC ====================

    public function test_can_be_assigned_to_checks_agent_type(): void
    {
        $task = Task::factory()->create();
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create(['type' => 'agent']);

        $this->assertFalse($task->canBeAssignedTo($human));
        $this->assertTrue($task->canBeAssignedTo($agent));
    }
}
```

### 2.3 Controllers to Test (35 Total)

| Controller | Endpoints | Priority |
|------------|-----------|----------|
| `UserController` | index, show, update, presence | High |
| `TaskController` | CRUD, reorder, comments | High |
| `ChannelController` | CRUD, members, join/leave | High |
| `MessageController` | CRUD, reactions, pin, threads | High |
| `DocumentController` | CRUD, comments, versions | High |
| `ApprovalController` | index, store, respond | High |
| `DirectMessageController` | CRUD, read status | High |
| `ActivityController` | index | Medium |
| `StatsController` | index, update | Medium |
| `NotificationController` | index, markRead | Medium |
| `SearchController` | index | Medium |
| `CalendarEventController` | CRUD | Medium |
| `DataTableController` | CRUD, columns, rows | Medium |
| `AutomationRuleController` | CRUD | Medium |
| `SettingsController` | index, update | Medium |
| `IntegrationController` | CRUD | Low |

### 2.4 Service Tests

```php
// tests/Unit/Services/AgentExecutionServiceTest.php

namespace Tests\Unit\Services;

use App\Services\AgentExecutionService;
use App\Models\User;
use App\Models\Task;
use Mockery;
use Tests\TestCase;

class AgentExecutionServiceTest extends TestCase
{
    private AgentExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AgentExecutionService::class);
    }

    public function test_assigns_task_to_available_agent(): void
    {
        $idleAgent = User::factory()->create([
            'type' => 'agent',
            'status' => 'idle',
        ]);

        $task = Task::factory()->create(['status' => 'backlog']);

        $result = $this->service->assignTask($task);

        $this->assertTrue($result);
        $this->assertEquals($idleAgent->id, $task->fresh()->assignee_id);
    }

    public function test_skips_busy_agents(): void
    {
        User::factory()->create([
            'type' => 'agent',
            'status' => 'working',
        ]);

        $task = Task::factory()->create();

        $result = $this->service->assignTask($task);

        $this->assertFalse($result);
        $this->assertNull($task->fresh()->assignee_id);
    }
}
```

---

## 3. Frontend Testing

### 3.1 Setup Vitest

```bash
npm install -D vitest @vue/test-utils @testing-library/vue jsdom happy-dom
```

```typescript
// vitest.config.ts

import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/js/setup.ts'],
    include: ['tests/js/**/*.{test,spec}.{js,ts}'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      include: ['resources/js/**/*.{vue,ts}'],
      exclude: ['resources/js/**/*.d.ts'],
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
    },
  },
})
```

```typescript
// tests/js/setup.ts

import { config } from '@vue/test-utils'
import { vi } from 'vitest'

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({
    props: {
      auth: { user: { id: '1', name: 'Test User' } },
    },
  }),
  router: {
    visit: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  Link: {
    template: '<a><slot /></a>',
  },
}))

// Global stubs
config.global.stubs = {
  teleport: true,
}
```

### 3.2 Component Test Structure

```
tests/js/
├── setup.ts
├── components/
│   ├── shared/
│   │   ├── Button.spec.ts
│   │   ├── Modal.spec.ts
│   │   ├── Badge.spec.ts
│   │   └── ...
│   ├── chat/
│   │   ├── Message.spec.ts
│   │   ├── MessageInput.spec.ts
│   │   └── ...
│   ├── tasks/
│   │   ├── TaskCard.spec.ts
│   │   ├── TaskBoard.spec.ts
│   │   └── ...
│   └── agents/
│       ├── AgentSettingsPanel.spec.ts
│       └── ...
└── composables/
    ├── useApi.spec.ts
    ├── useRealtime.spec.ts
    └── ...
```

### 3.3 Component Test Examples

```typescript
// tests/js/components/shared/Button.spec.ts

import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Button from '@/Components/shared/Button.vue'

describe('Button', () => {
  it('renders slot content', () => {
    const wrapper = mount(Button, {
      slots: {
        default: 'Click me',
      },
    })

    expect(wrapper.text()).toBe('Click me')
  })

  it('applies variant classes', () => {
    const wrapper = mount(Button, {
      props: { variant: 'primary' },
    })

    expect(wrapper.classes()).toContain('btn-primary')
  })

  it('disables when loading', () => {
    const wrapper = mount(Button, {
      props: { loading: true },
    })

    expect(wrapper.attributes('disabled')).toBeDefined()
  })

  it('emits click event', async () => {
    const wrapper = mount(Button)

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')).toBeTruthy()
  })

  it('prevents click when disabled', async () => {
    const wrapper = mount(Button, {
      props: { disabled: true },
    })

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')).toBeFalsy()
  })
})
```

```typescript
// tests/js/components/tasks/TaskCard.spec.ts

import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskCard from '@/Components/tasks/TaskCard.vue'

const mockTask = {
  id: '1',
  title: 'Test Task',
  description: 'Test description',
  status: 'in_progress',
  priority: 'high',
  assignee: {
    id: 'agent-1',
    name: 'Atlas',
    type: 'agent',
  },
  created_at: '2025-01-31T10:00:00Z',
}

describe('TaskCard', () => {
  it('renders task title and description', () => {
    const wrapper = mount(TaskCard, {
      props: { task: mockTask },
    })

    expect(wrapper.text()).toContain('Test Task')
    expect(wrapper.text()).toContain('Test description')
  })

  it('shows priority badge', () => {
    const wrapper = mount(TaskCard, {
      props: { task: mockTask },
    })

    expect(wrapper.find('[data-testid="priority-badge"]').text()).toBe('high')
  })

  it('shows assignee avatar when assigned', () => {
    const wrapper = mount(TaskCard, {
      props: { task: mockTask },
    })

    expect(wrapper.find('[data-testid="assignee-avatar"]').exists()).toBe(true)
  })

  it('hides assignee when unassigned', () => {
    const wrapper = mount(TaskCard, {
      props: {
        task: { ...mockTask, assignee: null },
      },
    })

    expect(wrapper.find('[data-testid="assignee-avatar"]').exists()).toBe(false)
  })

  it('emits click event with task', async () => {
    const wrapper = mount(TaskCard, {
      props: { task: mockTask },
    })

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')?.[0]).toEqual([mockTask])
  })
})
```

### 3.4 Composable Tests

```typescript
// tests/js/composables/useApi.spec.ts

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useApi } from '@/composables/useApi'
import axios from 'axios'

vi.mock('axios')

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('fetchTasks', () => {
    it('fetches tasks successfully', async () => {
      const mockTasks = [{ id: '1', title: 'Task 1' }]
      vi.mocked(axios.get).mockResolvedValueOnce({ data: { data: mockTasks } })

      const { fetchTasks } = useApi()
      const { data, promise } = fetchTasks()

      await promise

      expect(axios.get).toHaveBeenCalledWith('/api/tasks', expect.any(Object))
      expect(data.value).toEqual(mockTasks)
    })

    it('handles fetch error', async () => {
      vi.mocked(axios.get).mockRejectedValueOnce(new Error('Network error'))

      const { fetchTasks } = useApi()
      const { error, promise } = fetchTasks()

      await promise.catch(() => {})

      expect(error.value).toBeTruthy()
    })

    it('applies filters correctly', async () => {
      vi.mocked(axios.get).mockResolvedValueOnce({ data: { data: [] } })

      const { fetchTasks } = useApi()
      fetchTasks({ status: 'done', assignee_id: 'agent-1' })

      expect(axios.get).toHaveBeenCalledWith(
        '/api/tasks',
        expect.objectContaining({
          params: { status: 'done', assignee_id: 'agent-1' },
        })
      )
    })
  })

  describe('createTask', () => {
    it('creates task and returns data', async () => {
      const newTask = { id: '2', title: 'New Task' }
      vi.mocked(axios.post).mockResolvedValueOnce({ data: { data: newTask } })

      const { createTask } = useApi()
      const result = await createTask({
        title: 'New Task',
        channel_id: 'ch-1',
      })

      expect(axios.post).toHaveBeenCalledWith('/api/tasks', {
        title: 'New Task',
        channel_id: 'ch-1',
      })
      expect(result).toEqual(newTask)
    })
  })
})
```

### 3.5 Package.json Scripts Update

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "typecheck": "vue-tsc --noEmit",
    "test": "vitest",
    "test:ui": "vitest --ui",
    "test:coverage": "vitest run --coverage",
    "lint": "eslint resources/js --ext .vue,.ts --fix"
  }
}
```

---

## 4. End-to-End Testing

### 4.1 Existing Dusk Tests (Enhance)

Current coverage:
- ✅ Authentication flows
- ✅ Dashboard
- ✅ Navigation
- ✅ Chat
- ✅ Tasks
- ✅ Documents
- ✅ Approvals
- ✅ Profile

### 4.2 Critical User Journeys to Add

```php
// tests/Browser/AgentConfigurationTest.php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AgentConfigurationTest extends DuskTestCase
{
    public function test_user_can_edit_agent_personality(): void
    {
        $this->browse(function (Browser $browser) {
            $user = $this->createUserAndLogin($browser);
            $agent = $this->createAgent('writer');

            $browser->visit("/agent/{$agent->id}")
                ->waitForText('Personality')
                ->click('@personality-tab')
                ->waitFor('@personality-editor')
                ->type('@personality-editor', 'You are a helpful assistant...')
                ->click('@save-personality')
                ->waitForText('Saved')
                ->assertSee('Saved');
        });
    }

    public function test_user_can_manage_agent_capabilities(): void
    {
        $this->browse(function (Browser $browser) {
            $user = $this->createUserAndLogin($browser);
            $agent = $this->createAgent('coder');

            $browser->visit("/agent/{$agent->id}")
                ->click('@capabilities-tab')
                ->waitFor('@capability-list')
                ->click('@toggle-code-execution')
                ->click('@save-capabilities')
                ->waitForText('Saved');
        });
    }

    public function test_user_can_view_agent_memory(): void
    {
        $this->browse(function (Browser $browser) {
            $user = $this->createUserAndLogin($browser);
            $agent = $this->createAgent('researcher');

            $browser->visit("/agent/{$agent->id}")
                ->click('@memory-tab')
                ->waitFor('@session-list')
                ->assertSee('Current Session');
        });
    }
}
```

### 4.3 E2E Test Checklist

| Flow | Status | Test File |
|------|--------|-----------|
| User registration | ✅ | AuthenticationTest |
| User login | ✅ | AuthenticationTest |
| Password reset | ✅ | AuthenticationTest |
| Dashboard loads | ✅ | DashboardTest |
| Create task | ✅ | TasksTest |
| Assign task to agent | ⬜ | TasksTest |
| Complete task | ⬜ | TasksTest |
| Send chat message | ✅ | ChatTest |
| Create channel | ⬜ | ChatTest |
| Request approval | ⬜ | ApprovalsTest |
| Approve request | ⬜ | ApprovalsTest |
| Edit agent personality | ⬜ | AgentConfigurationTest |
| Edit agent capabilities | ⬜ | AgentConfigurationTest |
| View agent memory | ⬜ | AgentConfigurationTest |
| Create document | ⬜ | DocumentsTest |
| Add document comment | ⬜ | DocumentsTest |

---

## 5. CI/CD Pipeline

### 5.1 GitHub Actions Workflow

```yaml
# .github/workflows/ci.yml

name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

env:
  PHP_VERSION: '8.3'
  NODE_VERSION: '20'

jobs:
  # ==================== PHP TESTS ====================
  php-tests:
    name: PHP Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: opencompany
          POSTGRES_PASSWORD: secret
          POSTGRES_DB: opencompany_test
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: pdo, pgsql, pdo_pgsql, redis
          coverage: xdebug

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Copy environment file
        run: cp .env.example .env.testing

      - name: Generate application key
        run: php artisan key:generate --env=testing

      - name: Run migrations
        run: php artisan migrate --env=testing --force
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: opencompany_test
          DB_USERNAME: opencompany
          DB_PASSWORD: secret

      - name: Run PHPUnit tests
        run: php artisan test --coverage-clover=coverage.xml
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: opencompany_test
          DB_USERNAME: opencompany
          DB_PASSWORD: secret

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v4
        with:
          files: coverage.xml
          flags: php

  # ==================== FRONTEND TESTS ====================
  frontend-tests:
    name: Frontend Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Run TypeScript check
        run: npm run typecheck

      - name: Run Vitest
        run: npm run test:coverage

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v4
        with:
          files: coverage/lcov.info
          flags: frontend

  # ==================== STATIC ANALYSIS ====================
  static-analysis:
    name: Static Analysis
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --memory-limit=2G

      - name: Run Pint (code style)
        run: vendor/bin/pint --test

  # ==================== DUSK TESTS ====================
  dusk-tests:
    name: Browser Tests
    runs-on: ubuntu-latest
    needs: [php-tests, frontend-tests]

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: opencompany
          POSTGRES_PASSWORD: secret
          POSTGRES_DB: opencompany_test
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Install NPM dependencies
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Setup environment
        run: |
          cp .env.dusk.ci .env
          php artisan key:generate

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: opencompany_test
          DB_USERNAME: opencompany
          DB_PASSWORD: secret

      - name: Install Chrome Driver
        run: php artisan dusk:chrome-driver --detect

      - name: Start Chrome Driver
        run: ./vendor/laravel/dusk/bin/chromedriver-linux &

      - name: Run Laravel Server
        run: php artisan serve --no-reload &

      - name: Run Dusk Tests
        run: php artisan dusk
        env:
          APP_URL: http://127.0.0.1:8000
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: opencompany_test
          DB_USERNAME: opencompany
          DB_PASSWORD: secret

      - name: Upload Screenshots
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: dusk-screenshots
          path: tests/Browser/screenshots

      - name: Upload Console Logs
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: dusk-console
          path: tests/Browser/console
```

### 5.2 Environment Files for CI

```env
# .env.dusk.ci

APP_NAME=OpenCompany
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=opencompany_test
DB_USERNAME=opencompany
DB_PASSWORD=secret

BROADCAST_CONNECTION=log
CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
```

### 5.3 Quality Gates

| Check | Requirement | Enforcement |
|-------|-------------|-------------|
| PHPUnit Tests | 100% pass | Block merge |
| Vitest Tests | 100% pass | Block merge |
| Code Coverage | ≥80% | Warning |
| PHPStan | Level 5, 0 errors | Block merge |
| Pint | No style violations | Block merge |
| Dusk Tests | 100% pass | Block merge |
| TypeScript | No errors | Block merge |

---

## 6. Test Data Management

### 6.1 Existing Factories (13)

| Factory | Model | Status |
|---------|-------|--------|
| UserFactory | User | ✅ Exists |
| ChannelFactory | Channel | ✅ Exists |
| ChannelMemberFactory | ChannelMember | ✅ Exists |
| MessageFactory | Message | ✅ Exists |
| DocumentFactory | Document | ✅ Exists |
| DocumentVersionFactory | DocumentVersion | ✅ Exists |
| DocumentCommentFactory | DocumentComment | ✅ Exists |
| ApprovalRequestFactory | ApprovalRequest | ✅ Exists |
| NotificationFactory | Notification | ✅ Exists |
| DirectMessageFactory | DirectMessage | ✅ Exists |
| ListItemCommentFactory | ListItemComment | ✅ Exists |
| ActivityFactory | Activity | ✅ Exists |
| TaskFactory | Task | ✅ Exists |

### 6.2 Factories to Add

| Factory | Model | Priority |
|---------|-------|----------|
| AgentConfigurationFactory | AgentConfiguration | High |
| AgentCapabilityFactory | AgentCapability | High |
| AgentSettingsFactory | AgentSettings | High |
| AgentSessionFactory | AgentSession | High |
| AgentMemoryFactory | AgentMemory | Medium |
| ListAutomationRuleFactory | ListAutomationRule | Medium |
| CalendarEventFactory | CalendarEvent | Low |
| DataTableFactory | DataTable | Low |

### 6.3 Test Scenarios

```php
// database/seeders/TestScenarioSeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use App\Models\Channel;
use Illuminate\Database\Seeder;

class TestScenarioSeeder extends Seeder
{
    public function run(): void
    {
        // Scenario 1: Busy agent with multiple tasks
        $busyAgent = User::factory()->create([
            'type' => 'agent',
            'agent_type' => 'coder',
            'status' => 'working',
        ]);
        Task::factory()->count(5)->create([
            'assignee_id' => $busyAgent->id,
            'status' => 'in_progress',
        ]);

        // Scenario 2: Channel with active discussion
        $channel = Channel::factory()
            ->hasMembers(5)
            ->hasMessages(50)
            ->create();

        // Scenario 3: Approval workflow in progress
        $approval = ApprovalRequest::factory()->create([
            'status' => 'pending',
            'type' => 'deployment',
            'amount' => 100,
        ]);

        // Scenario 4: High-priority backlog
        Task::factory()->count(10)->create([
            'status' => 'backlog',
            'priority' => 'urgent',
        ]);
    }
}
```

---

## 7. Code Quality

### 7.1 PHPStan Configuration

```neon
# phpstan.neon

includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 5
    paths:
        - app/
    excludePaths:
        - app/Console/Kernel.php
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
```

### 7.2 Laravel Pint Configuration

```json
// pint.json

{
    "preset": "laravel",
    "rules": {
        "simplified_null_return": true,
        "blank_line_before_statement": {
            "statements": ["return"]
        },
        "not_operator_with_successor_space": true,
        "ordered_imports": {
            "sort_algorithm": "alpha"
        }
    }
}
```

### 7.3 ESLint Configuration

```javascript
// eslint.config.js

import eslint from '@eslint/js'
import tseslint from 'typescript-eslint'
import vue from 'eslint-plugin-vue'

export default [
  eslint.configs.recommended,
  ...tseslint.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    rules: {
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    },
  },
]
```

---

## 8. Performance Testing

### 8.1 Load Testing (k6)

```javascript
// tests/load/api-load-test.js

import http from 'k6/http'
import { check, sleep } from 'k6'

export const options = {
  stages: [
    { duration: '30s', target: 20 },   // Ramp up
    { duration: '1m', target: 20 },    // Stay at 20 users
    { duration: '30s', target: 50 },   // Ramp up more
    { duration: '1m', target: 50 },    // Stay at 50 users
    { duration: '30s', target: 0 },    // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% of requests under 500ms
    http_req_failed: ['rate<0.01'],    // Less than 1% failures
  },
}

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000'

export default function () {
  // Fetch tasks
  const tasksRes = http.get(`${BASE_URL}/api/tasks`, {
    headers: { Authorization: `Bearer ${__ENV.API_TOKEN}` },
  })
  check(tasksRes, {
    'tasks status is 200': (r) => r.status === 200,
    'tasks response time < 500ms': (r) => r.timings.duration < 500,
  })

  sleep(1)

  // Fetch channels
  const channelsRes = http.get(`${BASE_URL}/api/channels`, {
    headers: { Authorization: `Bearer ${__ENV.API_TOKEN}` },
  })
  check(channelsRes, {
    'channels status is 200': (r) => r.status === 200,
  })

  sleep(1)
}
```

### 8.2 Database Performance Testing

```php
// tests/Performance/DatabaseQueryTest.php

namespace Tests\Performance;

use App\Models\Task;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseQueryTest extends TestCase
{
    public function test_task_list_query_is_efficient(): void
    {
        Task::factory()->count(1000)->create();

        DB::enableQueryLog();

        Task::with(['assignee', 'creator', 'channel'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $queries = DB::getQueryLog();

        // Should be N+1 free (max 4 queries: tasks + 3 relationships)
        $this->assertLessThanOrEqual(4, count($queries));

        // Each query should be fast
        foreach ($queries as $query) {
            $this->assertLessThan(100, $query['time'], "Slow query: {$query['query']}");
        }
    }

    public function test_message_list_with_threads_is_efficient(): void
    {
        $channel = Channel::factory()->create();
        Message::factory()->count(500)->create(['channel_id' => $channel->id]);

        DB::enableQueryLog();

        Message::with(['author', 'reactions', 'attachments'])
            ->where('channel_id', $channel->id)
            ->orderBy('created_at', 'desc')
            ->paginate(100);

        $queries = DB::getQueryLog();

        $this->assertLessThanOrEqual(5, count($queries));
    }
}
```

---

## 9. Implementation Roadmap

### Week 1: Foundation

| Day | Task | Owner |
|-----|------|-------|
| 1-2 | Set up GitHub Actions CI pipeline | DevOps |
| 2-3 | Configure PHPStan + Pint | Backend |
| 3-4 | Set up Vitest for frontend | Frontend |
| 4-5 | Add coverage reporting to CI | DevOps |

**Deliverables:**
- [ ] `.github/workflows/ci.yml` working
- [ ] PHPStan passing at level 5
- [ ] Vitest running with coverage
- [ ] Codecov integration active

### Week 2: Backend API Tests

| Day | Task | Controllers |
|-----|------|-------------|
| 1 | TaskController tests | Task CRUD, reorder |
| 2 | ChannelController, MessageController tests | Chat functionality |
| 3 | UserController, ApprovalController tests | Users, approvals |
| 4 | DocumentController tests | Documents CRUD |
| 5 | Remaining controller tests | 20+ controllers |

**Deliverables:**
- [ ] 100% API endpoint coverage
- [ ] All validation rules tested
- [ ] Authorization tests complete

### Week 3: Backend Model Tests

| Day | Task | Models |
|-----|------|--------|
| 1 | User, Task models | Core models |
| 2 | Channel, Message models | Chat models |
| 3 | Document, Approval models | Document/workflow |
| 4 | Remaining models | 15+ models |
| 5 | Service layer tests | Critical services |

**Deliverables:**
- [ ] 100% model coverage
- [ ] All scopes tested
- [ ] All relationships verified

### Week 4: Frontend Tests

| Day | Task | Components |
|-----|------|------------|
| 1 | Shared components | Button, Modal, Badge, etc. |
| 2 | Chat components | Message, MessageInput, etc. |
| 3 | Task components | TaskCard, TaskBoard, etc. |
| 4 | Composables | useApi, useRealtime, etc. |
| 5 | Integration & cleanup | Cross-component tests |

**Deliverables:**
- [ ] 70% component coverage
- [ ] 100% composable coverage
- [ ] All tests passing in CI

### Ongoing: Maintenance

| Activity | Frequency |
|----------|-----------|
| New feature tests | With each PR |
| Coverage review | Weekly |
| Flaky test fixes | As needed |
| CI optimization | Monthly |
| Load testing | Before major releases |

---

## Quick Reference

### Run Commands

```bash
# Backend tests
php artisan test                      # All tests
php artisan test --filter=TaskTest    # Specific test
php artisan test --coverage           # With coverage

# Frontend tests
npm run test                          # Watch mode
npm run test:coverage                 # With coverage
npm run test -- TaskCard.spec.ts      # Specific file

# Browser tests
php artisan dusk                      # All browser tests
php artisan dusk --filter=TasksTest   # Specific test

# Code quality
vendor/bin/phpstan analyse            # Static analysis
vendor/bin/pint                       # Code style fix
npm run lint                          # ESLint
npm run typecheck                     # TypeScript
```

### Test Helpers

```php
// Create authenticated user
$user = User::factory()->create();
$this->actingAs($user);

// Create agent
$agent = User::factory()->create([
    'type' => 'agent',
    'agent_type' => 'coder',
]);

// Assert database state
$this->assertDatabaseHas('tasks', ['id' => $task->id]);
$this->assertDatabaseMissing('tasks', ['id' => $deletedId]);

// Assert JSON structure
$response->assertJsonStructure(['data' => ['id', 'title']]);
$response->assertJsonCount(10, 'data');
```

```typescript
// Mount component with props
const wrapper = mount(Component, {
  props: { task: mockTask },
})

// Find elements
wrapper.find('[data-testid="submit-btn"]')
wrapper.findComponent(Button)

// Assert text/visibility
expect(wrapper.text()).toContain('Expected')
expect(wrapper.find('.error').exists()).toBe(false)

// Trigger events
await wrapper.trigger('click')
await wrapper.find('input').setValue('new value')
```
