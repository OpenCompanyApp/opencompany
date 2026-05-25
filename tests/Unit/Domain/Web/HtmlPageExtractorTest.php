<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Extraction\HtmlPageExtractor;
use Tests\TestCase;

class HtmlPageExtractorTest extends TestCase
{
    public function test_extracts_metadata_outline_sections_and_removes_noise(): void
    {
        $html = <<<'HTML'
            <html>
                <head>
                    <title>Clean Page</title>
                    <meta name="description" content="Useful page">
                    <link rel="canonical" href="https://example.com/canonical">
                </head>
                <body>
                    <nav>Navigation should disappear</nav>
                    <main>
                        <h1>Clean Page</h1>
                        <p>Intro content.</p>
                        <h2>Details</h2>
                        <p>Detailed content.</p>
                        <script>window.secret = true</script>
                    </main>
                </body>
            </html>
            HTML;

        $page = app(HtmlPageExtractor::class)->extract($html, 'https://example.com/page');

        $this->assertSame('Clean Page', $page->title);
        $this->assertSame('Useful page', $page->metadata['description']);
        $this->assertSame('https://example.com/canonical', $page->metadata['canonical_url']);
        $this->assertSame('Details', $page->outline[1]['title']);
        $this->assertStringContainsString('Detailed content.', $page->sections['details']);
        $this->assertStringNotContainsString('Navigation should disappear', $page->fullContent);
        $this->assertStringNotContainsString('window.secret', $page->fullContent);
    }
}
