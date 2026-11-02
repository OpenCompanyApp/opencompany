<?php

namespace Tests\Evals\Vfs\Support;

/**
 * Cross-test request budget for one eval run (the Z.AI coding plan has a
 * 5-hour request window shared with humans and other agents).
 *
 * The count lives in a small JSON file under storage/app/evals/vfs/ keyed by
 * run id, so every data-provider case (each one a fresh app instance) shares
 * one cap. `EVAL_MAX_REQUESTS` (default 150) is the cap; once spent, further
 * calls throw EvalBudgetExhausted and the case is recorded as `budget` instead
 * of being scored.
 */
final class RequestBudget
{
    public function __construct(
        private readonly string $file,
        private readonly int $max,
    ) {}

    public function consume(): void
    {
        $state = $this->state();
        if ($state['used'] >= $this->max) {
            throw new EvalBudgetExhausted("Eval request budget of {$this->max} is spent.");
        }
        $state['used']++;
        @mkdir(dirname($this->file), 0775, true);
        file_put_contents($this->file, json_encode($state));
    }

    public function used(): int
    {
        return $this->state()['used'];
    }

    public function remaining(): int
    {
        return max(0, $this->max - $this->used());
    }

    /** @return array{used: int} */
    private function state(): array
    {
        $raw = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return ['used' => (int) ($raw['used'] ?? 0)];
    }
}
