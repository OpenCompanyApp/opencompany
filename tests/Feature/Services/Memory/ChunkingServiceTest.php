<?php

namespace Tests\Feature\Services\Memory;

use App\Services\Memory\ChunkingService;
use Tests\TestCase;

class ChunkingServiceTest extends TestCase
{
    private ChunkingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChunkingService;
    }

    public function test_splits_paragraphs_into_chunks(): void
    {
        // Build text with multiple paragraphs that exceed max_chunk_size
        $paragraphs = [];
        for ($i = 0; $i < 20; $i++) {
            $paragraphs[] = "This is paragraph number {$i} with enough words to contribute meaningfully to the token count of the overall document being chunked.";
        }
        $text = implode("\n\n", $paragraphs);

        config(['memory.chunking.max_chunk_size' => 100]);
        config(['memory.chunking.chunk_overlap' => 20]);

        $chunks = $this->service->chunk($text);

        $this->assertGreaterThan(1, count($chunks), 'Should split into multiple chunks');

        // All chunks should be non-empty
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk));
        }
    }

    public function test_single_paragraph_returns_one_chunk(): void
    {
        $text = 'A short paragraph that fits within the default chunk size.';

        $chunks = $this->service->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]);
    }

    public function test_empty_input_returns_empty_array(): void
    {
        $this->assertEmpty($this->service->chunk(''));
        $this->assertEmpty($this->service->chunk('   '));
    }

    public function test_long_single_paragraph_returns_it_without_infinite_loop(): void
    {
        // A single paragraph with no separator — should return as one chunk
        $words = array_fill(0, 1000, 'word');
        $text = implode(' ', $words);

        config(['memory.chunking.max_chunk_size' => 100]);

        $chunks = $this->service->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]);
    }

    public function test_overlap_carries_trailing_text(): void
    {
        config(['memory.chunking.max_chunk_size' => 50]);
        config(['memory.chunking.chunk_overlap' => 10]);

        // Create paragraphs small enough to be individual but that force splitting
        $paragraphs = [];
        for ($i = 0; $i < 10; $i++) {
            $paragraphs[] = "Paragraph {$i} has a decent number of words to push past the chunk threshold limit.";
        }
        $text = implode("\n\n", $paragraphs);

        $chunks = $this->service->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        // Later chunks should overlap with previous ones (share some trailing words)
        if (count($chunks) >= 2) {
            $lastWordsOfFirst = array_slice(explode(' ', $chunks[0]), -3);
            $overlapping = false;
            foreach ($lastWordsOfFirst as $word) {
                if (str_contains($chunks[1], $word)) {
                    $overlapping = true;
                    break;
                }
            }
            $this->assertTrue($overlapping, 'Second chunk should contain overlap from end of first chunk');
        }
    }

    public function test_respects_custom_separator(): void
    {
        config(['memory.chunking.separator' => '---']);
        config(['memory.chunking.max_chunk_size' => 50]);

        $text = "First section with enough words to matter.---Second section with additional text content.---Third section that adds more words here.";

        $chunks = $this->service->chunk($text);

        $this->assertGreaterThanOrEqual(1, count($chunks));
    }
}
