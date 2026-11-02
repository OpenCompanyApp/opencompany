<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Read-only automation mount.
 */
trait MountsVfsAutomations
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listAutomations(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageAutomations($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Page workspace-owned automations by creation time and id. Updated runtime
     * fields such as next_run_at must not move records across a browse cursor.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageAutomations(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) > 1) {
            throw VfsError::notReadable($path);
        }

        return $this->paginateVfsTimestampQuery(
            Automation::forWorkspace(),
            'created_at',
            'desc',
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (Automation $automation): VfsEntry => $this->automationEntry($automation, "/automations/{$automation->id}.json"),
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function readAutomations(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listAutomations($agent, $segments, $path, $budget));
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        $automation = $this->resolveAutomationToken(Str::before($segments[1], '.json'), $path);

        return json_encode($automation->toArray(), JSON_PRETTY_PRINT);
    }

    private function automationEntry(Automation $automation, string $path): VfsEntry
    {
        return new VfsEntry(
            $this->slug($automation->name).'--'.substr($automation->id, 0, 8).'.json',
            $path,
            'file',
            "/automations/{$automation->id}.json",
            'automation',
            $automation->id,
            capabilities: ['read'],
            metadata: [
                'name' => $automation->name,
                'active' => $automation->is_active,
                ...$this->readOnlyMetadata(
                    'Automation JSON is a generated automation record.',
                    'automation tools',
                    'Use automation tools to create, update, run, pause, or delete automations.',
                ),
            ],
        );
    }

    private function resolveAutomationToken(string $token, string $path): Automation
    {
        if (preg_match('/^(.*)--([a-f0-9]{8})$/i', $token, $parts) === 1) {
            return Automation::forWorkspace()
                ->where('id', 'like', $parts[2].'%')
                ->get()
                ->first(fn (Automation $candidate): bool => $this->slug($candidate->name) === $parts[1])
                ?? throw VfsError::notFound($path);
        }

        $byId = Automation::forWorkspace()->find($token);
        if ($byId) {
            return $byId;
        }

        $matches = Automation::forWorkspace()
            ->lazy(100)
            ->filter(fn (Automation $candidate): bool => $this->slug($candidate->name) === $token)
            ->take(2);
        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->count() > 1) {
            throw VfsError::invalid("Automation slug is ambiguous: {$token}. Use /automations/{uuid}.json.");
        }

        throw VfsError::notFound($path);
    }
}
