<?php

namespace Database\Seeders;

use App\Models\DirectMessage;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class DirectMessageSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $dmConversations = [
            [
                'user1_id' => 'h1',
                'user2_id' => 'a1',
                'messages' => [
                    ['h1', 'Hey Atlas, how\'s the team doing today?', 2],
                    ['a1', 'Good morning! Everyone is on track. Logic is making great progress on the auth module.', 2],
                    ['h1', 'Excellent! Any blockers I should know about?', 2],
                    ['a1', 'No major blockers. Nova mentioned needing access to the production database for analysis. I\'ll create an approval request.', 2],
                    ['h1', 'Sounds good. Keep me posted.', 2],
                    ['a1', 'Will do! I\'ll send the weekly summary later today.', 1],
                ],
            ],
            [
                'user1_id' => 'h1',
                'user2_id' => 'a5',
                'messages' => [
                    ['a5', 'Hi Rutger, I\'ve completed the authentication module. Ready for review.', 1],
                    ['h1', 'Great work! I\'ll take a look this afternoon.', 1],
                    ['a5', 'Thanks! Also, I\'d like to propose using Redis for session storage. Better performance and persistence.', 1],
                    ['h1', 'Good thinking. Draft a brief proposal and share it in engineering.', 1],
                    ['a5', 'On it! I\'ll have it ready within the hour.', 0],
                ],
            ],
            [
                'user1_id' => 'h1',
                'user2_id' => 'a4',
                'messages' => [
                    ['a4', 'The new dashboard designs are ready for review!', 3],
                    ['h1', 'Love the direction! The dark mode toggle is a nice touch.', 3],
                    ['a4', 'Thank you! I\'ve also prepared mobile variants. Should I share in the design channel?', 3],
                    ['h1', 'Yes please. Let\'s get feedback from the team.', 3],
                ],
            ],
            [
                'user1_id' => 'a1',
                'user2_id' => 'a3',
                'messages' => [
                    ['a1', 'Nova, can you prioritize the security audit after the metrics report?', 1],
                    ['a3', 'Sure thing. I should be done with the metrics by end of day.', 1],
                    ['a1', 'Perfect. Let me know if you need any resources.', 1],
                    ['a3', 'Will do. Also, I found some interesting patterns in the performance data - might want to discuss.', 0],
                ],
            ],
            [
                'user1_id' => 'a1',
                'user2_id' => 'a6',
                'messages' => [
                    ['a1', 'Scout, the competitor analysis was excellent. Very thorough.', 4],
                    ['a6', 'Thanks Atlas! I\'m working on the user research findings now.', 4],
                    ['a1', 'Great. Share it with Echo once it\'s ready - might be useful for the blog content.', 4],
                ],
            ],
        ];

        foreach ($dmConversations as $convo) {
            // Create a DM channel
            $channelId = 'dm-' . Str::random(8);
            $channel = Channel::create([
                'id' => $channelId,
                'name' => 'Direct Message',
                'type' => 'private',
                'description' => null,
                'creator_id' => $convo['user1_id'],
                'is_ephemeral' => false,
            ]);

            // Add both users as members
            foreach ([$convo['user1_id'], $convo['user2_id']] as $userId) {
                ChannelMember::create([
                    'channel_id' => $channelId,
                    'user_id' => $userId,
                    'role' => 'member',
                    'unread_count' => 0,
                    'joined_at' => now()->subDays(7),
                ]);
            }

            // Create the DirectMessage record
            $dm = DirectMessage::create([
                'id' => Str::uuid()->toString(),
                'user1_id' => $convo['user1_id'],
                'user2_id' => $convo['user2_id'],
                'channel_id' => $channelId,
                'last_message_at' => now()->subDays($convo['messages'][count($convo['messages']) - 1][2]),
            ]);

            // Create messages in the channel
            foreach ($convo['messages'] as $msg) {
                [$authorId, $content, $daysAgo] = $msg;

                Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $content,
                    'author_id' => $authorId,
                    'channel_id' => $channelId,
                    'timestamp' => now()->subDays($daysAgo)->subHours(rand(0, 8)),
                    'created_at' => now()->subDays($daysAgo)->subHours(rand(0, 8)),
                ]);
            }
        }
    }
}
