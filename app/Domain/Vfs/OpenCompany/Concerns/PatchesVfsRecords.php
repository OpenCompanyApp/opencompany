<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Structured patch helpers for task/list records and text patch validation.
 */
trait PatchesVfsRecords
{
    private function patchTask(User $agent, array $segments, string $path, string $patch, ?string $version): array
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            throw VfsError::notWritable($path);
        }

        $task = Task::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        if ($task->agent_id !== $agent->id) {
            throw VfsError::permissionDenied($path, 'task is not assigned to this agent');
        }
        $this->assertVersion($version, $this->version($task->updated_at?->toIso8601String(), $task->status), $path);

        $fields = $this->structuredPatchFields($patch, ['title', 'description', 'status', 'priority']);

        if (isset($fields['status'])) {
            $status = (string) $fields['status'];
            $validStatuses = [
                Task::STATUS_PENDING,
                Task::STATUS_ACTIVE,
                Task::STATUS_PAUSED,
                Task::STATUS_COMPLETED,
                Task::STATUS_FAILED,
                Task::STATUS_CANCELLED,
            ];
            if (! in_array($status, $validStatuses, true)) {
                throw VfsError::invalid("Unsupported task status: {$status}");
            }

            $fields['completed_at'] = $status === Task::STATUS_COMPLETED ? now() : null;
        }

        $task->update($fields);

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/tasks/by-id/{$task->id}",
            'version' => $this->version($task->fresh()->updated_at?->toIso8601String(), $task->fresh()->status),
            'fields' => array_keys($fields),
        ];
    }

    private function patchListItem(User $agent, array $segments, string $path, string $patch, ?string $version): array
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            throw VfsError::notWritable($path);
        }

        $item = ListItem::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        if ($item->creator_id !== $agent->id && $item->assignee_id !== $agent->id) {
            throw VfsError::permissionDenied($path, 'list item is not owned by or assigned to this agent');
        }
        $this->assertVersion($version, $this->version($item->updated_at?->toIso8601String(), $item->status ?? ''), $path);

        $fields = $this->structuredPatchFields($patch, ['title', 'description', 'status', 'priority']);

        if (isset($fields['status'])) {
            $status = (string) $fields['status'];
            $newStatus = ListStatus::forWorkspace()->where('slug', $status)->first();
            if ($newStatus) {
                $fields['completed_at'] = $newStatus->is_done ? ($item->completed_at ?? now()) : null;
            }
        }

        $item->update($fields);

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/lists/by-id/{$item->id}",
            'version' => $this->version($item->fresh()->updated_at?->toIso8601String(), $item->fresh()->status ?? ''),
            'fields' => array_keys($fields),
        ];
    }

    private function applyTextPatch(string $current, string $patch): string
    {
        $decoded = json_decode($patch, true);
        if (is_array($decoded) && array_key_exists('search', $decoded) && array_key_exists('replace', $decoded)) {
            $search = (string) $decoded['search'];
            if ($search === '' || ! str_contains($current, $search)) {
                throw VfsError::invalid('Patch search text was not found.');
            }

            return Str::replaceFirst($search, (string) $decoded['replace'], $current);
        }

        if (preg_match('/^<<<<<<< SEARCH\R(?<search>.*?)\R=======\R(?<replace>.*?)\R>>>>>>> REPLACE$/s', trim($patch), $matches) === 1) {
            $search = $matches['search'];
            if (! str_contains($current, $search)) {
                throw VfsError::invalid('Patch search block was not found.');
            }

            return Str::replaceFirst($search, $matches['replace'], $current);
        }

        throw VfsError::invalid('Unsupported patch format. Use JSON {"search":"old","replace":"new"} or SEARCH/REPLACE block.');
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function structuredPatchFields(string $patch, array $allowed): array
    {
        $decoded = json_decode($patch, true);
        if (! is_array($decoded)) {
            throw VfsError::invalid('Task and list patches require JSON object fields.');
        }

        $fields = $decoded['fields'] ?? $decoded;
        if (! is_array($fields) || $fields === []) {
            throw VfsError::invalid('Patch fields are required.');
        }

        $unknown = array_diff(array_keys($fields), $allowed);
        if ($unknown !== []) {
            throw VfsError::invalid('Unsupported patch fields: '.implode(', ', $unknown));
        }

        return array_intersect_key($fields, array_flip($allowed));
    }

    private function assertVersion(?string $given, ?string $expected, string $path): void
    {
        if ($given !== null && $expected !== null && ! hash_equals($expected, $given)) {
            throw VfsError::invalid("Version mismatch for {$path}", [
                'path' => $path,
                'expected' => $expected,
                'given' => $given,
            ]);
        }
    }
}
