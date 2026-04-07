<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Workspace\AddChannelMember;
use App\Agents\Tools\Workspace\AddMcpServer;
use App\Agents\Tools\Workspace\CreateAgent;
use App\Agents\Tools\Workspace\CreateChannel;
use App\Agents\Tools\Workspace\DeleteAgent;
use App\Agents\Tools\Workspace\DiscoverMcpTools;
use App\Agents\Tools\Workspace\GetAgentDetails;
use App\Agents\Tools\Workspace\GetAgentPermissions;
use App\Agents\Tools\Workspace\GetIntegrationConfig;
use App\Agents\Tools\Workspace\GetIntegrationSetup;
use App\Agents\Tools\Workspace\LinkExternalUser;
use App\Agents\Tools\Workspace\ListAgents;
use App\Agents\Tools\Workspace\ListAvailableModels;
use App\Agents\Tools\Workspace\ListIntegrations;
use App\Agents\Tools\Workspace\ListMcpServers;
use App\Agents\Tools\Workspace\ListMembers;
use App\Agents\Tools\Workspace\RemoveChannelMember;
use App\Agents\Tools\Workspace\RemoveMcpServer;
use App\Agents\Tools\Workspace\ReadAgentIdentityFile;
use App\Agents\Tools\Workspace\SetupIntegrationWebhook;
use App\Agents\Tools\Workspace\TestIntegrationConnection;
use App\Agents\Tools\Workspace\TestMcpServer;
use App\Agents\Tools\Workspace\UpdateAgent;
use App\Agents\Tools\Workspace\UpdateAgentChannelAccess;
use App\Agents\Tools\Workspace\UpdateAgentFileFolderAccess;
use App\Agents\Tools\Workspace\UpdateAgentFolderAccess;
use App\Agents\Tools\Workspace\UpdateAgentIdentityFile;
use App\Agents\Tools\Workspace\UpdateAgentIntegrationAccess;
use App\Agents\Tools\Workspace\UpdateAgentToolPermissions;
use App\Agents\Tools\Workspace\UpdateIntegrationConfig;
use App\Agents\Tools\Workspace\UpdateMcpServer;
use App\Models\User;
use App\Services\AgentAvatarService;
use App\Services\AgentDocumentService;
use App\Services\AgentPermissionService;

class WorkspaceToolProvider implements BuiltInToolProvider
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    public function groupName(): string
    {
        return 'workspace';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'agents, members, permissions, integrations, mcp, channels',
            'description' => 'Workspace management',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:gear-six';
    }

    public function tools(): array
    {
        return [
            'list_members' => [
                'class' => ListMembers::class,
                'type' => 'read',
                'name' => 'List Members',
                'description' => 'List all human members of the workspace.',
                'icon' => 'ph:users',
            ],
            'get_agent_details' => [
                'class' => GetAgentDetails::class,
                'type' => 'read',
                'name' => 'Get Agent Details',
                'description' => 'Get detailed information about a specific agent.',
                'icon' => 'ph:robot',
            ],
            'get_agent_permissions' => [
                'class' => GetAgentPermissions::class,
                'type' => 'read',
                'name' => 'Get Agent Permissions',
                'description' => 'View an agent\'s tool, channel, folder, and integration permissions.',
                'icon' => 'ph:shield-check',
            ],
            'list_integrations' => [
                'class' => ListIntegrations::class,
                'type' => 'read',
                'name' => 'List Integrations',
                'description' => 'List configured integrations and their status.',
                'icon' => 'ph:plugs-connected',
            ],
            'get_integration_config' => [
                'class' => GetIntegrationConfig::class,
                'type' => 'read',
                'name' => 'Get Integration Config',
                'description' => 'Get configuration details for a specific integration.',
                'icon' => 'ph:plugs-connected',
            ],
            'list_available_models' => [
                'class' => ListAvailableModels::class,
                'type' => 'read',
                'name' => 'List Available Models',
                'description' => 'List available AI models that agents can use.',
                'icon' => 'ph:brain',
            ],
            'create_agent' => [
                'class' => CreateAgent::class,
                'type' => 'write',
                'name' => 'Create Agent',
                'description' => 'Create a new agent in the workspace.',
                'icon' => 'ph:robot',
            ],
            'update_agent' => [
                'class' => UpdateAgent::class,
                'type' => 'write',
                'name' => 'Update Agent',
                'description' => 'Update an agent\'s name, brain, status, or behavior mode.',
                'icon' => 'ph:robot',
            ],
            'delete_agent' => [
                'class' => DeleteAgent::class,
                'type' => 'write',
                'name' => 'Delete Agent',
                'description' => 'Delete an agent from the workspace.',
                'icon' => 'ph:robot',
            ],
            'read_agent_identity_file' => [
                'class' => ReadAgentIdentityFile::class,
                'type' => 'read',
                'name' => 'Read Agent Identity File',
                'description' => 'Read an agent\'s identity file (IDENTITY, INSTRUCTIONS, or MEMORY).',
                'icon' => 'ph:file-text',
            ],
            'update_agent_identity_file' => [
                'class' => UpdateAgentIdentityFile::class,
                'type' => 'write',
                'name' => 'Update Agent Identity File',
                'description' => 'Update an agent\'s identity file content.',
                'icon' => 'ph:file-text',
            ],
            'update_agent_tool_permissions' => [
                'class' => UpdateAgentToolPermissions::class,
                'type' => 'write',
                'name' => 'Update Agent Tool Permissions',
                'description' => 'Update tool permissions for an agent.',
                'icon' => 'ph:shield-check',
            ],
            'update_agent_channel_access' => [
                'class' => UpdateAgentChannelAccess::class,
                'type' => 'write',
                'name' => 'Update Agent Channel Access',
                'description' => 'Update channel access for an agent.',
                'icon' => 'ph:shield-check',
            ],
            'update_agent_folder_access' => [
                'class' => UpdateAgentFolderAccess::class,
                'type' => 'write',
                'name' => 'Update Agent Folder Access',
                'description' => 'Update document folder access for an agent.',
                'icon' => 'ph:shield-check',
            ],
            'update_agent_file_folder_access' => [
                'class' => UpdateAgentFileFolderAccess::class,
                'type' => 'write',
                'name' => 'Update Agent File Folder Access',
                'description' => 'Update file storage folder access for an agent.',
                'icon' => 'ph:shield-check',
            ],
            'update_agent_integration_access' => [
                'class' => UpdateAgentIntegrationAccess::class,
                'type' => 'write',
                'name' => 'Update Agent Integration Access',
                'description' => 'Update integration access for an agent.',
                'icon' => 'ph:shield-check',
            ],
            'get_integration_setup' => [
                'class' => GetIntegrationSetup::class,
                'type' => 'read',
                'name' => 'Get Integration Setup',
                'description' => 'Get setup information and schema for an integration.',
                'icon' => 'ph:plugs-connected',
            ],
            'update_integration_config' => [
                'class' => UpdateIntegrationConfig::class,
                'type' => 'write',
                'name' => 'Update Integration Config',
                'description' => 'Update configuration settings for an integration.',
                'icon' => 'ph:plugs-connected',
            ],
            'test_integration_connection' => [
                'class' => TestIntegrationConnection::class,
                'type' => 'write',
                'name' => 'Test Integration Connection',
                'description' => 'Test connectivity to an integration.',
                'icon' => 'ph:plugs-connected',
            ],
            'setup_integration_webhook' => [
                'class' => SetupIntegrationWebhook::class,
                'type' => 'write',
                'name' => 'Setup Integration Webhook',
                'description' => 'Configure a webhook for an integration.',
                'icon' => 'ph:plugs-connected',
            ],
            'link_external_user' => [
                'class' => LinkExternalUser::class,
                'type' => 'write',
                'name' => 'Link External User',
                'description' => 'Link an external identity to a workspace user.',
                'icon' => 'ph:link',
            ],
            'list_mcp_servers' => [
                'class' => ListMcpServers::class,
                'type' => 'read',
                'name' => 'List MCP Servers',
                'description' => 'List all configured MCP servers.',
                'icon' => 'ph:plugs-connected',
            ],
            'add_mcp_server' => [
                'class' => AddMcpServer::class,
                'type' => 'write',
                'name' => 'Add MCP Server',
                'description' => 'Add a new MCP server and auto-discover its tools.',
                'icon' => 'ph:plugs-connected',
            ],
            'update_mcp_server' => [
                'class' => UpdateMcpServer::class,
                'type' => 'write',
                'name' => 'Update MCP Server',
                'description' => 'Update an MCP server\'s configuration.',
                'icon' => 'ph:plugs-connected',
            ],
            'remove_mcp_server' => [
                'class' => RemoveMcpServer::class,
                'type' => 'write',
                'name' => 'Remove MCP Server',
                'description' => 'Remove an MCP server and its tools.',
                'icon' => 'ph:plugs-connected',
            ],
            'test_mcp_server' => [
                'class' => TestMcpServer::class,
                'type' => 'write',
                'name' => 'Test MCP Server',
                'description' => 'Test connection to an MCP server.',
                'icon' => 'ph:plugs-connected',
            ],
            'discover_mcp_tools' => [
                'class' => DiscoverMcpTools::class,
                'type' => 'write',
                'name' => 'Discover MCP Tools',
                'description' => 'Refresh tool discovery for an MCP server.',
                'icon' => 'ph:plugs-connected',
            ],
            'create_channel' => [
                'class' => CreateChannel::class,
                'type' => 'write',
                'name' => 'Create Channel',
                'description' => 'Create a new channel.',
                'icon' => 'ph:hash',
            ],
            'add_channel_member' => [
                'class' => AddChannelMember::class,
                'type' => 'write',
                'name' => 'Add Channel Member',
                'description' => 'Add a user to a channel.',
                'icon' => 'ph:user-plus',
            ],
            'remove_channel_member' => [
                'class' => RemoveChannelMember::class,
                'type' => 'write',
                'name' => 'Remove Channel Member',
                'description' => 'Remove a user from a channel.',
                'icon' => 'ph:user-minus',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return match ($class) {
            // No-arg tools
            ListAgents::class,
            ListMembers::class,
            GetAgentDetails::class,
            ListIntegrations::class,
            ListAvailableModels::class => new $class,

            // Needs permissionService + tool_registry (ToolRegistry instance via context)
            GetAgentPermissions::class => new GetAgentPermissions($this->permissionService, $context['tool_registry']),

            // Agent + document + avatar services
            CreateAgent::class => new CreateAgent($agent, app(AgentDocumentService::class), app(AgentAvatarService::class)),

            // Agent + document service
            DeleteAgent::class => new DeleteAgent($agent, app(AgentDocumentService::class)),
            ReadAgentIdentityFile::class => new ReadAgentIdentityFile($agent, app(AgentDocumentService::class)),
            UpdateAgentIdentityFile::class => new UpdateAgentIdentityFile($agent, app(AgentDocumentService::class)),

            // Agent + permission service
            UpdateAgentToolPermissions::class,
            UpdateAgentChannelAccess::class,
            UpdateAgentFolderAccess::class,
            UpdateAgentFileFolderAccess::class,
            UpdateAgentIntegrationAccess::class => new $class($agent, $this->permissionService),

            // Agent-only tools
            GetIntegrationConfig::class,
            UpdateAgent::class,
            GetIntegrationSetup::class,
            UpdateIntegrationConfig::class,
            TestIntegrationConnection::class,
            SetupIntegrationWebhook::class,
            LinkExternalUser::class,
            ListMcpServers::class,
            AddMcpServer::class,
            UpdateMcpServer::class,
            RemoveMcpServer::class,
            TestMcpServer::class,
            DiscoverMcpTools::class,
            CreateChannel::class,
            AddChannelMember::class,
            RemoveChannelMember::class => new $class($agent),

            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
