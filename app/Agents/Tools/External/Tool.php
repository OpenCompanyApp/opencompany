<?php

namespace App\Agents\Tools\External;

use App\Agents\Tools\External\ToolResult;

/**
 * Framework-agnostic tool contract.
 *
 * Tools implement this interface to be callable from any host application
 * (OpenCompany web, KosmoKrator CLI, or any future PHP consumer).
 * The host's Ruby script bridge or tool executor calls execute() directly.
 */
interface Tool
{
    /**
     * Tool slug used for routing (e.g., 'render_mermaid', 'send_message').
     */
    public function name(): string;

    /**
     * Human-readable description. Shown in Ruby script docs and tool catalogs.
     * Can be multi-line with usage tips and examples.
     */
    public function description(): string;

    /**
     * Parameter definitions as a keyed array.
     *
     * Each key is the parameter name (snake_case), each value is an array with:
     *   'type'        => string   'string' | 'integer' | 'number' | 'boolean' | 'array' | 'object'
     *   'required'    => bool     (default false)
     *   'description' => string   (optional)
     *   'enum'        => array    (optional, for string enums)
     *   'items'       => array    (optional, for array element type, e.g. ['type' => 'string'])
     *   'properties'  => array    (optional, for object sub-properties)
     *   'default'     => mixed    (optional, default value)
     *
     * Example:
     *   return [
     *       'syntax' => ['type' => 'string', 'required' => true, 'description' => 'Diagram code'],
     *       'theme'  => ['type' => 'string', 'enum' => ['default', 'dark'], 'description' => 'Color theme'],
     *       'width'  => ['type' => 'integer', 'description' => 'Width in pixels'],
     *   ];
     *
     * @return array<string, array<string, mixed>>
     */
    public function parameters(): array;

    /**
     * Execute the tool with the given arguments.
     *
     * @param  array<string, mixed>  $args  Keyed arguments matching parameter definitions
     */
    public function execute(array $args): ToolResult;
}
