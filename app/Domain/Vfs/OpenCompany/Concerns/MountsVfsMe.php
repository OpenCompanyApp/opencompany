<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\ApprovalRequest;
use App\Models\User;

/**
 * `/me`: the acting agent's own working area, identical with or without a
 * computer.
 *
 *   /me/memory/      → /agents/by-id/{self}/memory/   durable memory (existing)
 *   /me/notes/       → /agents/by-id/{self}/notes/    working notes, self-writable
 *   /me/todo.md      → /agents/by-id/{self}/todo.md   current plan; single writer: the agent
 *   /me/identity/    → /agents/by-id/{self}/identity/ (and any other agent subpath)
 *   /me/scratch/     → /files/agents/{home}/scratch/  working files in the agent's file home
 *   /me/results/     → /files/agents/{home}/.results/ offloaded large tool outputs
 *   /me/pending/     read-only: this agent's pending approvals as {id}.md
 *
 * Aliases are resolved by rewriting the path before routing (rewriteMePath),
 * so every alias inherits the backing mount's permission checks, policy and
 * history unchanged; results report the backing canonical paths. Nothing is
 * created on read: notes/, todo.md, scratch/ and results/ appear empty or
 * absent until the first write creates them.
 *
 * Skills are deliberately NOT here: they will be a workspace-level `/skills`
 * mount with progressive disclosure.
 */
trait MountsVfsMe
{
    /**
     * Map a `/me…` path onto its backing path. Virtual paths (`/me`,
     * `/me/pending…`, and empty `/me/scratch` / `/me/results` roots) are
     * returned unchanged for the `me` mount to serve.
     */
    private function rewriteMePath(User $agent, string $path): string
    {
        $segments = $this->segments($path);
        if (($segments[0] ?? null) !== 'me' || count($segments) === 1 || $segments[1] === 'pending') {
            return $path;
        }

        $rest = array_slice($segments, 2);
        if (in_array($segments[1], ['scratch', 'results'], true)) {
            $base = $this->agentFilesHome($agent).'/'.($segments[1] === 'scratch' ? 'scratch' : '.results');
            if ($rest === [] && $this->files->resolveVirtualPath(substr($base, strlen('/files')), (string) $agent->workspace_id) === null) {
                return $path;
            }

            return rtrim($base.'/'.implode('/', $rest), '/');
        }

        return "/agents/by-id/{$agent->id}/".implode('/', array_slice($segments, 1));
    }

    /**
     * The agent's home folder under /files (`/files/agents/{slug}`): the same
     * convention the file-permission home rule uses (MutatesVfsFiles
     * assertCanCreateFilePath) and Stream B's result offloading writes into.
     */
    public function agentFilesHome(User $agent): string
    {
        return '/files/agents/'.$this->slug((string) $agent->name);
    }

    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listMe(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            return $this->meRootEntries($agent);
        }
        if (in_array($segments[1], ['scratch', 'results'], true) && ! isset($segments[2])) {
            return [];
        }
        if ($segments[1] === 'pending' && ! isset($segments[2])) {
            return $this->pendingApprovalEntries($agent, $budget);
        }
        if ($segments[1] === 'pending') {
            throw VfsError::notReadable($path);
        }

        throw VfsError::notFound($path);
    }

    /**
     * @param  list<string>  $segments
     */
    private function readMe(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1 || (count($segments) === 2 && in_array($segments[1], ['scratch', 'results', 'pending'], true))) {
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->listMe($agent, $segments, $path, $budget));
        }

        if ($segments[1] === 'pending' && count($segments) === 3) {
            $approval = $this->pendingApproval($agent, $segments[2]) ?? throw VfsError::notFound($path);

            return VfsText::fit($this->pendingApprovalMarkdown($approval), $budget, $path);
        }

        throw VfsError::notFound($path);
    }

    /**
     * @return list<VfsEntry>
     */
    private function meRootEntries(User $agent): array
    {
        $self = "/agents/by-id/{$agent->id}";
        $home = $this->agentFilesHome($agent);
        $entries = [
            new VfsEntry('memory', '/me/memory', 'directory', "{$self}/memory", 'agent', (string) $agent->id, capabilities: ['browse', 'read', 'search'], metadata: ['description' => 'Durable memory (MEMORY.md, topics, logs).']),
            new VfsEntry('notes', '/me/notes', 'directory', "{$self}/notes", 'agent', (string) $agent->id, capabilities: ['browse', 'read', 'search'], metadata: ['description' => 'Your working notes; only you write here.']),
            new VfsEntry('identity', '/me/identity', 'directory', "{$self}/identity", 'agent', (string) $agent->id, capabilities: ['browse', 'read'], metadata: ['description' => 'Your identity files (prompt-critical).']),
            new VfsEntry('scratch', '/me/scratch', 'directory', "{$home}/scratch", 'workspace_file', null, capabilities: ['browse', 'read'], metadata: ['description' => 'Working files in your /files home.']),
            new VfsEntry('results', '/me/results', 'directory', "{$home}/.results", 'workspace_file', null, capabilities: ['browse', 'read'], metadata: ['description' => 'Large tool outputs offloaded here; read with offset/limit.']),
            new VfsEntry('pending', '/me/pending', 'directory', '/me/pending', 'approval', null, capabilities: ['browse', 'read'], metadata: ['description' => 'Your pending approvals (read-only).']),
        ];
        if ($agent->docs_folder_id && $this->agentAreaDocument($agent, 'todo.md') !== null) {
            $entries[] = new VfsEntry('todo.md', '/me/todo.md', 'file', "{$self}/todo.md", 'document', null, capabilities: ['read', 'patch'], metadata: ['description' => 'Your current plan; only you write it.']);
        } else {
            $entries[] = new VfsEntry('todo.md', '/me/todo.md', 'file', "{$self}/todo.md", 'document', null, capabilities: [], metadata: [
                'description' => 'Your current plan; not created yet. Create it with vfs_write (mode create).',
                'exists' => false,
            ]);
        }

        return $entries;
    }

    /**
     * @return list<VfsEntry>
     */
    private function pendingApprovalEntries(User $agent, VfsBudget $budget): array
    {
        return $this->pendingApprovalQuery($agent)
            ->orderByDesc('created_at')
            ->limit($budget->maxEntries)
            ->get()
            ->map(fn (ApprovalRequest $approval): VfsEntry => new VfsEntry(
                "{$approval->id}.md",
                "/me/pending/{$approval->id}.md",
                'file',
                "/approvals/{$approval->id}.json",
                'approval',
                $approval->id,
                capabilities: ['read'],
                metadata: [
                    'title' => $approval->title,
                    'operation' => $approval->tool_execution_context['vfs_approval']['operation'] ?? ($approval->tool_execution_context['tool_slug'] ?? null),
                    'path' => $this->pendingApprovalSubject($approval),
                    'created_at' => $approval->created_at?->toIso8601String(),
                ],
            ))
            ->all();
    }

    /**
     * Pending approvals requested by this agent in its own workspace.
     *
     * @return \Illuminate\Database\Eloquent\Builder<ApprovalRequest>
     */
    private function pendingApprovalQuery(User $agent): \Illuminate\Database\Eloquent\Builder
    {
        return ApprovalRequest::query()
            ->where('requester_id', $agent->id)
            ->where('workspace_id', $agent->workspace_id)
            ->where('status', 'pending');
    }

    private function pendingApproval(User $agent, string $token): ?ApprovalRequest
    {
        $id = preg_replace('/\.md$/', '', $token) ?? $token;

        return $this->pendingApprovalQuery($agent)->whereKey($id)->first();
    }

    private function pendingApprovalSubject(ApprovalRequest $approval): ?string
    {
        $context = $approval->tool_execution_context ?? [];
        $subject = $context['vfs_approval']['subject'] ?? ($context['parameters']['path'] ?? ($context['parameters']['command'] ?? null));

        return is_string($subject) ? $subject : null;
    }

    /**
     * Agent-readable summary: operation, path, tools and the preview the
     * approver sees. Secrets in the preview are redacted by key name.
     */
    private function pendingApprovalMarkdown(ApprovalRequest $approval): string
    {
        $context = $approval->tool_execution_context ?? [];
        $vfs = is_array($context['vfs_approval'] ?? null) ? $context['vfs_approval'] : [];
        $preview = ApprovalRequest::redactSecretValue($vfs['preview'] ?? null);

        $lines = [
            '# '.$approval->title,
            '',
            "- id: {$approval->id}",
            '- status: '.$approval->status,
            '- operation: '.($vfs['operation'] ?? ($context['tool_slug'] ?? 'unknown')),
            '- path: '.($this->pendingApprovalSubject($approval) ?? '-'),
            '- tools: '.implode(', ', is_array($vfs['approved_tools'] ?? null) ? $vfs['approved_tools'] : [(string) ($context['tool_slug'] ?? '')]),
            '- created_at: '.$approval->created_at?->toIso8601String(),
            '',
            'This change applies automatically when a human approves it. Keep working; do not retry the call.',
        ];
        if ($preview !== null) {
            $lines[] = '';
            $lines[] = '## Preview';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = (string) json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $lines[] = '```';
        }

        return implode("\n", $lines);
    }
}
