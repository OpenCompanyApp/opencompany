<?php

namespace App\Domain\AgentRuntime\Application;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Delivers the visible agent message for a completed chat turn.
 *
 * This is intentionally separate from model generation and task bookkeeping
 * because message creation, generated-asset attachments, timestamps, Telegram
 * sync listeners, and broadcasts are the irreversible delivery side effects.
 */
class DeliverAgentMessage
{
    public function handle(User $agent, Message $userMessage, string $channelId, AgentResponse $response, string $responseText): Message
    {
        $agentMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $responseText,
            'channel_id' => $channelId,
            'author_id' => $agent->id,
            'reply_to_id' => $userMessage->reply_to_id,
            'timestamp' => now(),
        ]);

        $this->saveImageAttachments($agent, $response, $agentMessage);

        Channel::where('id', $channelId)->update(['last_message_at' => now()]);
        DirectMessage::where('channel_id', $channelId)->update(['last_message_at' => now()]);

        try {
            event(new MessageSent($agentMessage));
        } catch (\Throwable $broadcastError) {
            Log::warning('Failed to broadcast agent message', [
                'error' => $broadcastError->getMessage(),
                'channel' => $channelId,
            ]);
        }

        return $agentMessage;
    }

    private function saveImageAttachments(User $agent, AgentResponse $response, Message $message): void
    {
        try {
            foreach ($response->steps as $step) {
                foreach ($step->toolResults as $toolResult) {
                    if (! in_array($toolResult->name, ['RenderSvg', 'RenderMermaid', 'RenderPlantUml', 'RenderTypst', 'RenderVegaLite'], true)) {
                        continue;
                    }

                    $result = $toolResult->result ?? '';
                    if (! preg_match('#(/storage/(?:svg|mermaid|plantuml|vegalite)/[a-f0-9-]+\.png|/storage/typst/[a-f0-9-]+\.pdf)#', $result, $m)) {
                        continue;
                    }

                    $url = $m[1];
                    $filePath = storage_path('app/public/'.str_replace('/storage/', '', $url));

                    MessageAttachment::create([
                        'id' => Str::uuid()->toString(),
                        'message_id' => $message->id,
                        'filename' => basename($url),
                        'original_name' => basename($url),
                        'mime_type' => str_ends_with($url, '.pdf') ? 'application/pdf' : 'image/png',
                        'size' => file_exists($filePath) ? filesize($filePath) : 0,
                        'url' => $url,
                        'uploaded_by_id' => $agent->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to save image attachments', ['error' => $e->getMessage()]);
        }
    }
}
