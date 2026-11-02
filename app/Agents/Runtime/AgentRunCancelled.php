<?php

namespace App\Agents\Runtime;

/**
 * Signals cooperative cancellation without classifying it as provider failure.
 */
class AgentRunCancelled extends \RuntimeException {}
