<?php

namespace App\Jobs;

use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Automation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue transport for one script automation run.
 *
 * Script execution lives in the Automations domain context. This adapter owns
 * queue uniqueness, timeout/retry metadata, serialization, and workspace
 * binding for the sandbox execution path.
 */
class RunScriptAutomationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 2;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10];

    public int $uniqueFor = 120;

    public function __construct(
        private Automation $automation,
    ) {}

    public function uniqueId(): string
    {
        return 'script_automation_'.$this->automation->id;
    }

    public function handle(?ExecuteScriptAutomation $executeScriptAutomation = null): void
    {
        $this->setWorkspaceContext($this->automation->workspace_id);

        ($executeScriptAutomation ?? app(ExecuteScriptAutomation::class))->handle($this->automation);
    }
}
