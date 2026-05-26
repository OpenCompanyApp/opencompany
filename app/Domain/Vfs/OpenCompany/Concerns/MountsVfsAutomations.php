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
     * @return list<VfsEntry>
     */
    private function listAutomations(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) > 1) {
            throw VfsError::notReadable($path);
        }

        return Automation::forWorkspace()
            ->latest()
            ->limit($budget->maxEntries)
            ->get()
            ->map(fn (Automation $automation): VfsEntry => $this->automationEntry($automation, "/automations/{$automation->id}.json"))
            ->all();
    }

    private function readAutomations(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listAutomations($agent, $segments, $path, $budget));
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        $automation = $this->resolveAutomationToken(Str::before($segments[1] ?? '', '.json'), $path);

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
            metadata: ['name' => $automation->name, 'active' => $automation->is_active],
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

        $matches = Automation::forWorkspace()->get()->filter(fn (Automation $candidate): bool => $this->slug($candidate->name) === $token);
        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->count() > 1) {
            throw VfsError::invalid("Automation slug is ambiguous: {$token}. Use /automations/{uuid}.json.");
        }

        throw VfsError::notFound($path);
    }
}
