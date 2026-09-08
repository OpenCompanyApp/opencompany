<?php

namespace App\Jobs;

use App\Domain\Automations\Application\ExecutePromptAutomation;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Automation;
use App\Services\ScriptAutomationInvocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Queue transport for one scheduled automation run.
 *
 * Prompt automation execution lives in the Automations domain context. Script
 * automations still use their dedicated queue adapter because they run through
 * the mruby path with a much shorter timeout and no whole-script retry.
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

    private ?array $scriptReceipt = null;

    private ?string $scriptInvocationId = null;

    public function __construct(
        private Automation $automation,
    ) {
        if ($automation->isScript()) {
            $this->scriptReceipt = ScriptAutomationInvocation::capture($automation);
            $this->scriptInvocationId = (string) Str::uuid();
            // dispatchSync does not replace this outer worker's retry/timeout
            // policy. Bound the actual queued job, not only the inner adapter.
            $this->tries = 1;
            $this->timeout = 60;
        }
    }

    public function uniqueId(): string
    {
        return 'automation_'.$this->automation->id;
    }

    public function handle(?ExecutePromptAutomation $executePromptAutomation = null): void
    {
        if ($this->automation->isScript() || $this->scriptReceipt !== null) {
            if ($this->scriptReceipt === null || $this->scriptInvocationId === null) {
                return;
            }
            RunScriptAutomationJob::dispatchSync($this->automation, $this->scriptReceipt, $this->scriptInvocationId);

            return;
        }

        $this->setWorkspaceContext($this->automation->workspace_id);

        ($executePromptAutomation ?? app(ExecutePromptAutomation::class))->handle(
            $this->automation,
            $this->attempts(),
        );
    }
}
