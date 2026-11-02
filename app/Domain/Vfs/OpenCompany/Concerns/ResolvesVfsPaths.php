<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsPath;

/**
 * Path normalization and root mount discovery for OpenCompanyVfs.
 */
trait ResolvesVfsPaths
{
    /**
     * Normalize a virtual path. Delegates to VfsPath so the approval replay
     * path (ApprovalExecutionService) normalizes subjects identically.
     */
    public function normalizePath(string $path, string $cwd = '/'): string
    {
        return VfsPath::normalize($path, $cwd);
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), fn (string $part): bool => $part !== ''));
    }

    /**
     * One line per root mount: what lives there and how it is addressed.
     * Also the source of the mount map in /README.md, so the two never drift.
     *
     * @var array<string, string>
     */
    private const ROOT_MOUNTS = [
        'me' => 'Your own area: memory/, notes/, todo.md, scratch/, results/, pending/ approvals.',
        'agents' => 'Agents: identity, memory, notes, todo.md and tasks (relationship-gated).',
        'docs' => 'Documents and folders by title; read, write, edit, rm (trash), mv, mkdir.',
        'files' => 'Workspace files and folders; full read/write file semantics.',
        'tables' => 'Data tables: schema.json, rows.ndjson, rows.csv, rows/by-id/{id}.json.',
        'lists' => 'List items as markdown with front matter, grouped by project/status/assignee.',
        'tasks' => 'Agent task (run) records: open, completed, by-status, by-agent.',
        'channels' => 'Channel transcripts by day (read-only).',
        'tools' => 'Your tool catalog and the VFS reference (vfs.md).',
        'automations' => 'Automations (read-only).',
        'approvals' => 'Your approval requests (read-only).',
        'workspace' => 'Workspace members and settings (read-only).',
    ];

    /**
     * @return list<VfsEntry>
     */
    private function rootEntries(): array
    {
        $entries = [new VfsEntry('README.md', '/README.md', 'file', '/README.md', capabilities: ['read'], metadata: [
            'description' => 'Start here: mount map, path rules, edit workflow, approvals.',
        ])];
        foreach (self::ROOT_MOUNTS as $name => $description) {
            $entries[] = new VfsEntry($name, "/{$name}", 'directory', capabilities: ['browse', 'read', 'search'], metadata: ['description' => $description]);
        }

        return $entries;
    }

    /**
     * The root orientation file. Byte-stable: no per-agent or per-run data,
     * so it caches like the system prompt.
     */
    private function rootReadme(): string
    {
        $mounts = implode("\n", array_map(
            fn (string $name, string $description): string => sprintf('- /%-12s %s', $name, $description),
            array_keys(self::ROOT_MOUNTS),
            self::ROOT_MOUNTS,
        ));

        return <<<MD
# OpenCompany VFS

Company data as files. Every read and write goes through the same permissions,
approvals and history as the app.

## Mounts

{$mounts}

## Paths

- Friendly names are slugs of titles (`/docs/reports/weekly-report`); `.md` is accepted on documents.
- Two siblings with the same slug are listed as `name--1a2b3c4d` (last 8 characters of the id);
  the bare name then fails with `ambiguous_match` listing both.
- Every object has a stable canonical path: `/docs/by-id/{id}`, `/files/by-id/{id}`,
  `/tables/by-id/{id}/rows/by-id/{row}.json`, `/lists/by-id/{id}`, `/agents/by-id/{id}`.
  Reads and writes return it as `canonical_path`; prefer it when you come back later.
- `not_found` errors carry `did_you_mean` suggestions.

## Edit workflow

1. Read the object (`vfs_read`); keep its `version`.
2. Change it: `vfs_edit` / `vfs_patch` for targeted edits (text search/replace;
   table rows `{"data":{…},"base":{…}}`; list items and tasks by field), or `vfs_write`
   mode `overwrite` with that `version` to replace it whole.
3. Verify by reading it again.

A stale `version` fails with `conflict` (someone else changed it): re-read and decide again.

## Creating, deleting, moving

- Documents: `vfs_write /docs/<folder>/<Title>.md` (mode create); `mkdir` for folders.
- Table rows: `vfs_write /tables/<table>/rows/new.json` with `{"data":{"Column":value}}`.
- List items: `vfs_write /lists/projects/<project>/new.md` with front matter (title, status, …).
- `rm` moves documents, files, rows and list items to the trash: `<mount>/.trash/`
  (`/docs/.trash/`, `/files/.trash/`, `/lists/.trash/`, `/tables/<table>/rows/.trash/`).
  Restore with `mv <trash entry> <destination>`. Nothing is deleted permanently through the VFS.
- `mv` renames or moves documents and files.

## History

Append `/.history/` to a document, file or table row path to list its versions,
`v{n}--{author}--{time}`, newest first. `v{n}` is the content that change n replaced.
To restore one, read it and write that content back with the object's current `version`.

## Approvals

Some writes need a human's approval. You then get `approval_required` /
`pending_approval` with an `approval_id`: that is not a failure. Keep working; do not retry.
The change applies when approved and the outcome is posted back to you.
Your pending requests are listed in `/me/pending/`.

## /me

`/me` is your own area: `memory/`, `notes/` and `todo.md` (only you write them),
`scratch/` for working files, `results/` for large offloaded outputs, `pending/` approvals.
Folders appear when you first write into them.

## More

- `help` lists shell commands; `help <command>` explains one.
- `/tools/vfs.md` is the full reference.
- Optional `idempotency_key` on writes makes a retry after a timeout safe.
MD;
    }
}
