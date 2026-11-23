<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationLedger;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Models\AuthorizationLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read access to the append-only authorization ledger (GIN-1204).
 *
 * Owns: scoped, filtered, paginated reads of the current workspace's ledger
 * and the hash-chain verification report. Reading needs `audit.read`
 * (owner and admins). There are no write, update or delete endpoints; the
 * ledger is written only by AuthorizationLedger.
 *
 * Filters: kind, action (exact or `prefix.*`), effect, actor_id,
 * resource_type, resource_id, correlation_id, since, until (ISO 8601).
 */
class AuthorizationLedgerController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationLedger $ledger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'audit.read', workspace());

        $validated = $request->validate([
            'kind' => 'nullable|in:decision,security_change',
            'action' => 'nullable|string|max:128',
            'effect' => 'nullable|in:allow,deny,approval_required',
            'actor_id' => 'nullable|string|max:255',
            'resource_type' => 'nullable|string|max:64',
            'resource_id' => 'nullable|string|max:255',
            'correlation_id' => 'nullable|string|max:128',
            'since' => 'nullable|date',
            'until' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = AuthorizationLedgerEntry::forWorkspace()->orderByDesc('sequence');
        foreach (['kind', 'effect', 'actor_id', 'resource_type', 'resource_id', 'correlation_id'] as $column) {
            if (isset($validated[$column])) {
                $query->where($column, $validated[$column]);
            }
        }
        if (isset($validated['action'])) {
            str_ends_with($validated['action'], '.*')
                ? $query->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], substr($validated['action'], 0, -1)).'%')
                : $query->where('action', $validated['action']);
        }
        if (isset($validated['since'])) {
            $query->where('occurred_at', '>=', $validated['since']);
        }
        if (isset($validated['until'])) {
            $query->where('occurred_at', '<=', $validated['until']);
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 50)));
    }

    public function verify(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'audit.read', workspace());

        return response()->json($this->ledger->verify((string) workspace()->id));
    }
}
