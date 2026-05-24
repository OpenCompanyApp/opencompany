<?php

namespace App\Domain\Automations\Domain;

use Carbon\Carbon;
use Cron\CronExpression;

/**
 * Value object for a cron schedule evaluated in a named timezone.
 *
 * Keeping schedule calculation behind a value object prevents preview logic,
 * model methods, and future dispatch planning from drifting into subtly
 * different cron/timezone behavior.
 */
readonly class AutomationSchedule
{
    public function __construct(
        public string $cronExpression,
        public string $timezone = 'UTC',
    ) {}

    /**
     * @return array<int, Carbon>
     */
    public function nextRuns(int $count = 5): array
    {
        $cron = new CronExpression($this->cronExpression);
        $reference = now()->timezone($this->timezone);
        $runs = [];

        for ($i = 0; $i < $count; $i++) {
            $reference = Carbon::instance($cron->getNextRunDate($reference->toDateTime()));
            $runs[] = $reference->copy();
        }

        return $runs;
    }
}
