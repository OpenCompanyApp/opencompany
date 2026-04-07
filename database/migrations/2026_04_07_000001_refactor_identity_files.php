<?php

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Migrate agents from the old 8-identity-file structure to the new
 * 2-file identity + structured memory system.
 *
 * Old structure:
 *   agents/{slug}/identity/{IDENTITY,SOUL,USER,AGENTS,TOOLS,HEARTBEAT,BOOTSTRAP,MEMORY}.md
 *   agents/{slug}/memory/{YYYY-MM-DD}.md
 *
 * New structure:
 *   agents/{slug}/identity/{IDENTITY,INSTRUCTIONS}.md
 *   agents/{slug}/memory/MEMORY.md
 *   agents/{slug}/memory/topics/
 *   agents/{slug}/memory/logs/{YYYY-MM-DD}.md
 *   agents/{slug}/memory/peers/users/
 *   agents/{slug}/memory/peers/agents/
 */
return new class extends Migration
{
    public function up(): void
    {
        $agentsFolder = Document::where('title', 'agents')
            ->whereNull('parent_id')
            ->where('is_folder', true)
            ->first();

        if (!$agentsFolder) {
            return;
        }

        $agentFolders = Document::where('parent_id', $agentsFolder->id)
            ->where('is_folder', true)
            ->get();

        foreach ($agentFolders as $agentFolder) {
            $this->migrateAgentFolder($agentFolder);
        }
    }

    private function migrateAgentFolder(Document $agentFolder): void
    {
        $identityFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'identity')
            ->where('is_folder', true)
            ->first();

        if (!$identityFolder) {
            return;
        }

        $files = Document::where('parent_id', $identityFolder->id)
            ->where('is_folder', false)
            ->get()
            ->keyBy('title');

        // 1. Merge IDENTITY.md + SOUL.md → new IDENTITY.md
        $this->mergeIdentityFiles($identityFolder, $files);

        // 2. Merge USER + AGENTS + TOOLS + HEARTBEAT + BOOTSTRAP → new INSTRUCTIONS.md
        $this->createInstructionsFile($identityFolder, $files);

        // 3. Move/create MEMORY.md in memory/ folder
        $memoryFolder = $this->ensureMemoryStructure($agentFolder, $files);

        // 4. Move existing logs from memory/ to memory/logs/
        if ($memoryFolder) {
            $this->moveLogsToSubfolder($memoryFolder);
        }

        // 5. Delete old identity files
        $this->deleteOldFiles($identityFolder, $files);
    }

    private function mergeIdentityFiles(Document $identityFolder, $files): void
    {
        $identityContent = $files->get('IDENTITY.md')?->content ?? '';
        $soulContent = $files->get('SOUL.md')?->content ?? '';

        if (empty(trim($soulContent))) {
            // No SOUL content — keep IDENTITY as-is
            return;
        }

        // Merge: IDENTITY content first, then SOUL content
        $merged = $identityContent;
        if (!empty(trim($identityContent))) {
            $merged .= "\n\n";
        }
        $merged .= $soulContent;

        $identityFile = $files->get('IDENTITY.md');
        if ($identityFile) {
            $identityFile->update(['content' => $merged]);
        } else {
            Document::create([
                'id' => Str::uuid()->toString(),
                'title' => 'IDENTITY.md',
                'parent_id' => $identityFolder->id,
                'content' => $merged,
                'author_id' => $identityFolder->author_id,
                'is_folder' => false,
                'is_system' => true,
                'workspace_id' => $identityFolder->workspace_id,
            ]);
        }
    }

    private function createInstructionsFile(Document $identityFolder, $files): void
    {
        // Skip if INSTRUCTIONS.md already exists
        if ($files->has('INSTRUCTIONS.md')) {
            return;
        }

        $sections = [];

        $userContent = $files->get('USER.md')?->content;
        if ($userContent && !empty(trim($userContent))) {
            $sections[] = $userContent;
        }

        $agentsContent = $files->get('AGENTS.md')?->content;
        if ($agentsContent && !empty(trim($agentsContent))) {
            $sections[] = $agentsContent;
        }

        $toolsContent = $files->get('TOOLS.md')?->content;
        if ($toolsContent && !empty(trim($toolsContent))) {
            $sections[] = $toolsContent;
        }

        $heartbeatContent = $files->get('HEARTBEAT.md')?->content;
        if ($heartbeatContent && !empty(trim($heartbeatContent))) {
            $sections[] = $heartbeatContent;
        }

        $bootstrapContent = $files->get('BOOTSTRAP.md')?->content;
        if ($bootstrapContent && !empty(trim($bootstrapContent))) {
            $sections[] = $bootstrapContent;
        }

        $content = implode("\n\n", $sections);

        // Only create if there's actual content from the old files
        if (empty(trim($content))) {
            $content = "# Operating Instructions\n\n## User Context\n\n## Agent Network\n\n## Tool Guidelines\n";
        }

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'INSTRUCTIONS.md',
            'parent_id' => $identityFolder->id,
            'content' => $content,
            'author_id' => $identityFolder->author_id,
            'is_folder' => false,
            'is_system' => true,
            'workspace_id' => $identityFolder->workspace_id,
        ]);
    }

    private function ensureMemoryStructure(Document $agentFolder, $files): ?Document
    {
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->where('is_folder', true)
            ->first();

        if (!$memoryFolder) {
            // Create the entire memory tree
            $memoryFolder = Document::create([
                'id' => Str::uuid()->toString(),
                'title' => 'memory',
                'parent_id' => $agentFolder->id,
                'is_folder' => true,
                'is_system' => true,
                'author_id' => $agentFolder->author_id,
                'content' => '',
                'workspace_id' => $agentFolder->workspace_id,
            ]);
        }

        // Create sub-folders
        foreach (['topics', 'logs', 'peers'] as $subName) {
            $existing = Document::where('parent_id', $memoryFolder->id)
                ->where('title', $subName)
                ->where('is_folder', true)
                ->first();

            if (!$existing) {
                $sub = Document::create([
                    'id' => Str::uuid()->toString(),
                    'title' => $subName,
                    'parent_id' => $memoryFolder->id,
                    'is_folder' => true,
                    'is_system' => true,
                    'author_id' => $agentFolder->author_id,
                    'content' => '',
                    'workspace_id' => $agentFolder->workspace_id,
                ]);
            }

            // Ensure peers has users/ and agents/ sub-folders
            if ($subName === 'peers') {
                $peersFolder = Document::where('parent_id', $memoryFolder->id)
                    ->where('title', 'peers')
                    ->where('is_folder', true)
                    ->first();

                foreach (['users', 'agents'] as $peerType) {
                    Document::firstOrCreate(
                        [
                            'parent_id' => $peersFolder->id,
                            'title' => $peerType,
                            'is_folder' => true,
                            'workspace_id' => $agentFolder->workspace_id,
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'author_id' => $agentFolder->author_id,
                            'content' => '',
                            'is_system' => true,
                        ]
                    );
                }
            }
        }

        // Move/create MEMORY.md in memory/ folder
        $oldMemory = $files->get('MEMORY.md');
        $newMemoryExists = Document::where('parent_id', $memoryFolder->id)
            ->where('title', 'MEMORY.md')
            ->where('is_folder', false)
            ->exists();

        if (!$newMemoryExists) {
            $content = $oldMemory?->content ?? "# Memory\n\n## Core Knowledge\n\n## People\n\n## Topics\n";

            Document::create([
                'id' => Str::uuid()->toString(),
                'title' => 'MEMORY.md',
                'parent_id' => $memoryFolder->id,
                'content' => $content,
                'author_id' => $agentFolder->author_id,
                'is_folder' => false,
                'is_system' => true,
                'workspace_id' => $agentFolder->workspace_id,
            ]);
        }

        return $memoryFolder;
    }

    private function moveLogsToSubfolder(Document $memoryFolder): void
    {
        $logsFolder = Document::where('parent_id', $memoryFolder->id)
            ->where('title', 'logs')
            ->where('is_folder', true)
            ->first();

        if (!$logsFolder) {
            return;
        }

        // Find all date-named .md files directly in memory/ (not in sub-folders)
        $logs = Document::where('parent_id', $memoryFolder->id)
            ->where('is_folder', false)
            ->where('title', 'LIKE', '____-__-__%.md')
            ->get();

        foreach ($logs as $log) {
            // Move to logs/ folder by updating parent_id
            $log->update(['parent_id' => $logsFolder->id]);
        }
    }

    private function deleteOldFiles(Document $identityFolder, $files): void
    {
        $oldTypes = ['SOUL.md', 'USER.md', 'AGENTS.md', 'TOOLS.md', 'HEARTBEAT.md', 'BOOTSTRAP.md', 'MEMORY.md'];

        foreach ($oldTypes as $title) {
            $file = $files->get($title);
            if ($file) {
                // De-index chunks first
                DocumentChunk::where('document_id', $file->id)->delete();
                // Bypass is_system guard
                $file->update(['is_system' => false]);
                $file->delete();
            }
        }
    }

    public function down(): void
    {
        // No rollback — the old files are gone after merge.
        // To revert, restore from backup or re-run the old seeder.
    }
};
