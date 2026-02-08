<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class McpServer extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'url',
        'auth_type',
        'auth_config',
        'headers',
        'enabled',
        'discovered_tools',
        'server_info',
        'tools_discovered_at',
        'timeout',
        'icon',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'auth_config' => 'encrypted:array',
            'headers' => 'array',
            'enabled' => 'boolean',
            'discovered_tools' => 'array',
            'server_info' => 'array',
            'tools_discovered_at' => 'datetime',
        ];
    }

    /**
     * Get prefixed tool slugs from cached discovered tools.
     */
    public function getToolSlugs(): array
    {
        $slugs = [];
        foreach ($this->discovered_tools ?? [] as $tool) {
            $slugs[] = 'mcp_' . $this->slug . '__' . Str::snake($tool['name']);
        }

        return $slugs;
    }

    /**
     * Check if tool discovery cache is stale (older than 1 hour).
     */
    public function isToolDiscoveryStale(): bool
    {
        if (!$this->tools_discovered_at) {
            return true;
        }

        return $this->tools_discovered_at->lt(now()->subHour());
    }

    /**
     * Build HTTP auth headers from auth_type + auth_config.
     */
    public function getAuthHeaders(): array
    {
        $config = $this->auth_config ?? [];

        return match ($this->auth_type) {
            'bearer' => ['Authorization' => 'Bearer ' . ($config['token'] ?? '')],
            'header' => [($config['header_name'] ?? 'Authorization') => ($config['header_value'] ?? '')],
            default => [],
        };
    }

    /**
     * Get masked auth value for API responses.
     */
    public function getMaskedAuthValue(): ?string
    {
        $config = $this->auth_config ?? [];
        $value = match ($this->auth_type) {
            'bearer' => $config['token'] ?? null,
            'header' => $config['header_value'] ?? null,
            default => null,
        };

        if (!$value || strlen($value) < 8) {
            return $value ? '****' : null;
        }

        return substr($value, 0, 4) . '***' . substr($value, -4);
    }
}
