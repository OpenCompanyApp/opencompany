<?php

namespace App\Services;

use Bowerbird\RubyEngine\CapabilityFailure;

/**
 * Latches the first host control failure for one disposable script execution.
 *
 * Callback and supervisor checkpoint closures share this instance, never a
 * request-global singleton. Guest rescue cannot clear it or dispatch a later
 * callback. Only explicitly safe host-authored diagnostics belong here; raw
 * provider exceptions and policy authority remain with their owning services.
 */
final class CodeExecutionFailureState
{
    private ?CapabilityFailure $failure = null;

    /** Preserve the original control failure when later checkpoints also fail. */
    public function latch(CapabilityFailure $failure): CapabilityFailure
    {
        return $this->failure ??= $failure;
    }

    /** Reject further host calls even if Ruby rescued an earlier exception. */
    public function throwIfFailed(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }

    /** @return array{type: string, message: string}|null */
    public function diagnostic(): ?array
    {
        return $this->failure === null ? null : [
            'type' => $this->failure->errorType,
            'message' => $this->failure->getMessage(),
        ];
    }
}
