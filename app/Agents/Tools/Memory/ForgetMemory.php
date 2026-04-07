<?php

namespace App\Agents\Tools\Memory;

use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ForgetMemory implements Tool
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
        return 'Delete a memory topic or peer file that is no longer accurate or relevant. Also removes the entry from the MEMORY.md index.';
    }

    public function handle(Request $request): string
    {
        if ($this->scopeGuard && !$this->scopeGuard->canUseMemoryTools($this->agent, $this->channelId)) {
            return $this->scopeGuard->denialMessage('forget_memory');
        }

        $target = $request['target'] ?? '';

        if (empty($target)) {
            return 'Error: "target" is required. Use "topic" or "peer".';
        }

        return match ($target) {
            'topic' => $this->forgetTopic($request['topic'] ?? ''),
            'peer' => $this->forgetPeer($request['peer_id'] ?? '', $request['peer_type'] ?? ''),
            default => 'Error: target must be "topic" or "peer".',
        };
    }

    private function forgetTopic(string $slug): string
    {
        if (empty($slug)) {
            return 'Error: "topic" parameter is required when target is "topic".';
        }

        $doc = $this->docService->getMemoryTopicFile($this->agent, $slug);
        if (!$doc) {
            return "Topic '{$slug}' not found — nothing to forget.";
        }

        $this->indexer->deindex($doc);
        $this->docService->deleteMemoryTopic($this->agent, $slug);
        $this->docService->removeMemoryIndexEntry($this->agent, 'Topics', $slug);

        return "Topic '{$slug}' forgotten.";
    }

    private function forgetPeer(string $peerId, string $peerType): string
    {
        if (empty($peerId) || empty($peerType)) {
            return 'Error: "peer_id" and "peer_type" are required when target is "peer".';
        }

        if (!in_array($peerType, ['user', 'agent'])) {
            return 'Error: "peer_type" must be "user" or "agent".';
        }

        $doc = $this->docService->getPeerMemory($this->agent, $peerId, $peerType);
        if (!$doc) {
            $peer = User::find($peerId);
            $name = $peer?->name ?? $peerId;
            return "No peer memory found for '{$name}' — nothing to forget.";
        }

        $peer = User::find($peerId);
        $name = $peer?->name ?? $peerId;

        $this->indexer->deindex($doc);
        $this->docService->deletePeerMemory($this->agent, $peerId, $peerType);
        $this->docService->removeMemoryIndexEntry($this->agent, 'People', $name);

        return "Peer memory for '{$name}' forgotten.";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target' => $schema
                ->string()
                ->description('"topic" or "peer" — the type of memory to forget.')
                ->required(),
            'topic' => $schema
                ->string()
                ->description('Topic slug to forget. Required when target="topic".'),
            'peer_id' => $schema
                ->string()
                ->description('User or agent ID to forget. Required when target="peer".'),
            'peer_type' => $schema
                ->string()
                ->description('"user" or "agent". Required when target="peer".'),
        ];
    }
}
