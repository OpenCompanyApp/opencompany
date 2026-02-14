<?php

namespace App\Agents\Tools\System;

use App\Jobs\AgentResumeFromSleepJob;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class Wait implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Pause execution for a specified number of minutes and resume automatically. Use this to wait for external processes, schedule follow-ups, or check back later.';
    }

    public function handle(Request $request): string
    {
        try {
            $duration = (int) ($request['duration'] ?? 0);
            $reason = $request['reason'] ?? '';

            if ($duration < 1 || $duration > 10080) {
                return 'Error: Duration must be between 1 and 10080 minutes (7 days).';
            }

            if (empty(trim($reason))) {
                return 'Error: A reason is required for waiting.';
            }

            $resumeAt = now()->addMinutes($duration);

            $this->agent->update([
                'sleeping_until' => $resumeAt,
                'sleeping_reason' => $reason,
            ]);

            AgentResumeFromSleepJob::dispatch($this->agent)->delay($resumeAt);

            return "Execution will pause after your response. You will be automatically resumed at {$resumeAt->toISOString()} ({$duration} minutes from now). Reason recorded: {$reason}";
        } catch (\Throwable $e) {
            return "Error setting up wait: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'duration' => $schema
                ->integer()
                ->description('How many minutes to wait. Minimum 1, maximum 10080 (7 days).')
                ->required(),
            'reason' => $schema
                ->string()
                ->description('Why you are waiting. This is stored for transparency and audit.')
                ->required(),
        ];
    }
}
