<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workspace-owned list automation rule (trigger -> action, optional template).
 *
 * Tenancy contract: every rule carries a direct `workspace_id` and every read
 * or mutation MUST go through `forWorkspace()`. Before 2026-11 the only tenant
 * link was the optional `list_template_id`, so template-less rules leaked to
 * every workspace via the REST API. Do not "simplify" scoping back to
 * `whereHas('template', ...)`: template-less rules would become invisible to
 * their owner and the IDOR would return for the unscoped list endpoints.
 *
 * The creating hook below is a safety net for creators that predate the
 * column (e.g. agent tools); explicit creators should still pass workspace_id.
 */
class ListAutomationRule extends Model
{
    use BelongsToWorkspace;

    protected $table = 'list_automation_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'action_type',
        'action_config',
        'list_template_id',
        'is_active',
        'last_triggered_at',
        'trigger_count',
        'created_by_id',
    ];

    /**
     * Fill a missing workspace_id from the active workspace, then the linked
     * template (background contexts only). A rule is never persisted without a tenant when either is
     * known; if neither is known the row stays NULL and is unreachable through
     * forWorkspace() (fail closed) rather than visible to everyone.
     */
    protected static function booted(): void
    {
        static::creating(function (self $rule): void {
            if ($rule->workspace_id) {
                return;
            }

            // Invariant: the active workspace wins over the template. A
            // caller-supplied template id is untrusted and may be foreign.
            if (app()->bound('currentWorkspace')) {
                $rule->workspace_id = app('currentWorkspace')->id;

                return;
            }

            if ($rule->list_template_id) {
                $rule->workspace_id = ListTemplate::query()->whereKey($rule->list_template_id)->value('workspace_id');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'trigger_conditions' => 'array',
            'action_config' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ListTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ListTemplate::class, 'list_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
