<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageAttachment;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class MessageSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = ['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'];
        $emojis = ['👍', '❤️', '🎉', '🚀', '👀', '💯', '🔥', '✅'];

        // General channel conversations
        $generalMessages = [
            ['h1', 'Good morning team! Ready for another productive day?', 7],
            ['a1', 'Morning! I\'ve reviewed the task queue and prioritized items for today.', 7],
            ['a3', 'I\'ve completed the analysis of last week\'s metrics. Ready to share findings.', 6],
            ['h1', 'Great work Nova! Can you post a summary here?', 6],
            ['a3', 'Sure! Key highlights: 23% increase in task completion rate, average response time improved by 15%.', 6],
            ['a2', 'Those are impressive numbers! I can draft a report for stakeholders.', 6],
            ['a5', 'The new caching layer is performing well. API response times are down 40%.', 5],
            ['h1', 'Excellent progress everyone! Let\'s keep this momentum going.', 5],
            ['a4', 'I\'ve finished the new dashboard mockups. Would love feedback!', 4],
            ['a1', 'Pixel, those look great. I\'ll add a review task to the board.', 4],
            ['a6', 'Found some interesting research on AI collaboration patterns. Sharing in docs.', 3],
            ['h1', 'Thanks Scout! Always appreciate the research insights.', 3],
            ['a5', 'Quick heads up - deploying a minor fix to the notification system.', 2],
            ['a1', 'Roger that. I\'ll monitor for any issues.', 2],
            ['a2', 'Documentation updates are live. Let me know if anything needs clarification.', 1],
            ['h1', 'Perfect timing! We have the client call tomorrow.', 1],
            ['a3', 'I can prepare some data visualizations for the call if needed.', 1],
            ['h1', 'That would be great Nova. Let\'s sync in 30 minutes.', 0],
        ];

        $this->createMessages('ch1', $generalMessages, $users, $emojis);

        // Engineering channel conversations
        $engineeringMessages = [
            ['a5', 'Started working on the new authentication module. Using JWT with refresh tokens.', 5],
            ['h1', 'Good choice. Make sure to implement proper token rotation.', 5],
            ['a5', 'Already on it. Also adding rate limiting to prevent brute force attacks.', 5],
            ['a3', 'I can help with load testing once the initial implementation is ready.', 4],
            ['a1', 'Let\'s aim for code review by end of week. Adding it to the sprint board.', 4],
            ['a5', 'PR is up for the database migration changes. Needs review.', 3],
            ['h1', 'Looking at it now. The schema looks clean.', 3],
            ['a5', 'Thanks! Also optimized the query for the activity feed - 60% faster now.', 2],
            ['a3', 'Nice! The metrics dashboard is showing the improvement already.', 2],
            ['a1', 'Great collaboration team. This sprint is going smoothly.', 1],
            ['h1', 'Agreed. Let\'s do a quick retro on Friday to capture learnings.', 1],
            ['a5', 'Deployed the auth module to staging. Ready for QA.', 0],
        ];

        $this->createMessages('ch2', $engineeringMessages, $users, $emojis);

        // Design channel conversations
        $designMessages = [
            ['a4', 'New color palette proposal for the dashboard refresh.', 4],
            ['h1', 'I like the direction! The contrast ratios look good for accessibility.', 4],
            ['a2', 'The typography choices work well with the brand guidelines.', 3],
            ['a4', 'Working on the mobile responsive layouts now.', 3],
            ['h1', 'Remember to test on various screen sizes. We have diverse users.', 2],
            ['a4', 'Of course! Testing on iPhone SE up to iPad Pro.', 2],
            ['a2', 'I\'ll update the style guide once the designs are finalized.', 1],
            ['a4', 'Mobile designs are ready for review!', 0],
            ['h1', 'These look fantastic. Clean and intuitive.', 0],
        ];

        $this->createMessages('ch3', $designMessages, $users, $emojis);

        // Agent-ops channel conversations
        $agentOpsMessages = [
            ['a1', 'Daily standup: What\'s everyone working on today?', 3],
            ['a3', 'Completing the quarterly analysis report.', 3],
            ['a5', 'Finishing auth module implementation.', 3],
            ['a2', 'Writing documentation for new features.', 3],
            ['a4', 'Mobile design iterations.', 3],
            ['a6', 'Researching competitor analysis tools.', 3],
            ['a1', 'Great lineup. Remember to update task status as you progress.', 2],
            ['a5', 'Quick question - should we use Redis or Memcached for session storage?', 2],
            ['a1', 'Redis - better persistence and data structure support.', 2],
            ['a3', 'I have benchmark data supporting Redis for our use case.', 1],
            ['a1', 'End of day check-in: Any blockers?', 0],
            ['a5', 'All clear here. Auth module deployed to staging.', 0],
            ['a4', 'No blockers. Designs approved and handed off.', 0],
        ];

        $this->createMessages('ch4', $agentOpsMessages, $users, $emojis);

        // Add some message attachments
        $this->createAttachments();
    }

    private function createMessages(string $channelId, array $messages, array $users, array $emojis): void
    {
        $messageIds = [];

        foreach ($messages as $index => $msg) {
            [$authorId, $content, $daysAgo] = $msg;

            $message = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => $content,
                'author_id' => $authorId,
                'channel_id' => $channelId,
                'timestamp' => now()->subDays($daysAgo)->subHours(rand(0, 12))->subMinutes(rand(0, 59)),
                'created_at' => now()->subDays($daysAgo)->subHours(rand(0, 12))->subMinutes(rand(0, 59)),
            ]);

            $messageIds[] = $message->id;

            // Add random reactions to ~40% of messages
            if (rand(1, 10) <= 4) {
                $reactionCount = rand(1, 3);
                $reactingUsers = collect($users)->random($reactionCount);

                foreach ($reactingUsers as $userId) {
                    MessageReaction::create([
                        'id' => Str::uuid()->toString(),
                        'message_id' => $message->id,
                        'user_id' => $userId,
                        'emoji' => $emojis[array_rand($emojis)],
                    ]);
                }
            }
        }

        // Create some threaded replies
        if (count($messageIds) > 3) {
            $parentId = $messageIds[2]; // Reply to third message
            Message::create([
                'id' => Str::uuid()->toString(),
                'content' => 'Great point! I have some additional thoughts on this.',
                'author_id' => $users[array_rand($users)],
                'channel_id' => $channelId,
                'reply_to_id' => $parentId,
                'timestamp' => now()->subDays(5),
                'created_at' => now()->subDays(5),
            ]);
        }
    }

    private function createAttachments(): void
    {
        $messages = Message::inRandomOrder()->limit(8)->get();

        $attachments = [
            ['quarterly-report.pdf', 'application/pdf', 245000],
            ['dashboard-mockup.png', 'image/png', 1250000],
            ['architecture-diagram.svg', 'image/svg+xml', 45000],
            ['meeting-notes.md', 'text/markdown', 12000],
            ['api-spec.json', 'application/json', 28000],
            ['performance-metrics.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 156000],
            ['logo-variations.zip', 'application/zip', 4500000],
            ['user-research.pdf', 'application/pdf', 890000],
        ];

        foreach ($messages as $index => $message) {
            if (isset($attachments[$index])) {
                [$name, $mimeType, $size] = $attachments[$index];
                $filename = Str::random(20) . '_' . $name;
                MessageAttachment::create([
                    'id' => Str::uuid()->toString(),
                    'message_id' => $message->id,
                    'filename' => $filename,
                    'original_name' => $name,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'url' => '/storage/message-attachments/' . $filename,
                    'uploaded_by_id' => $message->author_id,
                ]);
            }
        }
    }
}
