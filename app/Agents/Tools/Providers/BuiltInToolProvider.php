<?php

namespace App\Agents\Tools\Providers;

use App\Models\User;

/**
 * Interface for built-in tool providers that organize app tools into groups.
 *
 * Built-in tools implement Laravel\Ai\Contracts\Tool (for the agent loop).
 * This is separate from integration-core's ToolProvider which returns
 * framework-agnostic IntegrationCore\Contracts\Tool instances.
 */
interface BuiltInToolProvider
{
    /**
     * Group name used as the app identifier (e.g., 'chat', 'docs', 'tables').
     */
    public function groupName(): string;

    /**
     * Group metadata for the system prompt catalog.
     *
     * @return array{label: string, description: string}
     */
    public function groupMeta(): array;

    /**
     * Iconify icon identifier for this group.
     */
    public function groupIcon(): string;

    /**
     * Tool definitions: slug => metadata.
     *
     * @return array<string, array{class: string, type: string, name: string, description: string, icon: string}>
     */
    public function tools(): array;

    /**
     * Create a tool instance for the given class.
     *
     * @param  array{channel_id?: string|null, task_id?: string|null}  $context
     */
    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool;
}
