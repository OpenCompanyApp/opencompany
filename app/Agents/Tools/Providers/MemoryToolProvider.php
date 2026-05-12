<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Memory\EditMemory;
use App\Agents\Tools\Memory\ForgetMemory;
use App\Agents\Tools\Memory\RecallMemory;
use App\Agents\Tools\Memory\SaveMemory;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers long-term memory tools.
 *
 * Memory files are stored as documents and indexed for recall, but MemoryScopeGuard
 * decides whether a call should touch topic memory or channel/peer-scoped memory.
 */
class MemoryToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'memory';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'save, recall, edit, forget',
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
            'edit_memory' => [
                'class' => EditMemory::class,
                'type' => 'write',
                'name' => 'Edit Memory',
                'description' => 'Edit an existing memory file (topic or peer).',
                'icon' => 'ph:brain',
            ],
            'forget_memory' => [
                'class' => ForgetMemory::class,
                'type' => 'write',
                'name' => 'Forget Memory',
                'description' => 'Delete a memory file (topic or peer).',
                'icon' => 'ph:brain',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Pass channel context through to the guard-aware tools so memories can
        // be attached to the right conversation scope when appropriate.
        return match ($class) {
            SaveMemory::class => new SaveMemory($agent, app(AgentDocumentService::class), app(DocumentIndexingService::class), app(MemoryScopeGuard::class), $context['channel_id'] ?? null),
            RecallMemory::class => new RecallMemory($agent, app(DocumentIndexingService::class), app(AgentDocumentService::class), app(MemoryScopeGuard::class), $context['channel_id'] ?? null),
            EditMemory::class => new EditMemory($agent, app(AgentDocumentService::class), app(DocumentIndexingService::class), app(MemoryScopeGuard::class), $context['channel_id'] ?? null),
            ForgetMemory::class => new ForgetMemory($agent, app(AgentDocumentService::class), app(DocumentIndexingService::class), app(MemoryScopeGuard::class), $context['channel_id'] ?? null),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
