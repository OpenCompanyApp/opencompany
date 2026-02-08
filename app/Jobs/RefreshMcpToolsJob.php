<?php

namespace App\Jobs;

use App\Models\McpServer;
use App\Services\Mcp\McpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshMcpToolsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                $client = McpClient::fromServer($server);
                $tools = $client->listTools();

                $server->update([
                    'discovered_tools' => $tools,
                    'tools_discovered_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("MCP tool refresh failed for {$server->name}: {$e->getMessage()}");
            }
        }
    }
}
