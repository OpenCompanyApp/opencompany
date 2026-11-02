<?php

namespace App\Agents\Runtime;

/**
 * Signals a cooperative pause. Unlike cancellation, the logical run remains
 * resumable and a later explicit resume dispatches a fresh physical job.
 */
class AgentRunPaused extends \RuntimeException {}
