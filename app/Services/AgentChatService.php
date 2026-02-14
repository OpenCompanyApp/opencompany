<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\Message;
use Illuminate\Support\Facades\Http;

class AgentChatService
{
    public function __construct(
        private AgentDocumentService $agentDocumentService
    ) {}

    public function respond(User $agent, string $channelId, string $userMessage): string
    {
        // Parse agent's brain setting (e.g., 'glm-coding:glm-4.7')
        $brain = $agent->brain ?? 'glm-coding:glm-4.7';
        $parts = explode(':', $brain, 2);
        $provider = $parts[0];
        $model = $parts[1] ?? 'glm-4.7';

        // Verify integration is enabled and get config
        $integration = IntegrationSetting::where('integration_id', $provider)
            ->where('enabled', true)
            ->first();

        if (!$integration) {
            return "I'm sorry, but my AI model provider ({$provider}) is not currently configured. Please ask an administrator to enable it in the Integrations settings.";
        }

        if (!$integration->hasValidConfig()) {
            return "I'm sorry, but my AI model provider ({$provider}) is not properly configured. Please ask an administrator to check the API settings.";
        }

        // Get API credentials from integration settings
        $apiKey = $integration->getConfigValue('api_key');
        $baseUrl = $integration->getConfigValue('url') ?? $this->getDefaultUrl($provider);

        // Load recent conversation history (last 10 messages)
        $history = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        // Build messages array for the API
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->author_id === $agent->id ? 'assistant' : 'user',
                'content' => $msg->content,
            ];
        }

        // Build system prompt from identity files
        $systemPrompt = $this->buildSystemPrompt($agent);

        // Call the configured AI model via HTTP
        return $this->callGlmApi($apiKey, $baseUrl, $model, $systemPrompt, $messages);
    }

    /**
     * Call GLM/Zhipu AI API directly
     *
     * @param  array<int, array<string, string>>  $messages
     */
    private function callGlmApi(string $apiKey, string $baseUrl, string $model, string $systemPrompt, array $messages): string
    {
        // Prepend system message
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$messages,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($baseUrl . '/chat/completions', [
            'model' => $model,
            'messages' => $apiMessages,
            'max_tokens' => 2048,
        ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content') ?? 'I apologize, but I received an empty response.';
        }

        $error = $response->json('error.message') ?? $response->body();
        throw new \Exception("AI API error: {$error}");
    }

    /**
     * Get default URL for a provider
     */
    private function getDefaultUrl(string $provider): string
    {
        return match ($provider) {
            'glm' => 'https://open.bigmodel.cn/api/paas/v4',
            'glm-coding' => 'https://api.z.ai/api/coding/paas/v4',
            default => throw new \Exception("Unknown provider: {$provider}"),
        };
    }

    /**
     * Build the system prompt from the agent's identity files
     */
    private function buildSystemPrompt(User $agent): string
    {
        $identityFiles = $this->agentDocumentService->getIdentityFiles($agent);

        $selfSection = "## You\n\n"
            . "- **ID**: {$agent->id}\n"
            . "- **Name**: {$agent->name}\n"
            . "- **Type**: {$agent->agent_type}\n"
            . "- **Brain**: {$agent->brain}\n"
            . "- **Behavior**: {$agent->behavior_mode}\n\n";

        $timezone = AppSetting::getValue('org_timezone', 'UTC');
        $now = now()->timezone($timezone);
        $timeSection = "## Current Time\n\n"
            . "- **DateTime**: {$now->format('l, F j, Y g:i A')} ({$timezone})\n"
            . "- **ISO**: {$now->toIso8601String()}\n\n";

        if ($identityFiles->isEmpty()) {
            return "You are {$agent->name}, a helpful AI assistant.\n\n" . $selfSection . $timeSection;
        }

        $prompt = "# Project Context\n\n";
        $prompt .= "You are an AI agent operating within a company workspace.\n\n";
        $prompt .= $selfSection;

        // Add identity files in a specific order for coherence
        $order = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'MEMORY', 'HEARTBEAT', 'BOOTSTRAP'];

        foreach ($order as $type) {
            $file = $identityFiles->firstWhere('title', "{$type}.md");
            if ($file && !empty(trim($file->content))) {
                $prompt .= "## {$type}.md\n\n{$file->content}\n\n";
            }
        }

        $prompt .= $timeSection;

        return $prompt;
    }
}
