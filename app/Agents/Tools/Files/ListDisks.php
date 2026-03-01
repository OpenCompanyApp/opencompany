<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Models\WorkspaceDisk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDisks implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List available storage disks in the workspace. Returns disk names, drivers, and which one is the default.';
    }

    public function handle(Request $request): string
    {
        try {
            $disks = WorkspaceDisk::where('workspace_id', $this->agent->workspace_id)
                ->where('enabled', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();

            if ($disks->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($disks->map(fn (WorkspaceDisk $disk) => [
                'id' => $disk->id,
                'name' => $disk->name,
                'driver' => $disk->driver,
                'isDefault' => $disk->is_default,
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing disks: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
