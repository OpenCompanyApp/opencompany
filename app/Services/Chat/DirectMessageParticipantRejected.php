<?php

namespace App\Services\Chat;

/**
 * Raised by DirectMessageResolver when a DM pair is not allowed: identical
 * participants (self-DM), or a participant that is not a member/agent of the
 * active workspace (including unknown user ids).
 *
 * Extends \DomainException so older callers that caught the generic exception
 * keep working. HTTP callers map REASON_SELF to 422 and REASON_OUTSIDE_WORKSPACE
 * to 404 so the response never reveals whether a foreign user id exists.
 */
class DirectMessageParticipantRejected extends \DomainException
{
    public const REASON_SELF = 'dm_self_not_allowed';

    public const REASON_OUTSIDE_WORKSPACE = 'dm_participant_outside_workspace';

    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
