<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
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

        // AI Agents with various statuses and tasks
        $agents = [
            [
                'id' => 'a1',
                'name' => 'Atlas',
                'agent_type' => 'manager',
                'status' => 'working',
                'current_task' => 'Coordinating team workflow and prioritizing tasks',
                'brain' => 'glm:glm-4-plus',
            ],
            [
                'id' => 'a2',
                'name' => 'Echo',
                'agent_type' => 'writer',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm-coding:glm-4.7',
            ],
            [
                'id' => 'a3',
                'name' => 'Nova',
                'agent_type' => 'analyst',
                'status' => 'working',
                'current_task' => 'Analyzing Q4 performance metrics',
                'brain' => 'glm-coding:glm-4.7',
            ],
            [
                'id' => 'a4',
                'name' => 'Pixel',
                'agent_type' => 'creative',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm:glm-4-plus',
            ],
            [
                'id' => 'a5',
                'name' => 'Logic',
                'agent_type' => 'coder',
                'status' => 'working',
                'current_task' => 'Building API endpoints for user management',
                'brain' => 'glm-coding:glm-4.7',
            ],
            [
                'id' => 'a6',
                'name' => 'Scout',
                'agent_type' => 'researcher',
                'status' => 'idle',
                'current_task' => null,
                'brain' => 'glm-coding:glm-4.7',
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
                'manager_id' => $agent['id'] !== 'a1' ? 'a1' : null, // Atlas manages other agents
            ]);
        }
    }
}
