<?php

namespace App\Agents\Tools\Memory;

use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class EditMemory implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $docService,
        private DocumentIndexingService $indexer,
        private ?MemoryScopeGuard $scopeGuard = null,
        private ?string $channelId = null,
    ) {}

    public function description(): string
    {
        return 'Edit an existing memory topic or peer file in-place. Provide the new full content to replace the old.';
    }

    public function handle(Request $request): string
    {
        if ($this->scopeGuard && !$this->scopeGuard->canUseMemoryTools($this->agent, $this->channelId)) {
            return $this->scopeGuard->denialMessage('edit_memory');
        }

        $target = $request['target'] ?? '';
        $content = $request['content'] ?? '';

        if (empty($target) || empty($content)) {
            return 'Error: "target" and "content" are required.';
        }

        return match ($target) {
            'topic' => $this->editTopic($request['topic'] ?? '', $content),
            'peer' => $this->editPeer($request['peer_id'] ?? '', $request['peer_type'] ?? '', $content),
            default => 'Error: target must be "topic" or "peer".',
        };
    }

    private function editTopic(string $slug, string $content): string
    {
        if (empty($slug)) {
            return 'Error: "topic" parameter is required when target is "topic".';
        }

        $existing = $this->docService->getMemoryTopicFile($this->agent, $slug);
        if (!$existing) {
            return "Error: Topic '{$slug}' not found. Use save_memory to create a new topic.";
        }

        $doc = $this->docService->saveMemoryTopic($this->agent, $slug, $content);
        $this->indexer->index($doc, 'topic', $this->agent->id);

        return "Topic '{$slug}' updated.";
    }

    private function editPeer(string $peerId, string $peerType, string $content): string
    {
        if (empty($peerId) || empty($peerType)) {
            return 'Error: "peer_id" and "peer_type" are required when target is "peer".';
        }

        if (!in_array($peerType, ['user', 'agent'])) {
            return 'Error: "peer_type" must be "user" or "agent".';
        }

        $existing = $this->docService->getPeerMemory($this->agent, $peerId, $peerType);
        if (!$existing) {
            return "Error: No peer memory found for {$peerType} '{$peerId}'. Use save_memory to create one.";
        }

        $doc = $this->docService->savePeerMemory($this->agent, $peerId, $peerType, $content);
        $this->indexer->index($doc, 'peer', $this->agent->id);

        $peer = User::find($peerId);
        $name = $peer?->name ?? $peerId;

        return "Peer memory for '{$name}' updated.";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target' => $schema
                ->string()
                ->description('"topic" or "peer" — the type of memory to edit.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The new full content for this memory.')
                ->required(),
            'topic' => $schema
                ->string()
                ->description('Topic slug. Required when target="topic".'),
            'peer_id' => $schema
                ->string()
                ->description('User or agent ID. Required when target="peer".'),
            'peer_type' => $schema
                ->string()
                ->description('"user" or "agent". Required when target="peer".'),
        ];
    }
}
