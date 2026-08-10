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
 * Queue transport for one QuickJS automation run.
 *
 * Script execution lives in the Automations domain context. This adapter owns
 * queue uniqueness, timeout/retry metadata, serialization, and workspace
 * binding for the sandbox execution path. Whole-script automatic retries are
 * intentionally disabled because an interrupted program may have completed
 * external writes even when the final runtime result is an error.
 */
class RunScriptAutomationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct(
        private Automation $automation,
    ) {}

    public function uniqueId(): string
    {
        return 'quickjs_automation_'.$this->automation->id;
    }

    public function handle(?ExecuteScriptAutomation $executeScriptAutomation = null): void
    {
        $this->setWorkspaceContext($this->automation->workspace_id);

        ($executeScriptAutomation ?? app(ExecuteScriptAutomation::class))->handle($this->automation);
    }
}
