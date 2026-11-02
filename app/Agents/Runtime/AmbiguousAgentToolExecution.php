<?php

namespace App\Agents\Runtime;

/**
 * Stops a logical run when replaying a tool could duplicate an external effect.
 */
class AmbiguousAgentToolExecution extends \RuntimeException {}
