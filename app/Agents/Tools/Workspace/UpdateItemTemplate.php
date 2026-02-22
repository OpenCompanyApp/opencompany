<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListTemplate;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateItemTemplate implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing list item template.';
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

            $updates = [];

            if (isset($request['name'])) {
                $updates['name'] = $request['name'];
            }
            if (isset($request['defaultTitle'])) {
                $updates['default_title'] = $request['defaultTitle'];
            }
            if (isset($request['defaultPriority'])) {
                $updates['default_priority'] = $request['defaultPriority'];
            }
            if (isset($request['isActive'])) {
                $updates['is_active'] = filter_var($request['isActive'], FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($updates)) {
                return 'No fields to update.';
            }

            $template->update($updates);

            return "Template updated: {$template->name} (ID: {$template->id})";
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
            'name' => $schema
                ->string()
                ->description('New template name.'),
            'defaultTitle' => $schema
                ->string()
                ->description('Default title for template items.'),
            'defaultPriority' => $schema
                ->string()
                ->description("Default priority: 'low', 'medium', 'high', 'urgent'."),
            'isActive' => $schema
                ->string()
                ->description("'true' or 'false' to activate/deactivate."),
        ];
    }
}
