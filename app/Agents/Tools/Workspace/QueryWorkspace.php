<?php

namespace App\Agents\Tools\Workspace;

use App\Agents\Tools\ToolRegistry;
use App\Models\IntegrationSetting;
use App\Models\ListAutomationRule;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class QueryWorkspace implements Tool
{
    public function __construct(
        private AgentPermissionService $permissionService,
        private ToolRegistry $toolRegistry,
    ) {}

    public function description(): string
    {
        return 'Read-only workspace introspection: list agents, get agent details, view permissions, integrations, and models.';
    }

    public function handle(Request $request): string
    {
        try {
            return match ($request['action']) {
                'list_agents' => $this->listAgents(),
                'get_agent' => $this->getAgent($request),
                'get_agent_permissions' => $this->getAgentPermissions($request),
                'list_integrations' => $this->listIntegrations(),
                'get_integration_config' => $this->getIntegrationConfig($request),
                'list_available_models' => $this->listAvailableModels(),
                'list_automation_rules' => $this->listAutomationRules(),
                default => "Unknown action: {$request['action']}. Use: list_agents, get_agent, get_agent_permissions, list_integrations, get_integration_config, list_available_models, list_automation_rules",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function listAgents(): string
    {
        $agents = User::where('type', 'agent')->orderBy('name')->get();

        if ($agents->isEmpty()) {
            return 'No agents found in workspace.';
        }

        $lines = ["Agents ({$agents->count()}):"];
        foreach ($agents as $agent) {
            $status = $agent->status ?? 'unknown';
            $behavior = $agent->behavior_mode ?? 'autonomous';
            $lines[] = "- {$agent->name} (ID: {$agent->id}) — type: {$agent->agent_type}, brain: {$agent->brain}, status: {$status}, behavior: {$behavior}";
        }

        return implode("\n", $lines);
    }

    private function getAgent(Request $request): string
    {
        $agentId = $request['agentId'] ?? null;
        if (!$agentId) {
            return 'agentId is required.';
        }

        $agent = User::where('type', 'agent')->find($agentId);
        if (!$agent) {
            return "Agent not found: {$agentId}";
        }

        $taskStats = Task::where('agent_id', $agent->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        $lines = [
            "Agent: {$agent->name}",
            "ID: {$agent->id}",
            "Type: {$agent->agent_type}",
            "Brain: {$agent->brain}",
            "Status: {$agent->status}",
            "Behavior Mode: " . ($agent->behavior_mode ?? 'autonomous'),
            "Must Wait For Approval: " . ($agent->must_wait_for_approval ? 'yes' : 'no'),
            "Current Task: " . ($agent->current_task ?? 'none'),
            "Tasks: " . (int) ($taskStats->completed ?? 0) . " completed / " . (int) ($taskStats->total ?? 0) . " total",
        ];

        return implode("\n", $lines);
    }

    private function getAgentPermissions(Request $request): string
    {
        $agentId = $request['agentId'] ?? null;
        if (!$agentId) {
            return 'agentId is required.';
        }

        $agent = User::where('type', 'agent')->find($agentId);
        if (!$agent) {
            return "Agent not found: {$agentId}";
        }

        $tools = $this->toolRegistry->getAllToolsMeta($agent);
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);
        $channelIds = $agent->channelPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
        $folderIds = $agent->folderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();

        $lines = [
            "Permissions for {$agent->name}:",
            '',
            'Behavior Mode: ' . ($agent->behavior_mode ?? 'autonomous'),
            '',
            'Enabled Integrations: ' . ($enabledIntegrations ? implode(', ', $enabledIntegrations) : 'all (no restrictions)'),
            'Allowed Channels: ' . ($channelIds->isEmpty() ? 'unrestricted' : $channelIds->implode(', ')),
            'Allowed Folders: ' . ($folderIds->isEmpty() ? 'unrestricted' : $folderIds->implode(', ')),
            '',
            'Tools:',
        ];

        foreach ($tools as $tool) {
            $status = $tool['enabled'] ? 'enabled' : 'DENIED';
            $approval = $tool['requiresApproval'] ? ' (requires approval)' : '';
            $lines[] = "- {$tool['id']}: {$status}{$approval}";
        }

        return implode("\n", $lines);
    }

    private function listIntegrations(): string
    {
        $settings = IntegrationSetting::all()->keyBy('integration_id');
        $available = IntegrationSetting::getAvailableIntegrations();

        $lines = ['Available Integrations:'];

        // Static integrations
        foreach ($available as $id => $info) {
            /** @var \App\Models\IntegrationSetting|null $setting */
            $setting = $settings->get($id);
            $enabled = ($setting && $setting->enabled) ? 'enabled' : 'disabled';
            $configured = ($setting && $setting->hasValidConfig()) ? 'configured' : 'not configured';
            $lines[] = "- {$id}: {$info['name']} — {$enabled}, {$configured}";
        }

        // Dynamic integrations from packages
        foreach (app(ToolProviderRegistry::class)->all() as $provider) {
            if (!$provider instanceof ConfigurableIntegration) {
                continue;
            }
            $id = $provider->appName();
            if (isset($available[$id])) {
                continue; // Already listed as static
            }
            $meta = $provider->integrationMeta();
            /** @var \App\Models\IntegrationSetting|null $setting */
            $setting = $settings->get($id);
            $enabled = ($setting && $setting->enabled) ? 'enabled' : 'disabled';
            $hasConfig = $setting && !empty($setting->config);
            $configured = $hasConfig ? 'configured' : 'not configured';
            $lines[] = "- {$id}: {$meta['name']} — {$enabled}, {$configured}";
        }

        return implode("\n", $lines);
    }

    private function getIntegrationConfig(Request $request): string
    {
        $integrationId = $request['integrationId'] ?? null;
        if (!$integrationId) {
            return 'integrationId is required.';
        }

        // Check dynamic providers first
        $provider = app(ToolProviderRegistry::class)->get($integrationId);
        if ($provider instanceof ConfigurableIntegration) {
            return $this->getDynamicIntegrationConfig($integrationId, $provider);
        }

        // Static providers
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$integrationId])) {
            $allIds = array_keys($available);
            foreach (app(ToolProviderRegistry::class)->all() as $p) {
                if ($p instanceof ConfigurableIntegration) {
                    $allIds[] = $p->appName();
                }
            }

            return "Integration not found: {$integrationId}. Available: " . implode(', ', $allIds);
        }

        /** @var \App\Models\IntegrationSetting|null $setting */
        $setting = IntegrationSetting::where('integration_id', $integrationId)->first();
        $info = $available[$integrationId];

        $lines = [
            "Integration: {$info['name']} ({$integrationId})",
            "Description: {$info['description']}",
            "Enabled: " . (($setting && $setting->enabled) ? 'yes' : 'no'),
            "Configured: " . (($setting?->hasValidConfig() ?? false) ? 'yes' : 'no'),
            "API Key: " . ($setting?->getMaskedApiKey() ?? 'not set'),
        ];

        if ($integrationId === 'telegram') {
            $lines[] = "Default Agent ID: " . ($setting?->getConfigValue('default_agent_id') ?? 'not set');
            $lines[] = "Notify Chat ID: " . ($setting?->getConfigValue('notify_chat_id') ?? 'not set');
            $allowed = $setting?->getConfigValue('allowed_telegram_users', []) ?? [];
            $lines[] = "Allowed Telegram Users: " . ($allowed ? implode(', ', $allowed) : 'none');
        } else {
            $lines[] = "URL: " . ($setting?->getConfigValue('url') ?? ($info['default_url'] ?? 'not set'));
            $lines[] = "Default Model: " . ($setting?->getConfigValue('default_model') ?? 'not set');
            if (!empty($info['models'])) {
                $lines[] = "Available Models: " . implode(', ', array_keys($info['models']));
            }
        }

        return implode("\n", $lines);
    }

    private function getDynamicIntegrationConfig(string $integrationId, ConfigurableIntegration $provider): string
    {
        $meta = $provider->integrationMeta();
        $schema = $provider->configSchema();
        /** @var \App\Models\IntegrationSetting|null $setting */
        $setting = IntegrationSetting::where('integration_id', $integrationId)->first();
        $config = $setting ? $setting->config : [];

        $lines = [
            "Integration: {$meta['name']} ({$integrationId})",
            "Description: {$meta['description']}",
            "Enabled: " . (($setting && $setting->enabled) ? 'yes' : 'no'),
        ];

        /** @var array{key: string, type: string, label: string, default?: mixed} $field */
        foreach ($schema as $field) {
            $value = $config[$field['key']] ?? null;

            if ($field['type'] === 'secret') {
                $masked = $setting?->getMaskedValue($field['key']) ?? 'not set';
                $lines[] = "{$field['label']}: {$masked}";
            } elseif ($field['type'] === 'string_list') {
                $items = is_array($value) ? $value : [];
                $lines[] = "{$field['label']}: " . ($items ? implode(', ', $items) : 'none');
            } else {
                $display = $value ?? ($field['default'] ?? 'not set');
                $lines[] = "{$field['label']}: {$display}";
            }
        }

        return implode("\n", $lines);
    }

    private function listAvailableModels(): string
    {
        $settings = IntegrationSetting::where('enabled', true)->get();
        $available = IntegrationSetting::getAvailableIntegrations();

        $lines = ['Available AI Models:'];
        $count = 0;

        foreach ($settings as $setting) {
            if (!isset($available[$setting->integration_id])) {
                continue;
            }

            $info = $available[$setting->integration_id];
            if (empty($info['models'])) {
                continue;
            }

            foreach ($info['models'] as $modelId => $modelName) {
                $brainId = $setting->integration_id . ':' . $modelId;
                $lines[] = "- {$brainId} — {$modelName} ({$info['name']})";
                $count++;
            }
        }

        if ($count === 0) {
            return 'No AI models available. Enable integrations first.';
        }

        return implode("\n", $lines);
    }

    private function listAutomationRules(): string
    {
        $rules = ListAutomationRule::with('template', 'createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($rules->isEmpty()) {
            return 'No automation rules found.';
        }

        $lines = ["Automation Rules ({$rules->count()}):"];
        foreach ($rules as $rule) {
            $active = $rule->is_active ? 'active' : 'inactive';
            $template = $rule->template ? " (template: {$rule->template->name})" : '';
            $lines[] = "- {$rule->name} (ID: {$rule->id}) — trigger: {$rule->trigger_type}, action: {$rule->action_type}, {$active}{$template}";
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'list_agents', 'get_agent', 'get_agent_permissions', 'list_integrations', 'get_integration_config', 'list_available_models', 'list_automation_rules'")
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('Agent UUID. Required for get_agent and get_agent_permissions.'),
            'integrationId' => $schema
                ->string()
                ->description('Integration ID (e.g. "telegram", "openai"). Required for get_integration_config.'),
        ];
    }
}
