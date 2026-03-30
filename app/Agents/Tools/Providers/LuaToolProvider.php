<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Lua\LuaExec;
use App\Agents\Tools\Lua\LuaListDocs;
use App\Agents\Tools\Lua\LuaReadDoc;
use App\Agents\Tools\Lua\LuaSearchDocs;
use App\Models\User;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaSandboxService;

class LuaToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'lua';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list_docs, search_docs, read_doc, exec',
            'description' => 'Lua scripting API reference and code execution',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:code';
    }

    public function tools(): array
    {
        return [
            'lua_list_docs' => [
                'class' => LuaListDocs::class,
                'type' => 'read',
                'name' => 'List Lua API Docs',
                'description' => 'List available Lua scripting API namespaces and functions.',
                'icon' => 'ph:list-bullets',
            ],
            'lua_search_docs' => [
                'class' => LuaSearchDocs::class,
                'type' => 'read',
                'name' => 'Search Lua API Docs',
                'description' => 'Search the Lua scripting API documentation by keyword.',
                'icon' => 'ph:magnifying-glass',
            ],
            'lua_read_doc' => [
                'class' => LuaReadDoc::class,
                'type' => 'read',
                'name' => 'Read Lua API Doc',
                'description' => 'Read detailed Lua API documentation for a namespace, function, or guide.',
                'icon' => 'ph:book-open-text',
            ],
            'lua_exec' => [
                'class' => LuaExec::class,
                'type' => 'write',
                'name' => 'Execute Lua Code',
                'description' => 'Execute Lua code in a sandboxed environment and return the output.',
                'icon' => 'ph:play',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return match ($class) {
            LuaListDocs::class => new LuaListDocs(app(LuaApiDocGenerator::class), $agent),
            LuaSearchDocs::class => new LuaSearchDocs(app(LuaApiDocGenerator::class), $agent),
            LuaReadDoc::class => new LuaReadDoc(app(LuaApiDocGenerator::class), $agent),
            LuaExec::class => new LuaExec(app(LuaSandboxService::class), $context['tool_registry'], app(LuaApiDocGenerator::class), $agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
