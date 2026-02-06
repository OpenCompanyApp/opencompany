<?php

namespace App\Agents\Tools\Plausible;

use App\Models\User;
use App\Services\PlausibleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PlausibleDeleteGoal implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a conversion goal from a website in Plausible.';
    }

    public function handle(Request $request): string
    {
        try {
            $plausible = app(PlausibleService::class);
            if (!$plausible->isConfigured()) {
                return 'Error: Plausible integration is not configured.';
            }

            $plausible->deleteGoal($request['siteId'], (int) $request['goalId']);

            return "Goal {$request['goalId']} has been deleted from site '{$request['siteId']}'.";
        } catch (\Throwable $e) {
            return "Error deleting goal: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'siteId' => $schema
                ->string()
                ->description('The site domain (e.g., "example.com").')
                ->required(),
            'goalId' => $schema
                ->integer()
                ->description('The ID of the goal to delete.')
                ->required(),
        ];
    }
}
