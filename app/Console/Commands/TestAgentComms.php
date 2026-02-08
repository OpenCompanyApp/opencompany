<?php

namespace App\Console\Commands;

use App\Agents\OpenCompanyAgent;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentCommunicationService;
use App\Services\AgentPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestAgentComms extends Command
{
    protected $signature = 'agent:test-comms
        {--pattern= : Test specific pattern (notify, ask, delegate) or all}
        {--agent-a= : Name or ID of agent A (caller)}
        {--agent-b= : Name or ID of agent B (target)}
        {--dry-run : Test DM creation, formatting, permissions without invoking LLM}';

    protected $description = 'Test inter-agent communication patterns end-to-end';

    public function handle(
        AgentCommunicationService $commService,
        AgentPermissionService $permissionService,
    ): int {
        $agentA = $this->resolveAgent($this->option('agent-a'), 'A');
        $agentB = $this->resolveAgent($this->option('agent-b'), 'B', $agentA?->id);

        if (!$agentA || !$agentB) {
            $this->error('Need at least 2 agents. Create agents first.');
            return 1;
        }

        $this->info("Agent A: {$agentA->name} ({$agentA->id}) [{$agentA->status}]");
        $this->info("Agent B: {$agentB->name} ({$agentB->id}) [{$agentB->status}]");
        $this->newLine();

        $pattern = $this->option('pattern');
        $dryRun = $this->option('dry-run');

        $patterns = $pattern ? [$pattern] : ['notify', 'ask', 'delegate'];

        foreach ($patterns as $p) {
            match ($p) {
                'notify' => $this->testNotify($agentA, $agentB, $commService, $permissionService, $dryRun),
                'ask' => $this->testAsk($agentA, $agentB, $commService, $permissionService, $dryRun),
                'delegate' => $this->testDelegate($agentA, $agentB, $commService, $permissionService, $dryRun),
                default => $this->error("Unknown pattern: {$p}"),
            };
            $this->newLine();
        }

        return 0;
    }

    private function testNotify(
        User $agentA,
        User $agentB,
        AgentCommunicationService $commService,
        AgentPermissionService $permissionService,
        bool $dryRun,
    ): void {
        $this->components->info('Testing NOTIFY pattern');

        // Step 1: Check permissions
        $perm = $permissionService->canContactAgent($agentA, $agentB);
        $this->line("  Permission: " . ($perm['allowed'] ? '<fg=green>allowed</>' : '<fg=red>denied</>') .
            ($perm['requires_approval'] ? ' (requires approval)' : ''));

        if (!$perm['allowed']) {
            $this->warn('  Skipping — permission denied');
            return;
        }

        // Step 2: Get/create DM channel
        $channelId = $commService->getOrCreateDmChannel($agentA, $agentB);
        $this->line("  DM channel: {$channelId}");

        // Step 3: Format message
        $formatted = $commService->formatRequestMessage('notify', $agentA, 'Test notification from agent:test-comms command');
        $this->line("  Formatted message:");
        $this->line("    " . str_replace("\n", "\n    ", $formatted));

        if ($dryRun) {
            $this->comment('  [dry-run] Would post notification message');
            return;
        }

        // Step 4: Post message
        $message = $commService->postMessage($channelId, $agentA, $formatted, 'agent_contact');
        $this->line("  Message posted: {$message->id}");
        $this->components->info('NOTIFY test passed');
    }

    private function testAsk(
        User $agentA,
        User $agentB,
        AgentCommunicationService $commService,
        AgentPermissionService $permissionService,
        bool $dryRun,
    ): void {
        $this->components->info('Testing ASK pattern');

        $perm = $permissionService->canContactAgent($agentA, $agentB);
        $this->line("  Permission: " . ($perm['allowed'] ? '<fg=green>allowed</>' : '<fg=red>denied</>'));

        if (!$perm['allowed']) {
            $this->warn('  Skipping — permission denied');
            return;
        }

        $channelId = $commService->getOrCreateDmChannel($agentA, $agentB);
        $this->line("  DM channel: {$channelId}");

        $formatted = $commService->formatRequestMessage('ask', $agentA, 'What is your current status? Reply briefly.');
        $this->line("  Formatted request:");
        $this->line("    " . str_replace("\n", "\n    ", $formatted));

        if ($dryRun) {
            $this->comment('  [dry-run] Would invoke LLM for synchronous ask');
            $this->line("  Current depth: " . AgentCommunicationService::currentDepth() . " / max 3");
            return;
        }

        // Post request
        $commService->postMessage($channelId, $agentA, $formatted, 'agent_contact');

        // Invoke LLM synchronously
        $this->line('  Invoking LLM...');
        try {
            $agentInstance = OpenCompanyAgent::for($agentB, $channelId);
            $response = $agentInstance->prompt('What is your current status? Reply briefly.');
            $responseText = $response->text;

            $this->line("  Response: {$responseText}");
            $commService->postMessage($channelId, $agentB, $responseText, 'agent_contact');
            $this->components->info('ASK test passed');
        } catch (\Throwable $e) {
            $this->error("  LLM call failed: {$e->getMessage()}");
        }
    }

    private function testDelegate(
        User $agentA,
        User $agentB,
        AgentCommunicationService $commService,
        AgentPermissionService $permissionService,
        bool $dryRun,
    ): void {
        $this->components->info('Testing DELEGATE pattern');

        $perm = $permissionService->canContactAgent($agentA, $agentB);
        $this->line("  Permission: " . ($perm['allowed'] ? '<fg=green>allowed</>' : '<fg=red>denied</>'));

        if (!$perm['allowed']) {
            $this->warn('  Skipping — permission denied');
            return;
        }

        $channelId = $commService->getOrCreateDmChannel($agentA, $agentB);
        $this->line("  DM channel: {$channelId}");

        if ($dryRun) {
            $formatted = $commService->formatRequestMessage('delegate', $agentA, 'Test delegation task', 'Test context', 'normal', 'fake-task-id');
            $this->line("  Formatted delegation:");
            $this->line("    " . str_replace("\n", "\n    ", $formatted));
            $this->comment('  [dry-run] Would create subtask and dispatch AgentRespondJob');
            return;
        }

        // Create parent task for agent A (so callback flow works)
        $parentTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Parent task for delegation test',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_MANUAL,
            'agent_id' => $agentA->id,
            'requester_id' => $agentA->id,
            'channel_id' => $channelId,
            'started_at' => now(),
        ]);
        $this->line("  Parent task created: {$parentTask->id}");

        // Create subtask
        $subtask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Test delegation from agent:test-comms',
            'description' => 'Reply with a brief acknowledgment.',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $agentB->id,
            'requester_id' => $agentA->id,
            'channel_id' => $channelId,
            'parent_task_id' => $parentTask->id,
        ]);
        $this->line("  Subtask created: {$subtask->id}");

        // Post message
        $formatted = $commService->formatRequestMessage(
            'delegate', $agentA, 'Reply with a brief acknowledgment.',
            'Test context from agent:test-comms', 'normal', $subtask->id
        );
        $triggerMessage = $commService->postMessage($channelId, $agentA, $formatted, 'agent_contact');

        // Track delegation on caller
        $agentA->addAwaitingDelegation($subtask->id);
        $this->line("  Agent A awaiting_delegation_ids: " . json_encode($agentA->fresh()->awaiting_delegation_ids));

        // Dispatch job
        AgentRespondJob::dispatch($triggerMessage, $agentB, $channelId);
        $this->line("  AgentRespondJob dispatched for {$agentB->name}");
        $this->components->info('DELEGATE test initiated — check queue worker for completion');
    }

    private function resolveAgent(?string $nameOrId, string $label, ?string $excludeId = null): ?User
    {
        if ($nameOrId) {
            return User::where('type', 'agent')
                ->where(fn ($q) => $q->where('id', $nameOrId)->orWhere('name', $nameOrId))
                ->first();
        }

        $query = User::where('type', 'agent')->where('status', '!=', 'offline');
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }
}
