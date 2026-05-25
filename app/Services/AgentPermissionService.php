<?php

namespace App\Services;

use App\Domain\Authorization\Application\AgentPermissionResolver;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Backward-compatible facade for agent authorization rules.
 *
 * New code should depend on `Domain\Authorization\Application` actions. This
 * service remains as the app-local compatibility API for existing runtime
 * checks, tools, and tests while the authorization context is migrated.
 */
class AgentPermissionService
{
    public function __construct(private AgentPermissionResolver $resolver) {}

    /**
     * @return array{allowed: bool, requires_approval: bool}
     */
    public function resolveToolPermission(User $agent, string $toolSlug, string $toolType): array
    {
        return $this->resolver->resolveToolPermission($agent, $toolSlug, $toolType);
    }

    /**
     * @return array{allowed: bool, can_request: bool}
     */
    public function canAccessChannel(User $agent, string $channelId): array
    {
        return $this->resolver->canAccessChannel($agent, $channelId);
    }

    /**
     * @return array<int, string>|null
     */
    public function getAllowedChannelIds(User $agent): ?array
    {
        return $this->resolver->getAllowedChannelIds($agent);
    }

    /**
     * @return array<int, string>|null
     */
    public function getAllowedFolderIds(User $agent): ?array
    {
        return $this->resolver->getAllowedFolderIds($agent);
    }

    /**
     * @return string[]
     */
    public function getEnabledIntegrations(User $agent): array
    {
        return $this->resolver->getEnabledIntegrations($agent);
    }

    public function isIntegrationEnabled(User $agent, string $appName): bool
    {
        return $this->resolver->isIntegrationEnabled($agent, $appName);
    }

    /**
     * @return array{allowed: bool, requires_approval: bool, can_request: bool}
     */
    public function canContactAgent(User $caller, User $target): array
    {
        return $this->resolver->canContactAgent($caller, $target);
    }

    /**
     * @return array<int, string>|null
     */
    public function getAllowedFileFolderIds(User $agent): ?array
    {
        return $this->resolver->getAllowedFileFolderIds($agent);
    }

    public function canAccessFilePath(User $agent, WorkspaceFile $file): bool
    {
        return $this->resolver->canAccessFilePath($agent, $file);
    }

    public function createAccessRequest(
        User $agent,
        string $scopeType,
        string $scopeKey,
        string $description,
    ): ApprovalRequest {
        return $this->resolver->createAccessRequest($agent, $scopeType, $scopeKey, $description);
    }
}
