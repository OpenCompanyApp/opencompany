<?php

namespace App\Services;

/** Host-owned cancellation; contains no provider payload or credential details. */
final class CodeExecutionCancelled extends \RuntimeException
{
    public const ERROR_CODE = 'code_execution_cancelled';

    public function __construct()
    {
        parent::__construct(self::ERROR_CODE);
    }
}
