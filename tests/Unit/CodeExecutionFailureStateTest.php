<?php

namespace Tests\Unit;

use App\Services\CodeExecutionFailureState;
use Bowerbird\RubyEngine\CapabilityFailure;
use PHPUnit\Framework\TestCase;

/** A rescued guest failure cannot reset host control state or leak across runs. */
class CodeExecutionFailureStateTest extends TestCase
{
    public function test_first_failure_survives_later_checkpoints_and_rejects_callbacks(): void
    {
        $state = new CodeExecutionFailureState;
        $this->assertNull($state->diagnostic());
        $state->throwIfFailed();

        $first = new CapabilityFailure('callback_time_exceeded', 'The callback deadline expired.');
        $this->assertSame($first, $state->latch($first));
        $this->assertSame($first, $state->latch(new CapabilityFailure('cancelled', 'A later checkpoint cancelled.')));
        $this->assertSame([
            'type' => 'callback_time_exceeded',
            'message' => 'The callback deadline expired.',
        ], $state->diagnostic());

        $fresh = new CodeExecutionFailureState;
        $this->assertNull($fresh->diagnostic());
        $fresh->throwIfFailed();

        $this->expectExceptionObject($first);
        $state->throwIfFailed();
    }
}
