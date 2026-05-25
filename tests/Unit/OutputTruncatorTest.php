<?php

namespace Tests\Unit;

use App\Services\Memory\OutputTruncator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers large tool-output storage and preview truncation.
 */
class OutputTruncatorTest extends TestCase
{
    public function test_truncates_large_output_and_persists_full_payload(): void
    {
        Storage::fake('local');

        $truncator = new OutputTruncator(
            maxLines: 3,
            maxBytes: 40,
            disk: 'local',
            pathPrefix: 'tool-results-test',
        );

        $output = "line-1\nline-2\nline-3\nline-4\nline-5";

        $result = $truncator->truncate($output, 'call-123');

        $this->assertIsString($result);
        $this->assertStringContainsString('[truncated - full output stored at storage:', $result);
        Storage::disk('local')->assertExists('tool-results-test/'.now()->format('Y/m/d').'/tool_call-123.txt');
    }
}
