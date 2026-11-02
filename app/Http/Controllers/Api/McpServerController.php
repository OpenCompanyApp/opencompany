<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\Application\ManageMcpServers;
use App\Http\Controllers\Controller;
use App\Services\Mcp\McpUrlPolicy;
use Closure;
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
    public function __construct(
        private ManageMcpServers $servers,
        private McpUrlPolicy $urlPolicy,
    ) {}

    /**
     * Validation rule enforcing the MCP SSRF policy on user-supplied URLs.
     *
     * This gives an immediate 422 instead of saving an unreachable/blocked
     * server. McpClient re-checks before every request, so this is the UX
     * layer, not the only enforcement point.
     */
    private function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $violation = $this->urlPolicy->violation($value);
            if ($violation !== null) {
                $fail($violation);
            }
        };
    }

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
            'url' => ['required', 'string', 'url', $this->safeUrlRule()],
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
            'url' => ['nullable', 'string', 'url', $this->safeUrlRule()],
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
        $request->validate([
            'url' => ['nullable', 'string', 'url', $this->safeUrlRule()],
        ]);

        $result = $this->servers->testConnection($id, $request->only([
            'url', 'auth_type', 'auth_config', 'timeout',
        ]));

        return response()->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    /**
     * Test an unsaved MCP server definition before creating it.
     */
    public function testNewConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url', $this->safeUrlRule()],
            'auth_type' => 'nullable|string|in:none,bearer,header',
            'auth_config' => 'nullable|array',
            'timeout' => 'nullable|integer|min:5|max:300',
        ]);

        $result = $this->servers->testUnsavedConnection($validated);

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
