<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Code\CodeExec;
use App\Agents\Tools\Code\CodeListDocs;
use App\Agents\Tools\Code\CodeReadDoc;
use App\Agents\Tools\Code\CodeSearchDocs;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\MrubySandboxService;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers the Code Mode discovery, validation, and execution tools.
 *
 * Agents inspect generated docs before running Ruby; the
 * execution tool receives the active ToolRegistry so CodeBridge calls obey the
 * same tool catalog, workspace scope, approvals, and permissions as direct calls.
 */
class CodeToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'code';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list_docs, search_docs, read_doc, exec',
            'description' => 'mruby Code Mode discovery, validation, and execution',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:code';
    }

    public function tools(): array
    {
        return [
            'code_list_docs' => [
                'class' => CodeListDocs::class,
                'type' => 'read',
                'name' => 'List Code Mode Docs',
                'description' => 'List permission-visible app.* namespaces. Use code_read_doc for function details.',
                'icon' => 'ph:list-bullets',
            ],
            'code_search_docs' => [
                'class' => CodeSearchDocs::class,
                'type' => 'read',
                'name' => 'Search Code Mode Docs',
                'description' => 'Search Code Mode functions and guides by intent or keyword.',
                'icon' => 'ph:magnifying-glass',
            ],
            'code_read_doc' => [
                'class' => CodeReadDoc::class,
                'type' => 'read',
                'name' => 'Read Code Mode Doc',
                'description' => 'Read exact parameters, effects, return shapes, and examples for a function or namespace.',
                'icon' => 'ph:book-open-text',
            ],
            'code_exec' => [
                'class' => CodeExec::class,
                'type' => 'write',
                'name' => 'Execute Ruby',
                'description' => 'Validate or execute synchronous Ruby in mruby after discovering APIs with code_read_doc.',
                'icon' => 'ph:play',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Runtime callers can pass the already-built registry to avoid a second
        // catalog construction and to keep Code Mode aligned with the run.
        $toolRegistry = $context['tool_registry'] ?? app(ToolRegistry::class);

        return match ($class) {
            CodeListDocs::class => new CodeListDocs(app(CodeApiDocGenerator::class), $agent),
            CodeSearchDocs::class => new CodeSearchDocs(app(CodeApiDocGenerator::class), $agent),
            CodeReadDoc::class => new CodeReadDoc(app(CodeApiDocGenerator::class), $agent),
            CodeExec::class => new CodeExec(app(MrubySandboxService::class), $toolRegistry, app(CodeApiDocGenerator::class), $agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
