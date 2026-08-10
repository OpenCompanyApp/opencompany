<?php

namespace Tests\Feature;

use App\Services\QuickJsSandboxService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Prevents agent-facing documentation and active runtime surfaces from
 * drifting back to the removed scripting contract.
 */
class CodeModeDocumentationTest extends TestCase
{
    public function test_every_static_javascript_example_compiles_in_quickjs(): void
    {
        $sandbox = app(QuickJsSandboxService::class);
        $examples = 0;

        foreach (glob(resource_path('code-docs/*.md')) ?: [] as $path) {
            $markdown = (string) file_get_contents($path);
            preg_match_all('/```(?:javascript|js)\s*\n(.*?)```/s', $markdown, $matches);

            foreach ($matches[1] as $index => $source) {
                $examples++;
                $result = $sandbox->execute(
                    code: trim($source),
                    profile: 'agent',
                    validateOnly: true,
                    sourceName: basename($path).'-'.($index + 1).'.js',
                );

                $this->assertTrue(
                    $result->succeeded(),
                    basename($path).' example '.($index + 1).' failed: '.json_encode($result->error),
                );
            }
        }

        $this->assertGreaterThan(0, $examples, 'The Code Mode guides must retain executable JavaScript examples.');
    }

    public function test_active_surfaces_have_no_removed_runtime_contracts(): void
    {
        $oldLanguage = 'l'.'ua';
        $oldDialect = $oldLanguage.'u';
        $forbidden = [
            '/\b'.preg_quote($oldLanguage, '/').'\b/i',
            '/\b'.preg_quote($oldDialect, '/').'\b/i',
            '/'.preg_quote($oldLanguage.'_', '/').'(?:exec|read_doc|search_docs|list_docs)/i',
            '/'.preg_quote('Lua'.'SandboxService', '/').'/',
            '/'.preg_quote('Lua'.'ToolProvider', '/').'/',
            '/'.preg_quote('OpenCompany'.'Lua'.'ToolInvoker', '/').'/',
            '/'.preg_quote($oldLanguage.'-docs', '/').'/i',
        ];

        $roots = [
            app_path(),
            config_path(),
            database_path(),
            base_path('routes'),
            resource_path('code-docs'),
            resource_path('js'),
        ];
        $singleFiles = [
            base_path('Dockerfile'),
            base_path('composer.json'),
            base_path('.github/workflows/ci.yml'),
        ];

        $violations = [];
        foreach (array_merge($singleFiles, $this->filesUnder($roots)) as $path) {
            $contents = (string) file_get_contents($path);
            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = str_replace(base_path().'/', '', $path);
                    break;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), 'Removed runtime references remain in active surfaces.');
    }

    /**
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function filesUnder(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $root,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'ts', 'vue', 'md', 'json'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
