<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Memory\RecallMemory;
use App\Agents\Tools\Memory\SaveMemory;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;

class MemoryToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'memory';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'save, recall',
            'description' => 'Long-term agent memory',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:brain';
    }

    public function tools(): array
    {
        return [
            'save_memory' => [
                'class' => SaveMemory::class,
                'type' => 'write',
                'name' => 'Save Memory',
                'description' => 'Save a durable memory that persists across conversations.',
                'icon' => 'ph:brain',
            ],
            'recall_memory' => [
                'class' => RecallMemory::class,
                'type' => 'read',
                'name' => 'Recall Memory',
                'description' => 'Search long-term memory for past information and learnings.',
                'icon' => 'ph:brain',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return match ($class) {
            SaveMemory::class => new SaveMemory($agent, app(AgentDocumentService::class), app(DocumentIndexingService::class), app(MemoryScopeGuard::class), $context['channel_id'] ?? null),
            RecallMemory::class => new RecallMemory($agent, app(DocumentIndexingService::class), app(AgentDocumentService::class), app(MemoryScopeGuard::class), $context['channel_id'] ?? null),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
