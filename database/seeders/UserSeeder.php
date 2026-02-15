<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AgentAvatarService;
use App\Services\HumanAvatarService;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();

        // Main human user
        User::create([
            'id' => 'h1',
            'name' => 'Rutger',
            'email' => 'rutger@gingermedia.biz',
            'password' => Hash::make('cdcdcd10'),
            'type' => 'human',
            'status' => 'offline',
            'presence' => 'online',
            'email_verified_at' => now(),
        ]);

        // Set workspace owner and create membership
        $workspace->update(['owner_id' => 'h1']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => 'h1',
            'role' => 'admin',
        ]);

        // System user for automations
        User::create([
            'id' => 'system',
            'name' => 'Automation',
            'type' => 'agent',
            'agent_type' => 'system',
            'status' => 'idle',
            'presence' => 'online',
            'workspace_id' => $workspace->id,
        ]);

        // AI Agents with various statuses and tasks
        $agents = [
            [
                'id' => 'a1',
                'name' => 'Atlas',
                'agent_type' => 'manager',
                'status' => 'working',
                'current_task' => 'Coordinating team workflow and prioritizing tasks',
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'autonomous',
                'must_wait_for_approval' => false,
            ],
            [
                'id' => 'a2',
                'name' => 'Echo',
                'agent_type' => 'writer',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'supervised',
                'must_wait_for_approval' => true,
            ],
            [
                'id' => 'a3',
                'name' => 'Nova',
                'agent_type' => 'analyst',
                'status' => 'working',
                'current_task' => 'Analyzing Q4 performance metrics',
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'autonomous',
                'must_wait_for_approval' => false,
            ],
            [
                'id' => 'a4',
                'name' => 'Pixel',
                'agent_type' => 'creative',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'supervised',
                'must_wait_for_approval' => false,
            ],
            [
                'id' => 'a5',
                'name' => 'Logic',
                'agent_type' => 'coder',
                'status' => 'working',
                'current_task' => 'Building API endpoints for user management',
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'autonomous',
                'must_wait_for_approval' => false,
            ],
            [
                'id' => 'a6',
                'name' => 'Scout',
                'agent_type' => 'researcher',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'strict',
                'must_wait_for_approval' => true,
            ],
            [
                'id' => 'a7',
                'name' => 'Nexus',
                'agent_type' => 'coordinator',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm-coding:glm-4.7',
                'behavior_mode' => 'autonomous',
                'must_wait_for_approval' => false,
            ],
        ];

        foreach ($agents as $agent) {
            User::create([
                'id' => $agent['id'],
                'name' => $agent['name'],
                'type' => 'agent',
                'agent_type' => $agent['agent_type'],
                'status' => $agent['status'],
                'presence' => $agent['status'] === 'working' ? 'online' : 'offline',
                'current_task' => $agent['current_task'],
                'brain' => $agent['brain'],
                'behavior_mode' => $agent['behavior_mode'] ?? null,
                'must_wait_for_approval' => $agent['must_wait_for_approval'] ?? false,
                'manager_id' => $agent['id'] !== 'a1' ? 'a1' : 'h1', // Atlas reports to Rutger, other agents report to Atlas
                'workspace_id' => $workspace->id,
            ]);
        }

        // Generate procedural avatars for all users
        app(AgentAvatarService::class)->generateAll();
        app(HumanAvatarService::class)->generateAll();
    }
}
