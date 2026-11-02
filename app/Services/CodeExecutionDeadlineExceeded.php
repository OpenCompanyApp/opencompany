<?php

namespace App\Services;

/** Host deadline rejection; an earlier dispatched write may still need reconciliation. */
final class CodeExecutionDeadlineExceeded extends \RuntimeException
{
    public const ERROR_CODE = 'code_execution_deadline_exceeded';

    public function __construct()
    {
        parent::__construct(self::ERROR_CODE);
    }
}
