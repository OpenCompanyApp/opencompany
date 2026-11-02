<?php

namespace App\Jobs;

use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\McpServer;
use App\Models\Workspace;
use App\Services\Mcp\McpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-discovers the tool lists of enabled MCP servers whose discovery is stale.
 *
 * Workspace: each server is refreshed with its own workspace bound via
 * withWorkspaceBinding(); the caller's binding is restored afterwards.
 */
class RefreshMcpToolsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetsWorkspaceContext;

    public function handle(): void
    {
        $servers = McpServer::where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('tools_discovered_at')
                    ->orWhere('tools_discovered_at', '<', now()->subHour());
            })
            ->get();

        foreach ($servers as $server) {
            try {
                // Each server's workspace is bound only while that server is
                // refreshed, so one tenant never leaks into the next server
                // or into the caller.
                $workspace = $server->workspace_id ? Workspace::find($server->workspace_id) : null;

                $this->withWorkspaceBinding($workspace, function () use ($server): void {
                    $client = McpClient::fromServer($server);
                    $tools = $client->listTools();

                    $server->update([
                        'discovered_tools' => $tools,
                        'tools_discovered_at' => now(),
                    ]);
                });
            } catch (\Throwable $e) {
                Log::warning("MCP tool refresh failed for {$server->name}: {$e->getMessage()}");
            }
        }
    }
}
