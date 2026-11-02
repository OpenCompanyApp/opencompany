<?php

namespace App\Console\Commands;

use App\Domain\Vfs\Spec\VfsSpecDocumentation;
use Illuminate\Console\Command;

/**
 * Prints or regenerates the artifacts derived from the VFS operation spec.
 *
 * `--format=openapi|markdown|code` prints one artifact; `--write-docs`
 * rewrites the generated reference sections of the files listed in
 * VfsSpecDocumentation::DOC_FILES (a drift test fails when they are stale).
 * Read-only apart from --write-docs; touches no database.
 */
class VfsSpecCommand extends Command
{
    protected $signature = 'vfs:spec
        {--format=openapi : openapi, markdown (docs/tools reference section) or code (Code Mode catalog)}
        {--write-docs : Rewrite the generated reference sections in the repo docs}';

    protected $description = 'Export the VFS operation spec (OpenAPI, markdown, Code Mode catalog) or regenerate its docs';

    public function handle(): int
    {
        if ($this->option('write-docs')) {
            foreach (VfsSpecDocumentation::DOC_FILES as $file => $audience) {
                $path = base_path($file);
                $current = is_file($path) ? (string) file_get_contents($path) : '';
                $next = VfsSpecDocumentation::withGeneratedSection($current, $audience);
                if ($next !== $current) {
                    file_put_contents($path, $next);
                    $this->line("updated {$file}");
                } else {
                    $this->line("unchanged {$file}");
                }
            }

            return self::SUCCESS;
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return match ((string) $this->option('format')) {
            'openapi' => $this->emit((string) json_encode(VfsSpecDocumentation::openApi(), $flags)),
            'code' => $this->emit((string) json_encode(VfsSpecDocumentation::codeCatalog(), $flags)),
            'markdown' => $this->emit(VfsSpecDocumentation::referenceMarkdown('tools')),
            default => $this->unknownFormat(),
        };
    }

    private function emit(string $output): int
    {
        $this->output->write($output);
        if (! str_ends_with($output, "\n")) {
            $this->output->newLine();
        }

        return self::SUCCESS;
    }

    private function unknownFormat(): int
    {
        $this->error('Unknown --format. Use openapi, markdown or code.');

        return self::INVALID;
    }
}
