<?php

namespace Tests\Evals;

use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Events\ToolInvoked;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Live agent-experience evals for mruby Code Mode on a real model (GLM).
 *
 * Scenario protected: a production agent, given only its normal tools and
 * Code Mode docs, can discover the right `app.*` capabilities, write valid
 * restricted Ruby, recover from its own errors, and produce correct results
 * against real workspace data — including writes that require task receipts.
 *
 * This suite calls a paid external model. It is deliberately outside the
 * Unit/Feature suites in phpunit.xml and is skipped unless a key is present:
 *
 *   GLM_API_KEY=... RUBY_ENGINE_BINARY=... php artisan test tests/Evals
 *
 * Each case writes a transcript (tool calls, Ruby source, errors, final reply)
 * to storage/app/evals/code-mode/<run>/<case>.json. Scores are objective
 * checks against the seeded data, not model self-assessment.
 */
#[Group('live-eval')]
class CodeModeGlmEvalTest extends TestCase
{
    use RefreshDatabase;

    private User $human;

    private User $agent;

    private static ?string $runId = null;

    /** @var list<array<string, mixed>> Tool calls observed via the SDK event. */
    public static array $invocations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $key = env('GLM_API_KEY') ?: env('ZAI_API_KEY');
        if (! $key) {
            $this->markTestSkipped('Live eval: set GLM_API_KEY (Z.AI coding plan) to run.');
        }
        if (! is_executable((string) config('code.engine_binary'))) {
            $this->markTestSkipped('Live eval: RUBY_ENGINE_BINARY must point at the pinned mruby engine.');
        }

        // Only broadcast events are faked: tool lifecycle events must reach
        // CheckpointToolCall so every tool step is recorded for the transcript.
        Event::fake([MessageSent::class, TaskUpdated::class, AgentStatusUpdated::class]);
        self::$invocations = [];
        Event::listen(ToolInvoked::class, function (ToolInvoked $event): void {
            $tool = $event->tool;
            for ($i = 0; $i < 4; $i++) {
                $tool = method_exists($tool, 'innerTool') ? $tool->innerTool() : (method_exists($tool, 'getInnerTool') ? $tool->getInnerTool() : $tool);
            }
            $meta = method_exists($tool, 'lastExecutionMetadata') ? $tool->lastExecutionMetadata() : null;
            self::$invocations[] = [
                'tool' => method_exists($event->tool, 'name') ? $event->tool->name() : class_basename($event->tool),
                'arguments' => $event->arguments,
                'error' => is_array($meta['error'] ?? null) ? json_encode($meta['error']) : null,
                'bridge_calls' => is_array($meta['bridgeCalls'] ?? null) ? array_map(fn ($c) => ($c['path'] ?? '?').':'.($c['status'] ?? '?'), $meta['bridgeCalls']) : [],
                'result_excerpt' => Str::limit(is_string($event->result) ? $event->result : (string) json_encode($event->result), 1500),
            ];
        });
        $model = (string) (env('EVAL_MODEL') ?: 'z:glm-5.1');
        [$provider] = explode(':', $model, 2);
        config([
            "ai.providers.{$provider}.key" => $key,
            "ai.providers.{$provider}.api_key" => $key,
            'ai.request_timeout' => 240,
        ]);

        $this->human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $this->human->id, 'role' => 'admin']);
        $this->agent = User::factory()->agent()->create([
            'name' => 'Atlas',
            'status' => 'idle',
            'brain' => $model,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->seedWorkspace();
        self::$runId ??= now()->format('Ymd-His');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'table_aggregate' => [
                'table_aggregate',
                'In the "Deals" table, what is the total amount of all deals in stage "won", and how many won deals are there? Use code to compute it exactly. Answer with the total and the count.',
            ],
            'table_group_by_owner' => [
                'table_group_by_owner',
                'Using the "Deals" table, give me the open pipeline (stages "proposal" and "negotiation" only) summed per owner. Reply with one line per owner like "Owner: amount", highest first.',
            ],
            'docs_search' => [
                'docs_search',
                'Which of our documents mention our refund policy (the word "refund")? List their exact titles.',
            ],
            'create_followup_tasks' => [
                'create_followup_tasks',
                'For every deal in the "Deals" table that is in stage "negotiation" and owned by Sam, create a task titled exactly "Follow up: <company>" (one task per deal). Then tell me how many tasks you created.',
            ],
            'update_table_row' => [
                'update_table_row',
                'In the "Signups" table, the signup with email user13@example.com should have status "churned". Update that row (only that row) and confirm.',
            ],
            'large_table_paging' => [
                'large_table_paging',
                'The "Signups" table is large. How many signups have status "churned"? Count all rows, not just the first page.',
            ],
        ];
    }

    #[DataProvider('cases')]
    public function test_code_mode_case(string $case, string $prompt): void
    {
        $channel = Channel::factory()->create(['type' => 'dm', 'workspace_id' => $this->workspace->id]);
        $message = Message::create([
            'id' => (string) Str::uuid(),
            'content' => $prompt,
            'channel_id' => $channel->id,
            'author_id' => $this->human->id,
            'timestamp' => now(),
        ]);
        $task = Task::createPending($message, $this->agent, $channel->id);

        $started = microtime(true);
        $error = null;
        try {
            (new AgentRespondJob($message, $this->agent, $channel->id, $task->id))->handle();
        } catch (\Throwable $e) {
            $error = $e::class.': '.$e->getMessage();
        }
        $elapsed = round(microtime(true) - $started, 1);

        $reply = Message::query()->where('channel_id', $channel->id)->where('author_id', $this->agent->id)
            ->orderByDesc('timestamp')->value('content') ?? '';
        $steps = array_map(fn (array $i): array => [...$i, 'tool' => Str::snake((string) $i['tool'])], self::$invocations);
        [$passed, $why] = $this->score($case, (string) $reply);

        $codeCalls = array_values(array_filter($steps, fn (array $s): bool => $s['tool'] === 'code_exec'));
        $report = [
            'case' => $case,
            'model' => $this->agent->brain,
            'passed' => $passed,
            'why' => $why,
            'elapsed_s' => $elapsed,
            'job_error' => $error,
            'task' => $task->fresh()?->only(['status', 'result']),
            'runs' => AgentRunRecord::query()->where('agent_id', $this->agent->id)->get(['kind', 'status', 'attempt_count', 'last_error'])->toArray(),
            'tool_calls' => count($steps),
            'code_exec_calls' => count($codeCalls),
            'code_exec_errors' => count(array_filter($codeCalls, fn (array $s): bool => $s['error'] !== null)),
            'used_docs' => count(array_filter($steps, fn (array $s): bool => str_starts_with($s['tool'], 'code_') && $s['tool'] !== 'code_exec')),
            'reply' => $reply,
            'steps' => $steps,
            'raw_steps' => TaskStep::query()->whereIn('task_id', Task::query()->where('agent_id', $this->agent->id)->pluck('id'))
                ->orderBy('created_at')->get(['description', 'step_type', 'status', 'metadata'])->toArray(),
        ];
        $dir = storage_path('app/evals/code-mode/'.self::$runId);
        @mkdir($dir, 0775, true);
        file_put_contents("{$dir}/{$case}.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fwrite(STDERR, sprintf("\n[eval] %-24s %s  code_exec=%d errors=%d docs=%d %ss  %s\n",
            $case, $passed ? 'PASS' : 'FAIL', $report['code_exec_calls'], $report['code_exec_errors'], $report['used_docs'], $elapsed, $why));

        $this->assertTrue($passed, "{$case}: {$why}. Transcript: {$dir}/{$case}.json");
    }

    /**
     * Tool steps recorded for this run (all tasks the agent touched).
     *
     * @return list<array{tool: string, arguments: mixed, error: ?string, result_excerpt: string}>
     */
    private function toolSteps(): array
    {
        return TaskStep::query()
            ->whereIn('task_id', Task::query()->where('agent_id', $this->agent->id)->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->map(function (TaskStep $step): array {
                $meta = $step->metadata ?? [];
                $tool = (string) ($meta['tool_name'] ?? $meta['tool'] ?? 'unknown');
                $code = is_array($meta['code_meta'] ?? null) ? $meta['code_meta'] : [];
                $result = is_string($meta['result'] ?? null) ? $meta['result'] : json_encode($meta['result'] ?? null);
                $errorText = is_array($code['error'] ?? null) ? json_encode($code['error']) : null;

                return [
                    'tool' => Str::snake(str_replace(' ', '', $tool)),
                    'arguments' => $meta['arguments'] ?? null,
                    'error' => $errorText,
                    'result_excerpt' => Str::limit((string) $result, 1500),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function score(string $case, string $reply): array
    {
        $normalized = str_replace([',', ' '], '', $reply);

        return match ($case) {
            'table_aggregate' => (str_contains($normalized, (string) $this->expected['won_total']) && preg_match('/\b'.$this->expected['won_count'].'\b/', $reply))
                ? [true, 'total and count correct']
                : [false, "expected total {$this->expected['won_total']} and count {$this->expected['won_count']}"],
            'table_group_by_owner' => $this->scoreOwners($reply),
            'docs_search' => $this->scoreDocs($reply),
            'create_followup_tasks' => $this->scoreFollowups(),
            'update_table_row' => $this->scoreRowUpdate(),
            'large_table_paging' => preg_match('/\b'.$this->expected['churned'].'\b/', $reply)
                ? [true, 'paged count correct']
                : [false, "expected {$this->expected['churned']} churned"],
            default => [false, 'unknown case'],
        };
    }

    /** @return array{0: bool, 1: string} */
    private function scoreOwners(string $reply): array
    {
        $flat = str_replace([',', ' '], '', $reply);
        foreach ($this->expected['open_by_owner'] as $owner => $amount) {
            if (! str_contains($reply, $owner) || ! str_contains($flat, (string) $amount)) {
                return [false, "missing {$owner}: {$amount}"];
            }
        }

        return [true, 'all owner sums present'];
    }

    /** @return array{0: bool, 1: string} */
    private function scoreRowUpdate(): array
    {
        $table = DataTable::query()->where('name', 'Signups')->firstOrFail();
        [$emailId, $statusId] = [$this->expected['signup_email_col'], $this->expected['signup_status_col']];
        $rows = DataTableRow::query()->where('table_id', $table->id)->get();
        $target = $rows->first(fn ($r) => ($r->data[$emailId] ?? null) === 'user13@example.com');
        $churned = $rows->filter(fn ($r) => ($r->data[$statusId] ?? null) === 'churned')->count();

        if (($target?->data[$statusId] ?? null) !== 'churned') {
            return [false, 'target row not updated'];
        }

        return $churned === $this->expected['churned'] + 1
            ? [true, 'exactly the target row changed']
            : [false, "other rows changed (churned now {$churned})"];
    }

    /** @return array{0: bool, 1: string} */
    private function scoreDocs(string $reply): array
    {
        foreach ($this->expected['refund_docs'] as $title) {
            if (! str_contains($reply, $title)) {
                return [false, "missing doc {$title}"];
            }
        }
        // Mentioning a non-matching doc is fine only when the same line says
        // it does NOT match (models often explain what they ruled out).
        foreach ($this->expected['non_refund_docs'] as $title) {
            foreach (preg_split('/\R/', $reply) ?: [] as $line) {
                if (str_contains($line, $title) && ! preg_match("/\\b(not|no|don't|doesn't|without|neither|nor)\\b/i", $line)) {
                    return [false, "wrongly listed {$title}"];
                }
            }
        }

        return [true, 'exact doc set'];
    }

    /** @return array{0: bool, 1: string} */
    private function scoreFollowups(): array
    {
        // User-facing tasks are board work items (ListItem); internal run
        // Tasks are also accepted. Exactly one per matching deal, no extras.
        $titles = ListItem::query()->where('workspace_id', $this->workspace->id)
            ->where('title', 'like', 'Follow up: %')->pluck('title')
            ->merge(Task::query()->where('workspace_id', $this->workspace->id)->where('title', 'like', 'Follow up: %')->pluck('title'))
            ->sort()->values()->all();
        $expected = collect($this->expected['sam_negotiation'])->map(fn ($c) => "Follow up: {$c}")->sort()->values()->all();

        return $titles === $expected
            ? [true, count($titles).' follow-up tasks exactly']
            : [false, 'tasks '.json_encode($titles).' != '.json_encode($expected)];
    }

    /** @var array<string, mixed> */
    private array $expected = [];

    /**
     * Deterministic workspace data with known answers.
     */
    private function seedWorkspace(): void
    {
        $deals = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Deals', 'created_by' => $this->human->id]);
        $cols = [];
        foreach (['Company' => 'text', 'Stage' => 'text', 'Amount' => 'number', 'Owner' => 'text'] as $i => $type) {
            $cols[$i] = DataTableColumn::create(['table_id' => $deals->id, 'name' => $i, 'type' => $type, 'order' => count($cols), 'required' => false]);
        }
        $stages = ['lead', 'proposal', 'negotiation', 'won', 'lost'];
        $owners = ['Sam', 'Priya', 'Jonas'];
        $wonTotal = 0;
        $wonCount = 0;
        $openByOwner = [];
        $samNegotiation = [];
        for ($i = 1; $i <= 36; $i++) {
            $company = "Company {$i}";
            $stage = $stages[($i * 7) % 5];
            $owner = $owners[($i * 5) % 3];
            $amount = 1000 + ($i * 137) % 9000;
            DataTableRow::create(['table_id' => $deals->id, 'created_by' => $this->human->id, 'data' => [
                $cols['Company']->id => $company, $cols['Stage']->id => $stage, $cols['Amount']->id => $amount, $cols['Owner']->id => $owner,
            ]]);
            if ($stage === 'won') {
                $wonTotal += $amount;
                $wonCount++;
            }
            if (in_array($stage, ['proposal', 'negotiation'], true)) {
                $openByOwner[$owner] = ($openByOwner[$owner] ?? 0) + $amount;
            }
            if ($stage === 'negotiation' && $owner === 'Sam') {
                $samNegotiation[] = $company;
            }
        }

        $signups = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Signups', 'created_by' => $this->human->id]);
        $email = DataTableColumn::create(['table_id' => $signups->id, 'name' => 'Email', 'type' => 'text', 'order' => 0, 'required' => false]);
        $status = DataTableColumn::create(['table_id' => $signups->id, 'name' => 'Status', 'type' => 'text', 'order' => 1, 'required' => false]);
        $churned = 0;
        for ($i = 1; $i <= 260; $i++) {
            $value = $i % 6 === 0 ? 'churned' : ($i % 4 === 1 ? 'trial' : 'active');
            $churned += $value === 'churned' ? 1 : 0;
            DataTableRow::create(['table_id' => $signups->id, 'created_by' => $this->human->id, 'data' => [
                $email->id => "user{$i}@example.com", $status->id => $value,
            ]]);
        }

        $refundDocs = ['Customer Refund Policy', 'Support Playbook'];
        Document::factory()->create(['title' => 'Customer Refund Policy', 'content' => "Our refund window is 30 days.\nRefunds go back to the original card.", 'author_id' => $this->human->id, 'workspace_id' => $this->workspace->id]);
        Document::factory()->create(['title' => 'Support Playbook', 'content' => "Escalate angry customers.\nFor money back requests follow the refund policy.", 'author_id' => $this->human->id, 'workspace_id' => $this->workspace->id]);
        Document::factory()->create(['title' => 'Onboarding Guide', 'content' => 'Welcome! Set up your laptop and accounts.', 'author_id' => $this->human->id, 'workspace_id' => $this->workspace->id]);
        Document::factory()->create(['title' => 'Pricing Notes', 'content' => 'Annual plans get two months free.', 'author_id' => $this->human->id, 'workspace_id' => $this->workspace->id]);

        arsort($openByOwner);
        $this->expected = [
            'won_total' => $wonTotal,
            'won_count' => $wonCount,
            'open_by_owner' => $openByOwner,
            'sam_negotiation' => $samNegotiation,
            'churned' => $churned,
            'signup_email_col' => $email->id,
            'signup_status_col' => $status->id,
            'refund_docs' => $refundDocs,
            'non_refund_docs' => ['Onboarding Guide', 'Pricing Notes'],
        ];
    }
}
