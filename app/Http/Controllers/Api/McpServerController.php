<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\Application\ManageMcpServers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workspace MCP server management API.
 *
 * MCP servers are remote tool providers configured by users. This controller
 * owns workspace-scoped CRUD, secret masking, discovery refreshes, and cleanup
 * of OpenCompany permission rows that reference local MCP slugs.
 */
class McpServerController extends Controller
{
    public function __construct(private ManageMcpServers $servers) {}

    /**
     * List all MCP servers.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->servers->list());
    }

    /**
     * Create a new MCP server and auto-discover tools.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|url',
            'auth_type' => 'nullable|string|in:none,bearer,header',
            'auth_config' => 'nullable|array',
            'timeout' => 'nullable|integer|min:5|max:300',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        return response()->json($this->servers->create($request->only([
            'name', 'url', 'auth_type', 'auth_config', 'timeout', 'icon', 'description',
        ])), 201);
    }

    /**
     * Get MCP server details.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->servers->show($id));
    }

    /**
     * Update MCP server configuration.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'url' => 'nullable|string|url',
            'auth_type' => 'nullable|string|in:none,bearer,header',
            'auth_config' => 'nullable|array',
            'timeout' => 'nullable|integer|min:5|max:300',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'enabled' => 'nullable|boolean',
        ]);

        $updates = $request->only([
            'name', 'url', 'auth_type', 'auth_config', 'timeout', 'icon', 'description', 'enabled',
        ]);

        return response()->json($this->servers->update($id, $updates));
    }

    /**
     * Delete an MCP server and clean up permissions.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->servers->delete($id);

        return response()->json(['success' => true]);
    }

    /**
     * Test connection to MCP server.
     */
    public function testConnection(Request $request, string $id): JsonResponse
    {
        $result = $this->servers->testConnection($id, $request->only([
            'url', 'auth_type', 'auth_config', 'timeout',
        ]));

        return response()->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    /**
     * Refresh tool discovery for an MCP server.
     */
    public function discoverTools(string $id): JsonResponse
    {
        try {
            return response()->json($this->servers->refreshTools($id));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
