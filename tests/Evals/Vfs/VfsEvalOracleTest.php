<?php

namespace Tests\Evals\Vfs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Evals\Vfs\Support\RunsVfsEvalCases;
use Tests\TestCase;

/**
 * Model-free validation of the VFS eval suite.
 *
 * Scenario protected: every task in tasks.yaml is solvable on the REAL tools
 * by its scripted known-good trajectory (`oracle` / `oracle_computer`), and
 * its state checker accepts that outcome with zero policy violations. A red
 * case here means the fixture, the checker or the VFS contract drifted, not
 * that a model misbehaved, so it must be fixed before spending model quota.
 *
 * No network: stray HTTP is prevented. Runs on sqlite :memory: like the rest
 * of the suite:  php artisan test tests/Evals/Vfs/VfsEvalOracleTest.php
 */
#[Group('vfs-eval-oracle')]
class VfsEvalOracleTest extends TestCase
{
    use RefreshDatabase;
    use RunsVfsEvalCases;

    protected function setUp(): void
    {
        self::assertInMemoryDatabase();
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function oracleCases(): array
    {
        return array_filter(self::cases(), function (array $case): bool {
            [$task, $mode] = $case;
            $agentMode = $mode === 'mixed' ? ($task['agent_mode'] ?? 'vfs_only') : $mode;

            return isset($task['scripted']) || isset($task[$agentMode === 'computer_with_bridge' ? 'oracle_computer' : 'oracle']);
        });
    }

    #[DataProvider('oracleCases')]
    public function test_oracle_trajectory_passes_its_checker(array $task, string $mode): void
    {
        $record = $this->runCase($task, $mode, 'oracle');
        $path = $this->writeRecord('oracle', $record);

        $this->assertTrue($record['success'], "{$task['id']}:{$mode}: {$record['why']} ({$path})");
        $this->assertSame([], $record['violations'], "{$task['id']}:{$mode} ({$path})");
    }
}
