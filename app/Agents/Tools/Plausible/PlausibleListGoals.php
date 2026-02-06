<?php

namespace App\Agents\Tools\Plausible;

use App\Models\User;
use App\Services\PlausibleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PlausibleListGoals implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all goals (conversion tracking) configured for a website in Plausible.';
    }

    public function handle(Request $request): string
    {
        try {
            $plausible = app(PlausibleService::class);
            if (!$plausible->isConfigured()) {
                return 'Error: Plausible integration is not configured.';
            }

            $result = $plausible->listGoals($request['siteId']);

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error listing goals: {$e->getMessage()}";
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
