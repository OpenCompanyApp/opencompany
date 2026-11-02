<?php

namespace App\Domain\Authorization\Application;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Raised by ApprovalResolutionPolicy::authorize() (and therefore by
 * ApprovalExecutionService::resolve()) when a responder may not decide an
 * approval. No state has changed when this is thrown: authorization runs before
 * the atomic pending->status claim.
 *
 * Extends AuthorizationException so an uncaught HTTP path renders 403 instead
 * of 500. Chat transports catch it and reply with getMessage(), which is safe
 * to show to end users. `reason` is a stable machine code for API clients.
 */
class ApprovalResolutionDenied extends AuthorizationException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
