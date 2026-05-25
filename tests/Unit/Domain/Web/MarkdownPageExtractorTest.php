<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Extraction\MarkdownPageExtractor;
use Tests\TestCase;

class MarkdownPageExtractorTest extends TestCase
{
    public function test_extracts_unique_outline_and_sections(): void
    {
        $page = app(MarkdownPageExtractor::class)->extract("# Title\n\nIntro\n\n## Repeat\n\nOne\n\n## Repeat\n\nTwo", 'Title');

        $this->assertSame([
            ['id' => 'title', 'title' => 'Title', 'level' => 1],
            ['id' => 'repeat', 'title' => 'Repeat', 'level' => 2],
            ['id' => 'repeat-1', 'title' => 'Repeat', 'level' => 2],
        ], $page->outline);
        $this->assertStringContainsString('One', $page->sections['repeat']);
        $this->assertStringContainsString('Two', $page->sections['repeat-1']);
    }

    public function test_markdown_to_plain_text_removes_markup(): void
    {
        $text = app(MarkdownPageExtractor::class)->markdownToPlainText("## Heading\n\n[Link](https://example.com) and `code`");

        $this->assertStringContainsString('Heading', $text);
        $this->assertStringContainsString('Link and code', $text);
        $this->assertStringNotContainsString('https://example.com', $text);
    }
}
