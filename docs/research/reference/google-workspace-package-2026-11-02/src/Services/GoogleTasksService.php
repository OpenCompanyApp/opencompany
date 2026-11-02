<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleTasksService
{
    private const BASE_URL = 'https://tasks.googleapis.com/tasks/v1';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * List all task lists.
     *
     * @return array<string, mixed>
     */
    public function listTaskLists(?int $maxResults = null, ?string $pageToken = null): array
    {
        $params = [];

        if ($maxResults !== null) {
            $params['maxResults'] = (string) min($maxResults, 100);
        }

        if ($pageToken !== null) {
            $params['pageToken'] = $pageToken;
        }

        return $this->client->get(self::BASE_URL . '/users/@me/lists', $params);
    }

    /**
     * Create a new task list.
     *
     * @return array<string, mixed>
     */
    public function createTaskList(string $title): array
    {
        return $this->client->post(self::BASE_URL . '/users/@me/lists', [
            'title' => $title,
        ]);
    }

    /**
     * Delete a task list.
     */
    public function deleteTaskList(string $tasklistId): void
    {
        $this->client->delete(self::BASE_URL . '/users/@me/lists/' . $tasklistId);
    }

    /**
     * List tasks in a task list.
     *
     * @param  array<string, mixed>  $params  Optional filters (showCompleted, showHidden, dueMin, dueMax, maxResults, pageToken)
     * @return array<string, mixed>
     */
    public function listTasks(string $tasklistId, array $params = []): array
    {
        $query = [];

        if (isset($params['showCompleted'])) {
            $query['showCompleted'] = $params['showCompleted'] ? 'true' : 'false';
        }

        if (isset($params['showHidden'])) {
            $query['showHidden'] = $params['showHidden'] ? 'true' : 'false';
        }

        if (isset($params['dueMin'])) {
            $query['dueMin'] = $params['dueMin'] . 'T00:00:00.000Z';
        }

        if (isset($params['dueMax'])) {
            $query['dueMax'] = $params['dueMax'] . 'T23:59:59.000Z';
        }

        if (isset($params['maxResults'])) {
            $query['maxResults'] = (string) min((int) $params['maxResults'], 100);
        }

        if (isset($params['pageToken'])) {
            $query['pageToken'] = $params['pageToken'];
        }

        return $this->client->get(self::BASE_URL . '/lists/' . $tasklistId . '/tasks', $query);
    }

    /**
     * Get a single task.
     *
     * @return array<string, mixed>
     */
    public function getTask(string $tasklistId, string $taskId): array
    {
        return $this->client->get(self::BASE_URL . '/lists/' . $tasklistId . '/tasks/' . $taskId);
    }

    /**
     * Create a new task.
     *
     * @param  array<string, mixed>  $data  Task fields (title, notes, due, status)
     * @return array<string, mixed>
     */
    public function createTask(string $tasklistId, array $data, ?string $parent = null): array
    {
        $query = [];
        if ($parent !== null) {
            $query['parent'] = $parent;
        }

        $url = self::BASE_URL . '/lists/' . $tasklistId . '/tasks';
        if (! empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->client->post($url, $data);
    }

    /**
     * Update a task.
     *
     * @param  array<string, mixed>  $data  Fields to update
     * @return array<string, mixed>
     */
    public function updateTask(string $tasklistId, string $taskId, array $data): array
    {
        return $this->client->patch(
            self::BASE_URL . '/lists/' . $tasklistId . '/tasks/' . $taskId,
            $data
        );
    }

    /**
     * Delete a task.
     */
    public function deleteTask(string $tasklistId, string $taskId): void
    {
        $this->client->delete(self::BASE_URL . '/lists/' . $tasklistId . '/tasks/' . $taskId);
    }

    /**
     * Move a task (reorder or reparent).
     *
     * @return array<string, mixed>
     */
    public function moveTask(string $tasklistId, string $taskId, ?string $parent = null, ?string $previous = null): array
    {
        $query = [];
        if ($parent !== null) {
            $query['parent'] = $parent;
        }
        if ($previous !== null) {
            $query['previous'] = $previous;
        }

        $url = self::BASE_URL . '/lists/' . $tasklistId . '/tasks/' . $taskId . '/move';
        if (! empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->client->post($url);
    }

    /**
     * Clear all completed tasks from a list.
     */
    public function clearCompleted(string $tasklistId): void
    {
        $this->client->post(self::BASE_URL . '/lists/' . $tasklistId . '/clear');
    }

    /**
     * Format a task for display.
     *
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public static function formatTask(array $task): array
    {
        $formatted = [
            'id' => $task['id'] ?? '',
            'title' => $task['title'] ?? '',
            'status' => $task['status'] ?? '',
        ];

        if (! empty($task['notes'])) {
            $formatted['notes'] = $task['notes'];
        }

        if (! empty($task['due'])) {
            $formatted['due'] = substr((string) $task['due'], 0, 10);
        }

        if (($task['status'] ?? '') === 'completed' && ! empty($task['completed'])) {
            $formatted['completedAt'] = substr((string) $task['completed'], 0, 10);
        }

        if (! empty($task['parent'])) {
            $formatted['parent'] = $task['parent'];
        }

        if (! empty($task['links'])) {
            $formatted['links'] = $task['links'];
        }

        if (! empty($task['webViewLink'])) {
            $formatted['webLink'] = $task['webViewLink'];
        }

        return $formatted;
    }
}
