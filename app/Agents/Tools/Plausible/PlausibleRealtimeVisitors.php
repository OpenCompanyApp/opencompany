<?php

namespace App\Agents\Tools\Plausible;

use App\Models\User;
use App\Services\PlausibleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PlausibleRealtimeVisitors implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Get the current number of realtime visitors on a website (visitors in the last 5 minutes).';
    }

    public function handle(Request $request): string
    {
        try {
            $plausible = app(PlausibleService::class);
            if (!$plausible->isConfigured()) {
                return 'Error: Plausible integration is not configured.';
            }

            $count = $plausible->realtimeVisitors($request['siteId']);

            return json_encode([
                'siteId' => $request['siteId'],
                'realtimeVisitors' => $count,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error getting realtime visitors: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'siteId' => $schema
                ->string()
                ->description('The site domain (e.g., "example.com").')
                ->required(),
        ];
    }
}
