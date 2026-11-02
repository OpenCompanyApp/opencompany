<?php

namespace App\Agents\Tools\System;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Decorates a tool whose governance gate requires approval: calling it creates
 * a pending ApprovalRequest instead of executing. Only a human resolves it
 * (ApprovalResolutionPolicy); ApprovalExecutionService re-checks permissions
 * and executes on approval.
 */
class ApprovalWrappedTool implements Tool
{
    private const BUDGET_PARAM_KEYS = [
        'amount', 'budget', 'cost', 'spend', 'bid', 'price',
        'daily_budget', 'total_budget', 'monthly_budget',
    ];

    /**
     * @param  array<string, mixed>  $toolMeta
     */
    public function __construct(
        private Tool $innerTool,
        private User $agent,
        private string $toolSlug,
        private array $toolMeta = [],
    ) {}

    public function name(): string
    {
        return method_exists($this->innerTool, 'name')
            ? $this->innerTool->name()
            : class_basename($this->innerTool);
    }

    public function description(): string
    {
        return $this->innerTool->description() .
            ' (Note: This tool requires human approval before execution.)';
    }

    public function handle(Request $request): string
    {
        try {
            // The amount is display-only. Invariant: agents never approve
            // anything, and the amount is an agent-supplied argument, so it
            // can never waive approval (the former below-threshold
            // auto-approval let an agent pass `price: 0` to skip the human).
            // Spend caps belong to per-agent budgets (governance-model.md).
            $budgetAmount = $this->extractBudgetAmount($request);
            $isBudget = $budgetAmount !== null;

            $amountLabel = $isBudget ? ' ($' . number_format($budgetAmount, 2) . ')' : '';

            $approval = ApprovalRequest::create([
                'id' => Str::uuid()->toString(),
                'type' => $isBudget ? 'budget' : 'action',
                'workspace_id' => $this->agent->workspace_id,
                'title' => "Tool: {$this->toolSlug}",
                'description' => "Agent {$this->agent->name} wants to execute {$this->toolSlug}{$amountLabel}",
                'requester_id' => $this->agent->id,
                'status' => 'pending',
                'amount' => $budgetAmount,
                'tool_execution_context' => [
                    'tool_slug' => $this->toolSlug,
                    'parameters' => $request->toArray(),
                ],
                'channel_id' => $request['channelId'] ?? null,
            ]);

            if ($this->agent->must_wait_for_approval) {
                $this->agent->update(['awaiting_approval_id' => $approval->id]);

                return "This action requires human approval. An approval request has been created (ID: {$approval->id}). " .
                    ($isBudget ? "Budget amount: \${$budgetAmount}. " : '') .
                    "You are configured to always wait for approvals. Execution will pause after your response. " .
                    "Tell the user you are waiting for their approval on: {$this->toolSlug}.";
            }

            return "This action requires human approval. An approval request has been created (ID: {$approval->id}). " .
                ($isBudget ? "Budget amount: \${$budgetAmount}. " : '') .
                "You can either: (1) Call wait_for_approval with this approval ID to pause until it's decided — do this if your next steps depend on this action's result. " .
                "Or (2) Continue working on other things — the action will execute automatically if approved later.";
        } catch (\Throwable $e) {
            return "Error creating approval request: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return $this->innerTool->schema($schema);
    }

    /**
     * Get the inner (unwrapped) tool instance.
     */
    public function getInnerTool(): Tool
    {
        return $this->innerTool;
    }

    /**
     * Extract a monetary amount from the tool request parameters.
     */
    private function extractBudgetAmount(Request $request): ?float
    {
        // Explicit budget_param from tool metadata
        if ($param = ($this->toolMeta['budget_param'] ?? null)) {
            $value = $request[$param] ?? null;

            return is_numeric($value) ? (float) $value : null;
        }

        // Heuristic: scan for common money-related parameter names
        foreach ($request->toArray() as $key => $value) {
            if (in_array(strtolower($key), self::BUDGET_PARAM_KEYS) && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
