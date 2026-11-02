<?php

namespace Tests\Evals\Vfs\Support;

/**
 * Raised by RequestBudget when the run's model-request cap is spent. The
 * eval records the case as not scored (`stopped: budget`) rather than failed.
 */
final class EvalBudgetExhausted extends \RuntimeException {}
