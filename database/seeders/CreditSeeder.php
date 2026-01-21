<?php

namespace Database\Seeders;

use App\Models\CreditTransaction;
use App\Models\Task;
use App\Models\ApprovalRequest;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class CreditSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Initial credit purchase
        CreditTransaction::create([
            'id' => Str::uuid()->toString(),
            'type' => 'purchase',
            'amount' => 4000.00,
            'description' => 'Initial credit purchase - Pro Plan',
            'user_id' => 'h1',
            'created_at' => now()->subDays(30),
        ]);

        // Task usage transactions
        $taskUsages = [
            ['a5', 'Authentication system implementation', 45.50, 3],
            ['a4', 'Dashboard mockup design', 32.00, 2],
            ['a2', 'API documentation writing', 18.25, 1],
            ['a3', 'Quarterly metrics analysis', 28.75, 0],
            ['a5', 'Notification system - in progress', 22.00, 0],
            ['a4', 'User onboarding design - in progress', 15.50, 0],
            ['a3', 'Performance analysis - in progress', 12.00, 0],
            ['a6', 'Competitor research - in progress', 8.50, 0],
            ['a5', 'Database query optimization', 35.00, 7],
            ['a2', 'User guide documentation', 24.00, 8],
            ['a3', 'Weekly metrics report', 15.00, 5],
            ['a4', 'Logo redesign concepts', 28.00, 10],
            ['a6', 'Market research summary', 22.00, 12],
            ['a5', 'Bug fixes - week 1', 42.00, 14],
            ['a2', 'Release notes writing', 12.00, 6],
            ['a1', 'Task coordination and planning', 35.00, 4],
        ];

        foreach ($taskUsages as $usage) {
            [$userId, $description, $amount, $daysAgo] = $usage;

            CreditTransaction::create([
                'id' => Str::uuid()->toString(),
                'type' => 'usage',
                'amount' => -$amount,
                'description' => $description,
                'user_id' => $userId,
                'created_at' => now()->subDays($daysAgo)->subHours(rand(0, 12)),
            ]);
        }

        // Bonus credits
        CreditTransaction::create([
            'id' => Str::uuid()->toString(),
            'type' => 'bonus',
            'amount' => 200.00,
            'description' => 'Early adopter bonus credits',
            'user_id' => 'h1',
            'created_at' => now()->subDays(25),
        ]);

        // Refund
        CreditTransaction::create([
            'id' => Str::uuid()->toString(),
            'type' => 'refund',
            'amount' => 50.00,
            'description' => 'Refund for cancelled task',
            'user_id' => 'a5',
            'created_at' => now()->subDays(15),
        ]);

        // Additional purchase
        CreditTransaction::create([
            'id' => Str::uuid()->toString(),
            'type' => 'purchase',
            'amount' => 500.00,
            'description' => 'Additional credits top-up',
            'user_id' => 'h1',
            'created_at' => now()->subDays(10),
        ]);

        // Link some transactions to existing tasks and approvals
        $this->linkTransactionsToTasks();
    }

    private function linkTransactionsToTasks(): void
    {
        // Get some completed tasks
        $tasks = Task::where('status', 'done')->limit(4)->get();

        foreach ($tasks as $task) {
            // Find or create a transaction for this task
            $existingTransaction = CreditTransaction::where('description', 'like', '%' . substr($task->title, 0, 20) . '%')->first();

            if ($existingTransaction) {
                $existingTransaction->update(['task_id' => $task->id]);
            }
        }

        // Link some to approval requests
        $approvals = ApprovalRequest::where('status', 'approved')->where('amount', '>', 0)->limit(2)->get();

        foreach ($approvals as $index => $approval) {
            CreditTransaction::create([
                'id' => Str::uuid()->toString(),
                'type' => 'usage',
                'amount' => -$approval->amount,
                'description' => 'Approved: ' . $approval->title,
                'user_id' => $approval->requester_id,
                'approval_id' => $approval->id,
                'created_at' => $approval->responded_at ?? now()->subDays(rand(1, 7)),
            ]);
        }
    }
}
