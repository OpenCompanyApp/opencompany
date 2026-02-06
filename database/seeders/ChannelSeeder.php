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
            // Public channels
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
                'name' => 'announcements',
                'type' => 'public',
                'description' => 'Company announcements and updates',
                'members' => ['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'],
            ],
            [
                'id' => 'ch5',
                'name' => 'random',
                'type' => 'public',
                'description' => 'Off-topic discussions and fun stuff',
                'members' => ['h1', 'a1', 'a2', 'a4', 'a6'],
            ],
            // Direct Messages
            [
                'id' => 'dm1',
                'name' => 'Atlas',
                'type' => 'dm',
                'description' => null,
                'members' => ['h1', 'a1'],
            ],
            [
                'id' => 'dm2',
                'name' => 'Nova',
                'type' => 'dm',
                'description' => null,
                'members' => ['h1', 'a3'],
            ],
            [
                'id' => 'dm3',
                'name' => 'Echo',
                'type' => 'dm',
                'description' => null,
                'members' => ['h1', 'a2'],
            ],
            // Private channels
            [
                'id' => 'ch6',
                'name' => 'leadership',
                'type' => 'private',
                'description' => 'Private leadership and strategy discussions',
                'members' => ['h1', 'a1', 'a7'],
            ],
            // External channels - for chatting with agents on the go
            [
                'id' => 'ext1',
                'name' => 'Telegram',
                'type' => 'external',
                'description' => 'Chat with your agents via Telegram',
                'external_provider' => 'telegram',
                'members' => ['h1', 'a1'],
            ],
            [
                'id' => 'ext2',
                'name' => 'Slack',
                'type' => 'external',
                'description' => 'Chat with your agents via Slack',
                'external_provider' => 'slack',
                'members' => ['h1', 'a2', 'a3'],
            ],
        ];

        foreach ($channels as $channelData) {
            $channel = Channel::updateOrCreate(
                ['id' => $channelData['id']],
                [
                    'name' => $channelData['name'],
                    'type' => $channelData['type'],
                    'description' => $channelData['description'] ?? null,
                    'creator_id' => 'h1',
                    'external_provider' => $channelData['external_provider'] ?? null,
                ]
            );

            // Only add members if they don't exist
            foreach ($channelData['members'] as $index => $memberId) {
                ChannelMember::firstOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'user_id' => $memberId,
                    ],
                    [
                        'role' => $index === 0 ? 'admin' : 'member',
                        'unread_count' => rand(0, 5),
                        'joined_at' => now()->subDays(rand(1, 30)),
                    ]
                );
            }
        }
    }
}
