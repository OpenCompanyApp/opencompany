<?php

namespace Tests\Evals\Vfs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Evals\Vfs\Support\GlmChatClient;
use Tests\Evals\Vfs\Support\RequestBudget;
use Tests\Evals\Vfs\Support\RunsVfsEvalCases;
use Tests\TestCase;

/**
 * Live agent-experience evals for the VFS on a real model (GLM via Z.AI).
 *
 * Scenario protected: an agent that has only the VFS (vfs_only arm), or a
 * sandbox computer whose only route to company data is the VFS bridge
 * (computer_with_bridge arm), can navigate, search, read, edit and write
 * company data correctly, stops at denials and approvals without retrying or
 * working around them, and never loses another writer's update (mixed arm).
 * Scores are state-based (EvalCheckers), not model self-assessment.
 *
 * Opt-in and paid: skipped unless RUN_VFS_LLM_EVALS=1 and a key is present
 * (ZAI_API_KEY, or the legacy GLM_API_KEY in .env). Never part of the
 * Unit/Feature suites (tests/Evals is not a phpunit testsuite).
 *
 *   RUN_VFS_LLM_EVALS=1 php artisan test tests/Evals/Vfs/VfsGlmEvalTest.php
 *     EVAL_ARMS=vfs_only,computer_with_bridge,mixed   arms to run (default all)
 *     EVAL_TASKS=PATCH-01,GOV-01                     task ids (default all)
 *     EVAL_MAX_REQUESTS=150                          cumulative model-request cap
 *     EVAL_RUN_ID=...                                JSONL/transcript name
 *     EVAL_PROVIDER / EVAL_MODEL                     default: agents' provider, catalog default model
 *
 * Output: storage/app/evals/vfs/<run>.jsonl (one line per case) and
 * storage/app/evals/vfs/<run>/<task>__<arm>.json (full transcript). The
 * request counter is storage/app/evals/vfs/request-budget.json (cumulative
 * across runs; delete it to reset). Model misses are recorded, not failed:
 * the suite fails only on harness errors.
 */
#[Group('llm-eval')]
class VfsGlmEvalTest extends TestCase
{
    use RefreshDatabase;
    use RunsVfsEvalCases;

    private static ?string $runId = null;

    protected function setUp(): void
    {
        self::assertInMemoryDatabase();
        parent::setUp();

        if (! env('RUN_VFS_LLM_EVALS')) {
            $this->markTestSkipped('VFS LLM evals are opt-in: set RUN_VFS_LLM_EVALS=1.');
        }
        if (GlmChatClient::keyFromEnv() === null) {
            $this->markTestSkipped('VFS LLM evals need ZAI_API_KEY (or the legacy GLM_API_KEY).');
        }
        self::$runId ??= (string) (env('EVAL_RUN_ID') ?: 'glm-'.now()->format('Ymd-His'));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function llmCases(): array
    {
        $arms = array_filter(explode(',', (string) getenv('EVAL_ARMS')));
        $ids = array_filter(explode(',', (string) getenv('EVAL_TASKS')));

        return array_filter(self::cases(), fn (array $case): bool => ($arms === [] || in_array($case[1], $arms, true))
            && ($ids === [] || in_array($case[0]['id'], $ids, true)));
    }

    #[DataProvider('llmCases')]
    public function test_vfs_task(array $task, string $mode): void
    {
        $budget = new RequestBudget(storage_path('app/evals/vfs/request-budget.json'), (int) (env('EVAL_MAX_REQUESTS') ?: 150));
        if ($budget->remaining() === 0 && ! isset($task['scripted'])) {
            $this->markTestSkipped('Eval request budget spent.');
        }
        // The client turns on preventStrayRequests and allows only the model host.
        $client = new GlmChatClient((string) GlmChatClient::keyFromEnv(), $budget);

        $record = $this->runCase($task, $mode, 'llm', $client);
        $record['run_id'] = self::$runId;
        $record['budget_used_total'] = $budget->used();
        $path = $this->writeRecord(self::$runId, $record);

        fwrite(STDERR, sprintf(
            "\n[vfs-eval] %-12s %-21s %s calls=%d req=%d tok=%d/%d err=%d rec=%d viol=%d %s  %s\n",
            $task['id'], $mode, $record['success'] ? 'PASS' : 'FAIL', $record['tool_calls'], $record['requests'],
            $record['prompt_tokens'], $record['completion_tokens'], $record['errors_returned'], $record['errors_recovered'],
            count($record['violations']), $record['stopped'], $record['why'],
        ));

        // Model misses are data, not test failures; only a harness error
        // (HTTP failure) fails the case.
        $this->assertNotSame('http_error', $record['stopped'], "{$task['id']}:{$mode}: {$record['error']} ({$path})");
        Http::preventStrayRequests(false);
    }
}
