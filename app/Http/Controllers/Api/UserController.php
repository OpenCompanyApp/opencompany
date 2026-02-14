<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * @return Collection<int, User>
     */
    public function index(): Collection
    {
        return User::orderBy('name')->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function agents(): Collection
    {
        return User::where('type', 'agent')->orderBy('name')->get();
    }

    public function show(string $id): JsonResponse|User
    {
        // Check for mock user IDs first
        $mockUsers = $this->getMockUsers();
        if (isset($mockUsers[$id])) {
            return response()->json($mockUsers[$id]);
        }

        return User::findOrFail($id);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getMockUsers(): array
    {
        return [
            'h1' => [
                'id' => 'h1',
                'name' => 'Rutger',
                'email' => 'rutger@gingermedia.biz',
                'avatar' => null,
                'type' => 'human',
                'status' => 'online',
            ],
            'a1' => [
                'id' => 'a1',
                'name' => 'Atlas',
                'avatar' => null,
                'type' => 'agent',
                'agentType' => 'manager',
                'status' => 'working',
                'currentTask' => 'Coordinating team tasks',
            ],
            'a2' => [
                'id' => 'a2',
                'name' => 'Echo',
                'avatar' => null,
                'type' => 'agent',
                'agentType' => 'writer',
                'status' => 'idle',
            ],
            'a3' => [
                'id' => 'a3',
                'name' => 'Nova',
                'avatar' => null,
                'type' => 'agent',
                'agentType' => 'analyst',
                'status' => 'working',
                'currentTask' => 'Analyzing metrics',
            ],
            'a4' => [
                'id' => 'a4',
                'name' => 'Pixel',
                'avatar' => null,
                'type' => 'agent',
                'agentType' => 'creative',
                'status' => 'idle',
            ],
            'a5' => [
                'id' => 'a5',
                'name' => 'Logic',
                'avatar' => null,
                'type' => 'agent',
                'agentType' => 'coder',
                'status' => 'working',
                'currentTask' => 'Implementing authentication',
            ],
            'a6' => [
                'id' => 'a6',
                'name' => 'Scout',
                'avatar' => null,
                'type' => 'agent',
                'agentType' => 'researcher',
                'status' => 'idle',
            ],
        ];
    }

    public function update(Request $request, string $id): User
    {
        $user = User::findOrFail($id);
        $oldName = $user->name;

        $user->update($request->only([
            'name',
            'email',
            'avatar',
            'status',
            'agent_type',
            'system_prompt',
        ]));

        if ($user->isHuman() && $request->has('name') && $request->name !== $oldName) {
            app(\App\Services\HumanAvatarService::class)->generate($user);
        }

        return $user;
    }

    public function updatePresence(Request $request, string $id): User
    {
        $user = User::findOrFail($id);
        $user->update([
            'presence' => $request->input('presence'),
            'last_active_at' => now(),
        ]);

        return $user;
    }

    public function activity(string $id): JsonResponse
    {
        // Return mock activity data for demo purposes
        return response()->json([
            'steps' => [
                [
                    'id' => '1',
                    'description' => 'Started working on task',
                    'status' => 'completed',
                    'startedAt' => now()->subHours(2)->toISOString(),
                    'completedAt' => now()->subHours(1)->toISOString(),
                ],
                [
                    'id' => '2',
                    'description' => 'Reviewing code changes',
                    'status' => 'in_progress',
                    'startedAt' => now()->subMinutes(30)->toISOString(),
                ],
            ],
            'activities' => [],
            'tasks' => [
                [
                    'id' => 't1',
                    'title' => 'Implement authentication',
                    'description' => 'Add JWT-based auth',
                    'status' => 'done',
                ],
                [
                    'id' => 't2',
                    'title' => 'Fix database queries',
                    'description' => 'Optimize slow queries',
                    'status' => 'in_progress',
                ],
            ],
            'stats' => [
                'completedTasks' => 12,
                'inProgressTasks' => 2,
                'totalTasks' => 14,
            ],
        ]);
    }
}
