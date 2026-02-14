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
        return 'Save a durable memory that persists across conversations. Use target "core" for high-value facts (always in your system prompt), or "log" for timestamped daily entries (searchable via recall_memory).';
    }

    public function handle(Request $request): string
    {
        if ($this->scopeGuard && !$this->scopeGuard->canUseMemoryTools($this->agent, $this->channelId)) {
            return $this->scopeGuard->denialMessage('save_memory');
        }

        $content = $request['content'];
        $category = $request['category'] ?? 'general';
        $target = $request['target'] ?? 'log';

        if ($target === 'core') {
            return $this->saveToCoreMemory($content, $category);
        }

        return $this->saveToDailyLog($content, $category);
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
        $this->indexer->index($memoryFile->fresh(), 'identity', $this->agent->id);

        return 'Core memory saved to MEMORY.md (always loaded in your system prompt).';
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema
                ->string()
                ->description('The memory content to save. Be specific and include context.')
                ->required(),
            'category' => $schema
                ->string()
                ->description('Category tag: "preference", "decision", "learning", "fact", or "general". Default: general.'),
            'target' => $schema
                ->string()
                ->description('Where to save: "core" writes to MEMORY.md (always in your system prompt — use for high-value durable facts), "log" appends to daily log (searchable via recall_memory). Default: log.'),
        ];
    }
}
