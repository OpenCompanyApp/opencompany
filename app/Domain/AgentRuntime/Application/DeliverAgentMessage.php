<?php

namespace App\Domain\AgentRuntime\Application;

use App\Events\MessageSent;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Models\WorkspaceFile;
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
    public function handle(User $agent, Message $userMessage, string $channelId, AgentRunRecord $run, AgentResponse $response, string $responseText): Message
    {
        $agentMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $responseText,
            'channel_id' => $channelId,
            'author_id' => $agent->id,
            'reply_to_id' => $userMessage->reply_to_id,
            'timestamp' => now(),
            'agent_run_id' => $run->id,
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

    /**
     * Attach images rendered by render_svg during this turn to the message.
     *
     * RenderSvg stores its PNG as a workspace file and returns an
     * `/api/files/{id}/download` link; the file is looked up in the agent's
     * own workspace, so a link can never attach another workspace's file. The
     * package renderers (Mermaid, PlantUML, Typst, Vega-Lite) were removed
     * with the package system and are deliberately not matched here.
     */
    private function saveImageAttachments(User $agent, AgentResponse $response, Message $message): void
    {
        try {
            foreach ($response->steps as $step) {
                foreach ($step->toolResults as $toolResult) {
                    if (! in_array($toolResult->name, ['RenderSvg', 'render_svg'], true)) {
                        continue;
                    }

                    $result = (string) ($toolResult->result ?? '');
                    if (! preg_match('#/api/files/([0-9A-Za-z-]+)/download#', $result, $m)) {
                        continue;
                    }

                    $file = WorkspaceFile::query()
                        ->where('workspace_id', $agent->workspace_id)
                        ->find($m[1]);
                    if ($file === null) {
                        continue;
                    }

                    MessageAttachment::create([
                        'id' => Str::uuid()->toString(),
                        'message_id' => $message->id,
                        'filename' => $file->name,
                        'original_name' => $file->name,
                        'mime_type' => $file->mime_type ?? 'image/png',
                        'size' => (int) ($file->size ?? 0),
                        'url' => "/api/files/{$file->id}/download",
                        'uploaded_by_id' => $agent->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to save image attachments', ['error' => $e->getMessage()]);
        }
    }
}
