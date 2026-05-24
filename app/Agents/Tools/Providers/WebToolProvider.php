<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Web\WebFetchTool;
use App\Agents\Tools\Web\WebSearchTool;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers app-owned non-browser web research tools.
 *
 * These are direct tools because search/fetch are core model-known primitives.
 * Provider credentials and URL policy are still enforced inside app-owned web
 * services before any network request is made.
 */
class WebToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'web';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'web_search, web_fetch',
            'description' => 'Non-browser web search and fetch',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:globe';
    }

    public function tools(): array
    {
        return [
            'web_search' => [
                'class' => WebSearchTool::class,
                'type' => 'read',
                'name' => 'Web Search',
                'description' => 'Search the web with configured provider adapters.',
                'icon' => 'ph:magnifying-glass',
            ],
            'web_fetch' => [
                'class' => WebFetchTool::class,
                'type' => 'read',
                'name' => 'Web Fetch',
                'description' => 'Fetch and extract web pages with direct/provider-backed adapters.',
                'icon' => 'ph:file-text',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        return app()->make($class, ['agent' => $agent]);
    }
}
