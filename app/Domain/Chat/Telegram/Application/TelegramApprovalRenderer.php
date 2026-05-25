<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Renders Telegram-native approval cards.
 *
 * Approval prompts can include model-generated tool arguments and operator
 * controlled values. This renderer is the single app-owned place that decides
 * what approval details are safe for Telegram and how credential-like
 * parameters are redacted across pending cards, inspect cards, notifications,
 * and resolved edits.
 */
class TelegramApprovalRenderer
{
    public const PENDING_VERSION = 'telegram-approval-card:v1';

    public const RESOLVED_VERSION = 'telegram-approval-card:resolved:v1';

    public const RESOLUTION_NOTIFICATION_VERSION = 'telegram-approval-resolution:v1';

    public const DEFERRED_NOTIFICATION_VERSION = 'telegram-approval-notification:v1';

    public const INSPECT_VERSION = 'telegram-approval-inspect-card:v1';

    public function pendingHtml(ApprovalRequest $approval, ?Workspace $workspace): string
    {
        $context = $approval->tool_execution_context ?? [];
        $title = $this->html($approval->title);
        $workspaceName = $this->html($workspace?->name ?? $approval->channel?->workspace?->name ?? 'Workspace');
        $requesterName = $this->html($approval->requester?->name ?? 'Unknown');
        $type = $this->html(ucfirst($approval->type ?? 'action'));
        $risk = $this->html($this->riskClass($approval));
        $tool = $this->html($this->toolName($context));
        $arguments = $this->html($this->argumentSummary($context['parameters'] ?? []));
        $lines = [
            '<b>Approval needed</b>',
            '',
            "<b>{$title}</b>",
            "Workspace: {$workspaceName}",
            "Type: {$type}",
            "Tool: {$tool}",
            "Arguments: {$arguments}",
            "Risk: {$risk}",
        ];

        if ($approval->amount !== null) {
            $lines[] = 'Amount: '.number_format((float) $approval->amount, 2);
        }

        $lines[] = "Requester: {$requesterName}";
        $lines[] = 'Button expiry: 30 minutes';

        if ($approval->description) {
            $lines[] = '';
            $lines[] = $this->html(Str::limit($approval->description, 700));
        }

        return implode("\n", $lines);
    }

    public function resolvedHtml(ApprovalRequest $approval, string $status, User $responder): string
    {
        $context = $approval->tool_execution_context ?? [];
        $label = strtoupper($status);
        $title = $this->html($approval->title);
        $type = $this->html(ucfirst($approval->type ?? 'action'));
        $tool = $this->html($this->toolName($context));
        $arguments = $this->html($this->argumentSummary($context['parameters'] ?? []));
        $risk = $this->html($this->riskClass($approval));
        $requester = $this->html($approval->requester?->name ?? 'unknown');
        $name = $this->html($responder->name);
        $respondedAt = $approval->responded_at?->timezone(config('app.timezone'))->format('Y-m-d H:i T') ?? now()->format('Y-m-d H:i T');
        $lines = [
            '<b>Approval resolved</b>',
            '',
            "<b>{$title}</b>",
            "Status: {$label}",
            "Type: {$type}",
            "Tool: {$tool}",
            "Arguments: {$arguments}",
            "Risk: {$risk}",
            "By: {$name}",
            "Requested by: {$requester}",
            "Responded: {$respondedAt}",
        ];

        if ($approval->amount !== null) {
            $lines[] = 'Amount: '.number_format((float) $approval->amount, 2);
        }

        return implode("\n", $lines);
    }

    public function inspectText(ApprovalRequest $approval): string
    {
        $context = $approval->tool_execution_context ?? [];

        $lines = ['Approval detail', ''];
        $lines[] = "Title: {$approval->title}";
        $lines[] = "Type: {$approval->type}";
        $lines[] = 'Tool: '.$this->toolName($context);
        $lines[] = 'Arguments: '.$this->argumentSummary($context['parameters'] ?? []);
        $lines[] = 'Risk: '.$this->riskClass($approval);
        $lines[] = 'From: '.($approval->requester?->name ?? 'unknown');
        $lines[] = "Status: {$approval->status}";
        if ($approval->amount !== null) {
            $lines[] = 'Amount: '.number_format((float) $approval->amount, 2);
        }
        if ($approval->description) {
            $lines[] = '';
            $lines[] = Str::limit($approval->description, 700);
        }

        $lines[] = 'Audit ID: '.Str::limit($approval->id, 12, '');

        return implode("\n", $lines);
    }

    public function resolutionNotificationText(ApprovalRequest $approval, string $status, User $responder): string
    {
        return implode("\n", [
            'Approval '.strtolower($status),
            '',
            "Title: {$approval->title}",
            'By: '.$responder->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function toolName(array $context): string
    {
        $tool = $context['tool_slug'] ?? $context['tool'] ?? null;

        return is_string($tool) && $tool !== '' ? $tool : 'opencompany_action';
    }

    public function riskClass(ApprovalRequest $approval): string
    {
        if ($approval->amount !== null && (float) $approval->amount >= 1000) {
            return 'high';
        }

        return match ($approval->type) {
            'budget', 'access' => 'high',
            'spawn' => 'medium',
            default => 'normal',
        };
    }

    public function argumentSummary(mixed $parameters): string
    {
        if (! is_array($parameters) || $parameters === []) {
            return 'none';
        }

        $parts = [];
        foreach (array_slice($parameters, 0, 6, preserve_keys: true) as $key => $value) {
            $key = (string) $key;
            $parts[] = $key.'='.$this->summarizeArgumentValue($key, $value);
        }

        if (count($parameters) > 6) {
            $parts[] = '+'.(count($parameters) - 6).' more';
        }

        return implode(', ', $parts);
    }

    private function summarizeArgumentValue(string $key, mixed $value): string
    {
        if (preg_match('/(secret|token|password|credential|api[_-]?key)/i', $key)) {
            return '[redacted]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return Str::limit((string) $value, 80);
        }

        return is_array($value) ? '[array:'.count($value).']' : '['.get_debug_type($value).']';
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
