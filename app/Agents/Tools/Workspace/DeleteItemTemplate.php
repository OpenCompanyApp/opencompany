<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListTemplate;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteItemTemplate implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a list item template.';
    }

    public function handle(Request $request): string
    {
        try {
            $templateId = $request['templateId'] ?? null;
            if (!$templateId) {
                return 'templateId is required.';
            }

            $template = ListTemplate::forWorkspace()->find($templateId);
            if (!$template) {
                return "Template not found: {$templateId}";
            }

            $name = $template->name;
            $template->delete();

            return "Template deleted: {$name}";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'templateId' => $schema
                ->string()
                ->description('Template UUID.')
                ->required(),
        ];
    }
}
