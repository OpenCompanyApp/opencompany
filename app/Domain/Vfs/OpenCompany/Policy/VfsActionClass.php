<?php

namespace App\Domain\Vfs\OpenCompany\Policy;

/**
 * Governance action class of one VFS operation (gating by reversibility).
 *
 * See docs/architecture/governance-model.md "Action classes and default gates".
 * The future policy engine will pick its default gate (allow, ask, propose,
 * two approvers) from this class. Until that engine lands, enforcement stays
 * with today's tool permissions and approvals: this value is metadata carried
 * on every VfsMutationDecision, every VFS approval context and every audit
 * event so the engine can consume it without re-deriving anything.
 *
 * Owns: the mapping from (operation, subject, required tools) to a class.
 * Does not own: gating decisions, presets, proposals or trash. Stream B's
 * operation spec registry (app/Domain/Vfs/Spec) declares the class per tool
 * operation; this classifier refines it per concrete path (an `rm` of a doc
 * is `delete`, a patch of an agent identity file is `governance`). If the two
 * ever disagree, the per-path classification here is the stricter one and
 * must win.
 *
 * Only the VFS-reachable classes exist here. `external_*`, `spend`,
 * `exposure` and `floor` never apply to VFS operations on company data.
 */
final class VfsActionClass
{
    public const READ = 'read';

    public const REVERSIBLE_EDIT = 'reversible_edit';

    public const DELETE = 'delete';

    public const STRUCTURAL = 'structural';

    public const GOVERNANCE = 'governance';

    /** @var list<string> */
    public const ALL = [
        self::READ,
        self::REVERSIBLE_EDIT,
        self::DELETE,
        self::STRUCTURAL,
        self::GOVERNANCE,
    ];

    /**
     * Strictness rank, lowest first. strictest() and classify() agree on
     * this order: governance > structural > delete > reversible_edit > read.
     *
     * @var array<string, int>
     */
    private const RANK = [
        self::READ => 0,
        self::REVERSIBLE_EDIT => 1,
        self::DELETE => 2,
        self::STRUCTURAL => 3,
        self::GOVERNANCE => 4,
    ];

    /** Tools whose effect is a governance change regardless of the VFS verb. */
    private const GOVERNANCE_TOOLS = [
        'update_agent_identity_file',
        'update_agent_tool_permissions',
        'update_agent_folder_access',
        'update_agent_integration_access',
    ];

    /** Shell verbs that move objects to trash (delete means trash). */
    private const DELETE_VERBS = ['rm', 'rmdir', 'unlink', 'shred'];

    /**
     * Classify one operation.
     *
     * Order matters and must stay strictest-first: governance (identity,
     * memory, instructions of an agent) beats structural (schema) beats
     * delete beats reversible_edit. A command line that both writes and
     * deletes is a `delete`.
     *
     * @param  string  $operation  VFS operation (patch, write, exec_write, remove, move, copy, mkdir, touch, truncate, create, read, ...)
     * @param  string  $subject  Normalized VFS path, or the command subject for exec_write
     * @param  list<string>  $requiredTools
     */
    public static function classify(string $operation, string $subject, array $requiredTools = []): string
    {
        if (in_array($operation, ['read', 'list', 'stat', 'search'], true)) {
            return self::READ;
        }

        if (array_intersect($requiredTools, self::GOVERNANCE_TOOLS) !== []
            || self::isAgentGovernancePath($subject)
            || ($operation === 'exec_write' && self::commandTouchesGovernancePath($subject))) {
            return self::GOVERNANCE;
        }

        if (self::isStructuralPath($subject)) {
            return self::STRUCTURAL;
        }

        if (in_array($operation, ['remove', 'delete', 'rm', 'trash'], true)) {
            return self::DELETE;
        }

        if ($operation === 'exec_write' && self::commandDeletes($subject)) {
            return self::DELETE;
        }

        return self::REVERSIBLE_EDIT;
    }

    /**
     * The strictest of several classes (nulls and unknown values ignored).
     *
     * Used wherever a caller declares a class (a command spec, a C1 record
     * operation) while the per-path classifier also has an opinion: a
     * declared class is a floor, never a downgrade, so an `rm` declared
     * `delete` of an identity file stays `governance`. Returns
     * reversible_edit when nothing usable was given, matching classify()'s
     * fallback for writes.
     */
    public static function strictest(?string ...$classes): string
    {
        $best = null;
        foreach ($classes as $class) {
            if ($class === null || ! isset(self::RANK[$class])) {
                continue;
            }
            if ($best === null || self::RANK[$class] > self::RANK[$best]) {
                $best = $class;
            }
        }

        return $best ?? self::REVERSIBLE_EDIT;
    }

    /**
     * Agent identity, instructions and memory files are governance objects:
     * they change who an agent is and what it remembers.
     */
    private static function isAgentGovernancePath(string $subject): bool
    {
        return preg_match('#^/(?:agents/(?:by-id/)?[^/]+|me)/(?:identity|memory)(?:/|$)#', $subject) === 1;
    }

    /** Table schema files describe structure, not records. */
    private static function isStructuralPath(string $subject): bool
    {
        return preg_match('#^/tables/(?:by-id/)?[^/]+/(?:schema\.json|columns(?:/|$)|views(?:/|$))#', $subject) === 1;
    }

    /**
     * True when a shell line names an agent identity or memory path as any
     * word (operand, redirect target, `-o` value). Like commandDeletes this
     * is lexical and errs strict: a mere mention can only raise the class.
     */
    private static function commandTouchesGovernancePath(string $command): bool
    {
        return preg_match('#(?:^|[\s\'"=>])/(?:agents/(?:by-id/)?[^/\s\'"]+|me)/(?:identity|memory)(?:[/\s\'"]|$)#', $command) === 1;
    }

    /**
     * True when any command of a shell line is a deleting verb. Quoted text
     * is not parsed here; a quoted "rm" argument to echo can only make the
     * class stricter, which is the safe direction for a gate.
     */
    private static function commandDeletes(string $command): bool
    {
        foreach (preg_split('/(?:&&|\|\||[;|\n])/', $command) ?: [] as $segment) {
            $verb = strtolower((string) strtok(trim($segment), " \t"));
            if (in_array($verb, self::DELETE_VERBS, true)) {
                return true;
            }
        }

        return false;
    }
}
