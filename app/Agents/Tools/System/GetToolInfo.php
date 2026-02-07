<?php

namespace App\Agents\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetToolInfo implements Tool
{
    public function __construct(
        private User $agent,
        private ToolRegistry $toolRegistry,
    ) {}

    public function description(): string
    {
        return 'Get detailed parameter info for a tool or app before using it.';
    }

    public function handle(Request $request): string
    {
        $name = $request['name'] ?? '';

        if (empty($name)) {
            return 'Please provide a tool or app name. Apps: chat, docs, tables, calendar, lists, tasks, telegram, system';
        }

        return $this->toolRegistry->getToolDetail($name, $this->agent);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Tool slug (e.g. "manage_table") or app name (e.g. "tables").')
                ->required(),
        ];
    }
}
