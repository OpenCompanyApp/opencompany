<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\ChannelMember;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ChannelSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $channels = [
            [
                'id' => 'ch1',
                'name' => 'general',
                'type' => 'public',
                'description' => 'General discussion and announcements',
                'members' => ['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'],
            ],
            [
                'id' => 'ch2',
                'name' => 'engineering',
                'type' => 'public',
                'description' => 'Technical discussions and code reviews',
                'members' => ['h1', 'a1', 'a3', 'a5'],
            ],
            [
                'id' => 'ch3',
                'name' => 'design',
                'type' => 'public',
                'description' => 'Design discussions and creative work',
                'members' => ['h1', 'a2', 'a4'],
            ],
            [
                'id' => 'ch4',
                'name' => 'agent-ops',
                'type' => 'agent',
                'description' => 'Agent coordination and status updates',
                'members' => ['a1', 'a2', 'a3', 'a4', 'a5', 'a6'],
            ],
        ];

        foreach ($channels as $channelData) {
            $channel = Channel::create([
                'id' => $channelData['id'],
                'name' => $channelData['name'],
                'type' => $channelData['type'],
                'description' => $channelData['description'],
                'creator_id' => 'h1',
            ]);

            foreach ($channelData['members'] as $index => $memberId) {
                ChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $memberId,
                    'role' => $index === 0 ? 'admin' : 'member',
                    'unread_count' => rand(0, 5),
                    'joined_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }
}
