<?php

namespace App\Domain\Vfs\Spec;

/**
 * Declarative contract of one VFS operation (one `vfs_*` tool).
 *
 * Owns the agent-facing surface only: names on every surface, effect and
 * governance action class, the permission that governs the tool toggle, the
 * parameter/result/error contract, examples and notes. It owns no behavior:
 * handlers live in App\Agents\Tools\Vfs and authorization lives in the VFS
 * facade and VfsMutationPolicy. Nothing here grants access.
 *
 * Effect vs action class:
 * - `effect` is the Code Mode receipt/ledger effect (`read`, `write`, or
 *   `per_command` for vfs_exec, whose effect CodeBridge derives per pipeline).
 * - `actionClass` is the governance-model class the future policy engine
 *   gates on (docs/architecture/governance-model.md "Action classes"):
 *   `read`, `reversible_edit`, `delete`, `structural`, `governance`, or
 *   `per_command` / `per_path` when VfsOperationSpecRegistry::actionClassFor()
 *   must resolve it from the call. Today's enforcement is still tool
 *   permissions + approvals; this field is metadata for the engine.
 */
final class VfsOperationSpec
{
    /**
     * @param  'read'|'write'|'per_command'  $effect
     * @param  'read'|'write'  $registryType  Static ToolRegistry type (vfs_exec stays `read`; CodeBridge upgrades mutating pipelines)
     * @param  'read'|'reversible_edit'|'delete'|'structural'|'governance'|'per_command'|'per_path'  $actionClass
     * @param  string  $permission  Permission slug that governs this tool in ToolRegistry (the capabilities-UI toggle)
     * @param  array<string, list<string>>  $composedPermissions  Path scope => every permission the operation composes there
     * @param  list<VfsParameterSpec>  $parameters  Schema order is positional order in Code Mode
     * @param  array<string, string>  $resultFields  Result key => meaning
     * @param  list<string>  $errorCodes  Codes this operation can return (see VfsOperationSpecRegistry::ERROR_CODES)
     * @param  list<string>  $examples  Direct-tool call examples (JSON arguments)
     * @param  list<string>  $rubyExamples  Code Mode examples
     * @param  list<string>  $notes  Semantics that differ from POSIX or other tools
     */
    public function __construct(
        public readonly string $name,
        public readonly string $toolSlug,
        public readonly string $rubyName,
        public readonly string $cliVerb,
        public readonly string $title,
        public readonly string $icon,
        public readonly bool $direct,
        public readonly string $effect,
        public readonly string $registryType,
        public readonly string $actionClass,
        public readonly string $permission,
        public readonly array $composedPermissions,
        public readonly string $summary,
        public readonly string $description,
        public readonly array $parameters,
        public readonly array $resultFields,
        public readonly array $errorCodes,
        public readonly array $examples = [],
        public readonly array $rubyExamples = [],
        public readonly array $notes = [],
        public readonly ?string $canMutateWith = null,
    ) {}

    public function parameter(string $name): ?VfsParameterSpec
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Ruby signature as documented in Code Mode (`read(path:, offset:, ...)`).
     */
    public function rubySignature(): string
    {
        $parts = array_map(
            fn (VfsParameterSpec $parameter): string => $this->snake($parameter->name).':',
            $this->parameters,
        );

        return $this->rubyName.'('.implode(', ', $parts).')';
    }

    /**
     * Whether the governing permission is another tool's slug (vfs_read is
     * governed by vfs_exec, vfs_edit by vfs_patch, script-only writes by
     * vfs_write). Such tools have no capabilities-UI toggle of their own.
     */
    public function borrowsPermission(): bool
    {
        return $this->permission !== $this->toolSlug;
    }

    private function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
