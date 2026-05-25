<?php

namespace Tests\Unit;

use App\Services\Memory\PromptFrameBuilder;
use Tests\TestCase;

/**
 * Covers prompt frame assembly and section budgeting.
 */
class PromptFrameBuilderTest extends TestCase
{
    public function test_splits_stable_and_volatile_sections(): void
    {
        $builder = new PromptFrameBuilder;

        $frame = $builder->splitSections([
            ['label' => 'Header', 'content' => "Header\n"],
            ['label' => 'Current Time', 'content' => "Time\n"],
            ['label' => 'Apps', 'content' => "Apps\n"],
            ['label' => 'Current Task', 'content' => "Task\n"],
        ]);

        $this->assertSame("Header\nApps\n", $frame['stable_prompt']);
        $this->assertSame("Time\nTask\n", $frame['volatile_prompt']);
        $this->assertCount(2, $frame['stable_breakdown']);
        $this->assertCount(2, $frame['volatile_breakdown']);
    }
}
