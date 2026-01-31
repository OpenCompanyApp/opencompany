<?php

namespace App\Services;

use App\Models\User;
use App\Models\Message;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AgentChatService
{
    public function respond(User $agent, string $channelId, string $userMessage): string
    {
        // Load recent conversation history (last 10 messages, excluding the one just sent)
        $history = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        // Build messages array for Prism using proper Message objects
        $messages = $history->map(function ($msg) use ($agent) {
            if ($msg->author_id === $agent->id) {
                return new AssistantMessage($msg->content);
            }
            return new UserMessage($msg->content);
        })->values()->all();

        // Simple system prompt
        $systemPrompt = "You are {$agent->name}, a helpful AI assistant.";

        // Call GLM 4.7 via Prism
        $response = Prism::text()
            ->using('glm', 'glm-4.7')
            ->withSystemPrompt($systemPrompt)
            ->withMessages($messages)
            ->asText();

        return $response->text;
    }
}
