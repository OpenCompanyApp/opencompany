<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\Spec\VfsGuide;
use App\Domain\Vfs\Spec\VfsSpecDocumentation;
use App\Models\User;

/**
 * Runtime tool-documentation mount (`/tools`).
 *
 * Layout:
 *   /tools/catalog.md, /tools/index.json   the agent's effective tool surface
 *                                          (VfsToolCatalogSource; permission-filtered)
 *   /tools/vfs.md = /tools/vfs/README.md   VFS guide (VfsGuide)
 *   /tools/vfs/examples.md                 worked examples (VfsGuide)
 *   /tools/vfs/reference.md                generated from the operation spec
 *   /tools/vfs/code.json, openapi.json     generated from the operation spec
 *   /tools/vfs/commands.json|md            shell command catalog (Stream A registry)
 *   /tools/vfs/commands/<c>/<c>.json|md    one command; `/tools/vfs/commands/<c>.json|md`
 *                                          resolve too (the path agents guess)
 *
 * Files here describe callable surfaces; they never execute tools and are
 * read-only. Agent breadcrumb: do not hand-write VFS reference text here;
 * generate it from VfsOperationSpecRegistry.
 */
trait MountsVfsTools
{
    /**
     * @return list<VfsEntry>
     */
    private function listTools(string $path): array
    {
        $file = fn (string $filePath): VfsEntry => new VfsEntry(basename($filePath), $filePath, 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata());
        $directory = fn (string $directoryPath): VfsEntry => new VfsEntry(basename($directoryPath), $directoryPath, 'directory', capabilities: ['browse', 'read', 'search']);

        if ($path === '/tools') {
            return [
                $file('/tools/catalog.md'),
                $file('/tools/index.json'),
                $file('/tools/vfs.md'),
                $directory('/tools/vfs'),
            ];
        }

        if ($path === '/tools/vfs') {
            return [
                $file('/tools/vfs/README.md'),
                $file('/tools/vfs/examples.md'),
                $file('/tools/vfs/reference.md'),
                $file('/tools/vfs/code.json'),
                $file('/tools/vfs/openapi.json'),
                $file('/tools/vfs/commands.json'),
                $file('/tools/vfs/commands.md'),
                $directory('/tools/vfs/commands'),
            ];
        }

        if ($path === '/tools/vfs/commands') {
            return array_map(
                fn (string $command): VfsEntry => $directory("/tools/vfs/commands/{$command}"),
                array_keys(VfsCommandExecutor::commandCatalog()),
            );
        }

        if (preg_match('#^/tools/vfs/commands/([^/.]+)$#', $path, $matches) === 1) {
            VfsCommandHelp::commandEntry($matches[1]);

            return [
                $file("{$path}/{$matches[1]}.json"),
                $file("{$path}/{$matches[1]}.md"),
            ];
        }

        throw VfsError::notReadable($path);
    }

    /**
     * Stat entry for the command-file alias `/tools/vfs/commands/<c>.json|md`,
     * which no directory listing contains. Everything else under /tools is
     * resolved through its parent listing (null here).
     */
    private function directToolEntry(string $path): ?VfsEntry
    {
        if (preg_match('#^/tools/vfs/commands/([^/]+)\.(json|md)$#', $path, $matches) !== 1) {
            return null;
        }
        VfsCommandHelp::commandEntry($matches[1]);

        return new VfsEntry(
            basename($path),
            $path,
            'file',
            canonicalPath: "/tools/vfs/commands/{$matches[1]}/{$matches[1]}.{$matches[2]}",
            capabilities: ['read', 'search'],
            metadata: $this->toolDocumentMetadata(),
        );
    }

    private function readTools(User $agent, string $path, VfsBudget $budget): string
    {
        $json = fn (mixed $value): string => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $content = match (true) {
            in_array($path, ['/tools', '/tools/vfs', '/tools/vfs/commands'], true) => $this->entriesToText($this->listTools($path)),
            $path === '/tools/catalog.md' => $this->toolCatalogMarkdown($agent),
            $path === '/tools/index.json' => $json($this->toolCatalogIndex($agent)),
            $path === '/tools/vfs.md', $path === '/tools/vfs/README.md' => VfsGuide::readme(),
            $path === '/tools/vfs/examples.md' => VfsGuide::examples(),
            $path === '/tools/vfs/reference.md' => VfsSpecDocumentation::referenceMarkdown('tools'),
            $path === '/tools/vfs/code.json' => $json(VfsSpecDocumentation::codeCatalog()),
            $path === '/tools/vfs/openapi.json' => $json(VfsSpecDocumentation::openApi()),
            $path === '/tools/vfs/commands.json' => $json(VfsCommandExecutor::commandCatalog()),
            $path === '/tools/vfs/commands.md' => VfsCommandHelp::listMarkdown(),
            default => $this->readToolCommandFile($path),
        };

        return VfsText::fit($content, $budget, $path);
    }

    /**
     * `/tools/vfs/commands/<c>` (listing), `/tools/vfs/commands/<c>/<c>.json|md`
     * and the alias `/tools/vfs/commands/<c>.json|md`.
     */
    private function readToolCommandFile(string $path): string
    {
        if (preg_match('#^/tools/vfs/commands/([^/.]+)$#', $path) === 1) {
            return $this->entriesToText($this->listTools($path));
        }

        if (preg_match('#^/tools/vfs/commands/(?:([^/]+)/)?([^/]+)\.(json|md)$#', $path, $matches) === 1
            && ($matches[1] === '' || $matches[1] === $matches[2])) {
            return $matches[3] === 'json'
                ? (string) json_encode(VfsCommandHelp::commandEntry($matches[2]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : VfsCommandHelp::commandMarkdown($matches[2]);
        }

        throw VfsError::notFound($path);
    }
}
