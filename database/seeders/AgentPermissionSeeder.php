<?php

namespace Database\Seeders;

use App\Models\AgentPermission;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class AgentPermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Atlas (a1, manager) — All tools allowed, no approval required, unrestricted
        // No permission rows needed (defaults to all allowed)

        // Echo (a2, writer) — All tools, send_channel_message requires approval
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a2',
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a2',
            'scope_type' => 'tool',
            'scope_key' => 'search_documents',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a2',
            'scope_type' => 'tool',
            'scope_key' => 'create_task_step',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        // Nova (a3, analyst) — All tools allowed, unrestricted
        // No permission rows needed

        // Pixel (a4, creative) — Only search_documents + send_channel_message; create_task_step denied
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a4',
            'scope_type' => 'tool',
            'scope_key' => 'search_documents',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a4',
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a4',
            'scope_type' => 'tool',
            'scope_key' => 'create_task_step',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        // Logic (a5, coder) — All tools allowed, unrestricted
        // No permission rows needed

        // Scout (a6, researcher) — Only search_documents; other tools denied
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a6',
            'scope_type' => 'tool',
            'scope_key' => 'search_documents',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a6',
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => 'a6',
            'scope_type' => 'tool',
            'scope_key' => 'create_task_step',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);
    }
}
