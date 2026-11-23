<?php

namespace App\Domain\Authorization\Application;

use App\Domain\Authorization\Engine\Decision;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown by AuthorizationService::authorize() when a decision is not allow.
 *
 * HTTP mapping: reasons that mean "you cannot see this" are a 404 with the
 * same body as a missing record, so a private channel, an unshared document
 * or another workspace's resource is indistinguishable from a missing one
 * (no existence oracle). Everything else is a 403 carrying the stable
 * reason code as `error`.
 */
class AuthorizationDenied extends HttpException
{
    /** Reason codes that hide the resource's existence. */
    public const HIDDEN = [
        'workspace_boundary', 'actor_not_in_workspace', 'channel_not_member',
        'document_not_shared', 'agent_channel_not_granted',
    ];

    public function __construct(public readonly string $action, public readonly Decision $decision)
    {
        $hidden = in_array($decision->reasonCode, self::HIDDEN, true);

        parent::__construct($hidden ? 404 : 403, $hidden ? 'Not found.' : $decision->reason);
    }

    public function render(): JsonResponse
    {
        if ($this->getStatusCode() === 404) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'message' => $this->decision->reason,
            'error' => $this->decision->reasonCode,
        ], 403);
    }
}
