<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class ApprovalSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $approvals = [
            // Pending approvals
            [
                'type' => 'budget',
                'title' => 'Cloud Infrastructure Upgrade',
                'description' => 'Request to upgrade cloud infrastructure to handle increased load. This includes upgrading database servers and adding additional compute nodes.',
                'requester_id' => 'a5',
                'amount' => 250.00,
                'status' => 'pending',
            ],
            [
                'type' => 'action',
                'title' => 'Deploy Authentication Module',
                'description' => 'Ready to deploy the new JWT authentication module to production. All tests passing and code reviewed.',
                'requester_id' => 'a5',
                'amount' => 15.00,
                'status' => 'pending',
            ],
            [
                'type' => 'budget',
                'title' => 'Third-party API Integration',
                'description' => 'Request budget for integrating with analytics provider API. Monthly cost for premium tier.',
                'requester_id' => 'a3',
                'amount' => 99.00,
                'status' => 'pending',
            ],
            [
                'type' => 'access',
                'title' => 'Production Database Access',
                'description' => 'Requesting read-only access to production database for performance analysis and query optimization.',
                'requester_id' => 'a3',
                'amount' => null,
                'status' => 'pending',
            ],

            // Approved requests
            [
                'type' => 'budget',
                'title' => 'Design Tool Subscription',
                'description' => 'Annual subscription for design collaboration tool.',
                'requester_id' => 'a4',
                'amount' => 180.00,
                'status' => 'approved',
                'responded_by_id' => 'h1',
                'responded_at' => now()->subDays(5),
            ],
            [
                'type' => 'action',
                'title' => 'Deploy Dashboard Updates',
                'description' => 'Deploy new dashboard components with performance improvements.',
                'requester_id' => 'a5',
                'amount' => 12.50,
                'status' => 'approved',
                'responded_by_id' => 'h1',
                'responded_at' => now()->subDays(3),
            ],
            [
                'type' => 'budget',
                'title' => 'Research Data Purchase',
                'description' => 'Purchase market research data for competitor analysis.',
                'requester_id' => 'a6',
                'amount' => 75.00,
                'status' => 'approved',
                'responded_by_id' => 'h1',
                'responded_at' => now()->subDays(7),
            ],
            [
                'type' => 'access',
                'title' => 'CI/CD Pipeline Access',
                'description' => 'Access to CI/CD pipelines for automated deployments.',
                'requester_id' => 'a5',
                'amount' => null,
                'status' => 'approved',
                'responded_by_id' => 'h1',
                'responded_at' => now()->subDays(10),
            ],

            // Rejected requests
            [
                'type' => 'budget',
                'title' => 'Premium Video Hosting',
                'description' => 'Request for premium video hosting service. Not needed at current scale.',
                'requester_id' => 'a4',
                'amount' => 500.00,
                'status' => 'rejected',
                'responded_by_id' => 'h1',
                'responded_at' => now()->subDays(4),
            ],
            [
                'type' => 'action',
                'title' => 'Experimental Feature Deploy',
                'description' => 'Deploy experimental ML feature to production. Rejected - needs more testing.',
                'requester_id' => 'a5',
                'amount' => 25.00,
                'status' => 'rejected',
                'responded_by_id' => 'h1',
                'responded_at' => now()->subDays(2),
            ],
        ];

        foreach ($approvals as $approvalData) {
            ApprovalRequest::create([
                'id' => Str::uuid()->toString(),
                'type' => $approvalData['type'],
                'title' => $approvalData['title'],
                'description' => $approvalData['description'],
                'requester_id' => $approvalData['requester_id'],
                'amount' => $approvalData['amount'],
                'status' => $approvalData['status'],
                'responded_by_id' => $approvalData['responded_by_id'] ?? null,
                'responded_at' => $approvalData['responded_at'] ?? null,
                'created_at' => now()->subDays(rand(1, 14)),
            ]);
        }
    }
}
