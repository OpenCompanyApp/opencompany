<?php

namespace Tests\Feature;

use App\Services\MrubySandboxService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Prevents agent-facing documentation and active runtime surfaces from
 * drifting back to the retired scripting runtimes (Lua/Luau, QuickJS).
 */
class CodeModeDocumentationTest extends TestCase
{
    // Salvaged from the retired QuickJS branch and ported to mruby: every
    // Ruby example the agent reads must compile under the real profile.
    public function test_every_static_ruby_example_validates_in_mruby(): void
    {
        if (! is_executable((string) config('code.engine_binary'))) {
            $this->markTestSkipped('Requires the pinned Ruby engine (RUBY_ENGINE_BINARY).');
        }

        $sandbox = app(MrubySandboxService::class);
        $examples = 0;

        foreach (glob(resource_path('code-docs/*.md')) ?: [] as $path) {
            $markdown = (string) file_get_contents($path);
            preg_match_all('/```ruby\s*\n(.*?)```/s', $markdown, $matches);

            foreach ($matches[1] as $index => $source) {
                $examples++;
                $result = $sandbox->execute(
                    code: trim($source),
                    profile: 'agent',
                    validateOnly: true,
                    sourceName: basename($path).'-'.($index + 1).'.rb',
                );

                $this->assertNull(
                    $result->error,
                    basename($path).' example '.($index + 1).' failed: '.json_encode($result->error),
                );
            }
        }

        $this->assertGreaterThan(0, $examples, 'The Code Mode guides must retain Ruby examples.');
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
            '/'.preg_quote('Quick'.'Js', '/').'/i',
        ];

        $roots = [
            app_path(),
            config_path(),
            // Migrations are history (they migrate legacy Lua scripts) and are
            // intentionally not scanned; factories and seeders are.
            database_path('factories'),
            database_path('seeders'),
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
