<?php

namespace Tests\Feature;

use App\Services\CodeExecutionBudget;
use App\Services\CodeExecutionCancelled;
use App\Services\CodeExecutionDeadlineExceeded;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MrubyExecutionBudgetTest extends TestCase
{
    public function test_callback_scope_clamps_laravel_http_options_at_dispatch(): void
    {
        $captured = [];
        Http::fake(function ($request, array $options) use (&$captured) {
            $captured = $options;

            return Http::response(['ok' => true]);
        });

        $budget = app(CodeExecutionBudget::class);
        $budget->within(5_000, 120, static fn (): bool => false, function () use ($budget): void {
            $budget->callback(function (): void {
                Http::timeout(30)->connectTimeout(20)->get('https://budget.example.test/dispatch');
            });
        });

        $this->assertLessThanOrEqual(0.12, $captured['timeout']);
        $this->assertLessThanOrEqual(0.12, $captured['connect_timeout']);
        $this->assertLessThanOrEqual(0.12, $captured['read_timeout']);
        $this->assertGreaterThan(0, $captured['timeout']);
    }

    public function test_cancellation_prevents_http_dispatch(): void
    {
        $dispatched = false;
        $cancelled = false;
        Http::fake(function () use (&$dispatched) {
            $dispatched = true;

            return Http::response(['unexpected' => true]);
        });

        $budget = app(CodeExecutionBudget::class);

        try {
            $budget->within(5_000, 120, static function () use (&$cancelled): bool {
                return $cancelled;
            }, function () use ($budget, &$cancelled): void {
                $budget->callback(function () use (&$cancelled): void {
                    $cancelled = true;
                    Http::get('https://budget.example.test/cancelled');
                });
            });
            $this->fail('Expected a cancellation exception.');
        } catch (CodeExecutionCancelled $exception) {
            $this->assertSame(CodeExecutionCancelled::ERROR_CODE, $exception->getMessage());
        }

        $this->assertFalse($dispatched);
    }

    public function test_expired_callback_prevents_http_dispatch_and_scope_restores(): void
    {
        $dispatches = 0;
        $captured = [];
        Http::fake(function ($request, array $options) use (&$dispatches, &$captured) {
            $dispatches++;
            $captured[] = $options;

            return Http::response(['ok' => true]);
        });

        $budget = app(CodeExecutionBudget::class);

        try {
            $budget->within(5_000, 1, static fn (): bool => false, function () use ($budget): void {
                $budget->callback(function (): void {
                    usleep(5_000);
                    Http::get('https://budget.example.test/expired');
                });
            });
            $this->fail('Expected a deadline exception.');
        } catch (CodeExecutionDeadlineExceeded $exception) {
            $this->assertSame(CodeExecutionDeadlineExceeded::ERROR_CODE, $exception->getMessage());
        }

        $this->assertSame(0, $dispatches);

        Http::timeout(7)->get('https://budget.example.test/ordinary-host-call');

        $this->assertSame(1, $dispatches);
        $this->assertSame(7, $captured[0]['timeout']);
        $this->assertArrayNotHasKey('read_timeout', $captured[0]);
    }

    public function test_multiple_requests_share_one_callback_deadline(): void
    {
        $timeouts = [];
        Http::fake(function ($request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response(['ok' => true]);
        });

        $budget = app(CodeExecutionBudget::class);
        $budget->within(5_000, 500, static fn (): bool => false, function () use ($budget): void {
            $budget->callback(function (): void {
                Http::get('https://budget.example.test/first');
                usleep(20_000);
                Http::get('https://budget.example.test/second');
            });
        });

        $this->assertCount(2, $timeouts);
        $this->assertLessThan($timeouts[0], $timeouts[1]);
    }
}
