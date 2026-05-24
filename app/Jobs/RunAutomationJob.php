<?php

namespace App\Jobs;

use App\Domain\Automations\Application\ExecutePromptAutomation;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Automation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue transport for one scheduled automation run.
 *
 * Prompt automation execution lives in the Automations domain context. Script
 * automations still use their dedicated queue adapter because they run through
 * the Luau sandbox path with a much shorter timeout.
 */
class RunAutomationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 2;

    public int $timeout = 1800;

    /** @var array<int, int> */
    public array $backoff = [30];

    public int $uniqueFor = 1800;

    public function __construct(
        private Automation $automation,
    ) {}

    public function uniqueId(): string
    {
        return 'automation_'.$this->automation->id;
    }

    public function handle(?ExecutePromptAutomation $executePromptAutomation = null): void
    {
        if ($this->automation->isScript()) {
            RunScriptAutomationJob::dispatchSync($this->automation);

            return;
        }

        $this->setWorkspaceContext($this->automation->workspace_id);

        ($executePromptAutomation ?? app(ExecutePromptAutomation::class))->handle(
            $this->automation,
            $this->attempts(),
        );
    }
}
