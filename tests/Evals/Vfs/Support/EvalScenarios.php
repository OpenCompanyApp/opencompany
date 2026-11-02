<?php

namespace Tests\Evals\Vfs\Support;

use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Services\ApprovalExecutionService;
use Illuminate\Support\Facades\Queue;

/**
 * Scripted actors for the eval: pre-steps that put the world in a state
 * (GOV-03's approved request, MIX-04's edit after the pull) and the second
 * writer of mixed tasks, triggered between two of the LLM agent's tool calls.
 *
 * Scripted writers use the same real tools as the agent (Forge through
 * surface `computer`, Atlas through `tool`), so their writes are governed and
 * audited like any other. A hook fires at most once per case.
 */
final class EvalScenarios
{
    private bool $fired = false;

    public function __construct(private readonly EvalWorld $world) {}

    /**
     * Run a named pre-step before the agent's first turn.
     */
    public function pre(string $name, ?BridgeShim $shim = null): void
    {
        match ($name) {
            'gov03_request_and_approve' => $this->gov03RequestAndApprove(),
            'mix04_atlas_edit_after_pull' => $this->atlasTools()->callSlug('vfs_edit', [
                'path' => '/docs/handbook/onboarding',
                'edits' => [['old_text' => 'Ask #help for access', 'new_text' => 'Ask #it-support for access']],
            ]),
            default => throw new \InvalidArgumentException("Unknown pre-step {$name}"),
        };
    }

    /**
     * Post-tool hook for mixed tasks: fire the named writer once, right after
     * the first agent tool result that contains the trigger text.
     *
     * @param  array{name: string, after_result_contains: string}  $hook
     * @param  array<string, mixed>  $step
     */
    public function afterTool(array $hook, array $step): void
    {
        if ($this->fired || ! str_contains((string) $step['result'], $hook['after_result_contains'])) {
            return;
        }
        $this->fired = true;

        match ($hook['name']) {
            'mix01_forge_edit' => $this->forgeTools()->callSlug('vfs_edit', [
                'path' => '/docs/handbook/refund-policy',
                'edits' => [[
                    'old_text' => "Refunds go back to the original payment method.\n",
                    'new_text' => "Refunds go back to the original payment method.\nApproved refunds are paid within 5 business days.\n",
                ]],
            ]),
            'mix03_forge_mrr' => $this->forgeTools()->callSlug('vfs_patch', [
                'path' => "/tables/customers/rows/by-id/{$this->world->ids['row.globex']}.json",
                'patch' => json_encode(['data' => ['MRR' => 0], 'base' => ['MRR' => 290]]),
            ]),
            default => throw new \InvalidArgumentException("Unknown hook {$hook['name']}"),
        };
    }

    public function fired(): bool
    {
        return $this->fired;
    }

    /**
     * MIX-02, fully scripted (no model): Forge pulls, Atlas edits, Forge
     * pushes its stale full copy back through the bridge.
     *
     * @return array{steps: list<array<string, mixed>>, reply: string, scripted_ok: bool, scripted_why: string}
     */
    public function mix02StalePush(): array
    {
        $shim = new BridgeShim($this->world->forge);
        $steps = [];
        $record = function (string $tool, array $arguments, string $result) use (&$steps): string {
            $steps[] = ['tool' => $tool, 'arguments' => $arguments, 'result' => $result] + EvalAgentLoop::outcome($result);

            return $result;
        };

        $atlas = $record('VfsEdit', ['atlas' => true], $this->atlasTools()->callSlug('vfs_edit', [
            'path' => '/docs/handbook/refund-policy',
            'edits' => [['old_text' => 'support@acme.test', 'new_text' => 'billing@acme.test']],
        ]));
        $push = $record(BridgeShim::TOOL_NAME, ['command' => 'push'], $shim->run(
            "cp /mnt/oc/docs/handbook/refund-policy ~/work/rp.md && sed -i 's/14 days/21 days/' ~/work/rp.md && oc vfs push ~/work/rp.md /docs/handbook/refund-policy",
        ));
        $unversioned = $record('VfsWrite', ['forge' => true], $shim->toolbox()->callSlug('vfs_write', [
            'path' => '/docs/handbook/refund-policy',
            'mode' => 'overwrite',
            'content' => "stale copy\n",
        ]));

        $content = (string) Document::find($this->world->ids['doc.refund'])->content;
        $checks = [
            'atlas edit committed' => (json_decode($atlas, true)['ok'] ?? false) === true,
            'stale push refused with conflict' => EvalAgentLoop::outcome($push)['error_code'] === 'conflict',
            'unversioned overwrite refused with invalid_request' => EvalAgentLoop::outcome($unversioned)['error_code'] === 'invalid_request',
            "Atlas's edit kept" => str_contains($content, 'billing@acme.test') && str_contains($content, '14 days'),
        ];
        $failed = array_keys(array_filter($checks, fn ($ok) => ! $ok));

        return [
            'steps' => $steps,
            'reply' => '',
            'scripted_ok' => $failed === [],
            'scripted_why' => $failed === [] ? 'stale push conflict; unversioned overwrite refused; Atlas edit kept' : 'failed: '.implode('; ', $failed),
        ];
    }

    private function gov03RequestAndApprove(): void
    {
        $result = json_decode($this->atlasTools()->callSlug('vfs_patch', [
            'path' => "/tables/pricing/rows/by-id/{$this->world->ids['row.price_pro']}.json",
            'patch' => json_encode(['data' => ['Price' => 109], 'base' => ['Price' => 99]]),
        ]), true);
        $approval = ApprovalRequest::query()->findOrFail($result['approval_id'] ?? null);

        // The approval replay re-queues the waiting agent's run (a real model
        // call through the app provider layer). The eval drives the model
        // itself, so only that dispatch is swallowed; the replayed tool call
        // runs synchronously inside resolve().
        $queue = Queue::getFacadeRoot();
        Queue::fake();
        try {
            app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->world->human);
        } finally {
            Queue::swap($queue);
        }
    }

    private function atlasTools(): EvalToolbox
    {
        return new EvalToolbox($this->world->atlas, 'tool');
    }

    private function forgeTools(): EvalToolbox
    {
        return new EvalToolbox($this->world->forge, 'computer');
    }
}
