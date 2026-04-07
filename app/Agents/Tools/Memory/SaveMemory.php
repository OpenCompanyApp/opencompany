<?php

namespace App\Agents\Tools\Memory;

use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SaveMemory implements Tool
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
        return 'Save a durable memory that persists across conversations. Use target "core" for high-value facts (always in your system prompt), "topic" for knowledge files, "log" for timestamped daily entries, or "peer" for notes about a specific user or agent.';
    }

    public function handle(Request $request): string
    {
        if ($this->scopeGuard && !$this->scopeGuard->canUseMemoryTools($this->agent, $this->channelId)) {
            return $this->scopeGuard->denialMessage('save_memory');
        }

        $content = $request['content'] ?? '';
        $category = $request['category'] ?? 'general';
        $target = $request['target'] ?? 'log';

        if (empty($content)) {
            return 'Error: "content" is required.';
        }

        return match ($target) {
            'core' => $this->saveToCoreMemory($content, $category),
            'topic' => $this->saveToTopic($request['topic'] ?? '', $content),
            'log' => $this->saveToDailyLog($content, $category),
            'peer' => $this->saveToPeerMemory(
                $request['peer_id'] ?? '',
                $request['peer_type'] ?? '',
                $content,
            ),
            default => 'Error: Unknown target. Use "core", "topic", "log", or "peer".',
        };
    }

    private function saveToCoreMemory(string $content, string $category): string
    {
        $memoryFile = $this->docService->getIdentityFile($this->agent, 'MEMORY');

        if (!$memoryFile) {
            return 'Error: MEMORY.md not found for this agent.';
        }

        $newContent = $memoryFile->content . "\n\n### {$category}\n{$content}";
        $this->docService->updateIdentityFile($this->agent, 'MEMORY', $newContent);

        // Reindex so recall_memory can find it
        $this->indexer->index($memoryFile->fresh(), 'memory', $this->agent->id);

        return 'Core memory saved to MEMORY.md (always loaded in your system prompt).';
    }

    private function saveToTopic(string $slug, string $content): string
    {
        if (empty($slug)) {
            return 'Error: "topic" parameter is required when target is "topic".';
        }

        $doc = $this->docService->saveMemoryTopic($this->agent, $slug, $content);
        if (!$doc) {
            return 'Error: Could not save topic. Agent document structure may not be initialized.';
        }

        $this->docService->updateMemoryIndex($this->agent, 'Topics', $slug, "topics/{$slug}.md");
        $this->indexer->index($doc, 'topic', $this->agent->id);

        return "Topic memory saved to topics/{$slug}.md (recallable via recall_memory with topic parameter).";
    }

    private function saveToDailyLog(string $content, string $category): string
    {
        $entry = "### [{$category}] " . now()->format('H:i') . "\n\n{$content}";
        $doc = $this->docService->createMemoryLog($this->agent, $entry);

        if (!$doc) {
            return 'Error: Could not save memory. Agent document structure may not be initialized.';
        }

        // Index for semantic recall
        $this->indexer->index($doc, 'memory', $this->agent->id);

        return "Memory saved to {$doc->title} (recallable via recall_memory).";
    }

    private function saveToPeerMemory(string $peerId, string $peerType, string $content): string
    {
        if (empty($peerId) || empty($peerType)) {
            return 'Error: "peer_id" and "peer_type" are required when target is "peer".';
        }

        if (!in_array($peerType, ['user', 'agent'])) {
            return 'Error: "peer_type" must be "user" or "agent".';
        }

        $peer = User::find($peerId);
        $peerName = $peer?->name ?? $peerId;

        $doc = $this->docService->savePeerMemory($this->agent, $peerId, $peerType, $content);
        if (!$doc) {
            return 'Error: Could not save peer memory. Agent document structure may not be initialized.';
        }

        $this->docService->updateMemoryIndex(
            $this->agent,
            'People',
            "{$peerName} ({$peerType})",
            "peers/{$peerType}s/{$peerId}.md",
        );
        $this->indexer->index($doc, 'peer', $this->agent->id);

        return "Peer memory saved for {$peerType} '{$peerName}' (loaded when talking to them).";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema
                ->string()
                ->description('The memory content to save. Be specific and include context.')
                ->required(),
            'target' => $schema
                ->string()
                ->description('Where to save: "core" writes to MEMORY.md (always in system prompt — use for high-value durable facts), "topic" creates a knowledge file, "log" appends to daily log (default), "peer" saves notes about a specific user or agent. Default: "log".'),
            'category' => $schema
                ->string()
                ->description('Category tag: "preference", "decision", "learning", "fact", or "general". Used for core and log targets. Default: general.'),
            'topic' => $schema
                ->string()
                ->description('Topic slug (e.g., "vue3-migration"). Required when target="topic". Used as the filename.'),
            'peer_id' => $schema
                ->string()
                ->description('User or agent ID. Required when target="peer".'),
            'peer_type' => $schema
                ->string()
                ->description('Either "user" or "agent". Required when target="peer".'),
        ];
    }
}
