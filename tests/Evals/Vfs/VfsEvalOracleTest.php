<?php

namespace Tests\Evals\Vfs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Evals\Vfs\Support\BridgeShim;
use Tests\Evals\Vfs\Support\EvalWorld;
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

    // The shim is the only route from the fake computer to company data:
    // globs expand locally (pilot finding: unexpanded *.json made the
    // computer arm fail for harness reasons), the snapshot is read-only and
    // host programs that could reach real systems are refused.
    public function test_bridge_shim_shell_contract(): void
    {
        $world = EvalWorld::build($this->workspace, []);
        $shim = new BridgeShim($world->atlas);
        $run = fn (string $command): array => json_decode($shim->run($command), true);

        $hits = $run('grep -l Globex /mnt/oc/tables/customers/rows/by-id/*.json');
        $this->assertSame(0, $hits['exit_code'], json_encode($hits));
        $this->assertStringContainsString($world->ids['row.globex'], $hits['stdout']);
        $this->assertStringContainsString('Read-only file system', $run('echo x > /mnt/oc/docs/handbook/roadmap')['stderr']);
        $this->assertSame(127, $run('curl http://opencompany.test')['exit_code']);
        $this->assertSame(127, $run('php artisan tinker')['exit_code']);
        $this->assertStringNotContainsString('CONFIDENTIAL', $run('grep -r CONFIDENTIAL /mnt/oc')['stdout']);
        $this->assertSame("a b\n", $run("echo 'a b' | cat")['stdout']);
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
