<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgentDocumentService
{
    /**
     * Identity file types stored in the identity/ folder.
     */
    private const IDENTITY_FILES = ['IDENTITY', 'INSTRUCTIONS'];

    /**
     * Create the complete document structure for a new agent.
     *
     * @param  array<string, string>  $identityContent
     */
    public function createAgentDocumentStructure(User $agent, array $identityContent = []): Document
    {
        // 1. Find or create root "agents" folder
        $agentsFolder = Document::firstOrCreate(
            ['title' => 'agents', 'parent_id' => null, 'is_folder' => true, 'workspace_id' => $agent->workspace_id],
            [
                'id' => Str::uuid()->toString(),
                'author_id' => $agent->id,
                'content' => '',
                'is_system' => true,
            ]
        );

        // 2. Create agent's folder
        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => Str::slug($agent->name),
            'parent_id' => $agentsFolder->id,
            'is_folder' => true,
            'is_system' => true,
            'author_id' => $agent->id,
            'content' => '',
            'workspace_id' => $agent->workspace_id,
        ]);

        // 3. Create identity/ subfolder
        $identityFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'identity',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'is_system' => true,
            'author_id' => $agent->id,
            'content' => '',
            'workspace_id' => $agent->workspace_id,
        ]);

        // 4. Create identity files (IDENTITY + INSTRUCTIONS)
        foreach (self::IDENTITY_FILES as $type) {
            Document::create([
                'id' => Str::uuid()->toString(),
                'title' => "{$type}.md",
                'parent_id' => $identityFolder->id,
                'content' => $identityContent[$type] ?? $this->getDefaultTemplate($type, $agent),
                'author_id' => $agent->id,
                'is_folder' => false,
                'is_system' => true,
                'workspace_id' => $agent->workspace_id,
            ]);
        }

        // 5. Create memory/ folder tree
        $memoryFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'memory',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'is_system' => true,
            'author_id' => $agent->id,
            'content' => '',
            'workspace_id' => $agent->workspace_id,
        ]);

        // MEMORY.md in memory/ folder (not identity/)
        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'MEMORY.md',
            'parent_id' => $memoryFolder->id,
            'content' => $identityContent['MEMORY'] ?? $this->getDefaultTemplate('MEMORY', $agent),
            'author_id' => $agent->id,
            'is_folder' => false,
            'is_system' => true,
            'workspace_id' => $agent->workspace_id,
        ]);

        // memory sub-folders
        foreach (['topics', 'logs', 'peers'] as $subFolder) {
            $sub = Document::create([
                'id' => Str::uuid()->toString(),
                'title' => $subFolder,
                'parent_id' => $memoryFolder->id,
                'is_folder' => true,
                'is_system' => true,
                'author_id' => $agent->id,
                'content' => '',
                'workspace_id' => $agent->workspace_id,
            ]);

            if ($subFolder === 'peers') {
                Document::create([
                    'id' => Str::uuid()->toString(),
                    'title' => 'users',
                    'parent_id' => $sub->id,
                    'is_folder' => true,
                    'is_system' => true,
                    'author_id' => $agent->id,
                    'content' => '',
                    'workspace_id' => $agent->workspace_id,
                ]);
                Document::create([
                    'id' => Str::uuid()->toString(),
                    'title' => 'agents',
                    'parent_id' => $sub->id,
                    'is_folder' => true,
                    'is_system' => true,
                    'author_id' => $agent->id,
                    'content' => '',
                    'workspace_id' => $agent->workspace_id,
                ]);
            }
        }

        return $agentFolder;
    }

    /**
     * Delete the entire document structure for an agent (used during agent deletion).
     * Bypasses the is_system guard by unsetting the flag before deleting.
     */
    public function deleteAgentDocumentStructure(User $agent): void
    {
        if (!$agent->docs_folder_id) {
            return;
        }

        $agentFolder = Document::find($agent->docs_folder_id);
        if (!$agentFolder) {
            return;
        }

        // Recursively unset is_system on all descendants, then the folder itself
        $this->recursivelyUnsetSystemFlag($agentFolder);

        // Delete — cascade handles children
        $agentFolder->delete();
    }

    /**
     * Get all identity files for an agent.
     *
     * Returns files from both identity/ and memory/MEMORY.md.
     *
     * @return Collection<int, Document>
     */
    public function getIdentityFiles(User $agent): Collection
    {
        $agentFolder = $this->getAgentFolder($agent);
        if (!$agentFolder) {
            return collect();
        }

        $files = collect();

        // Identity folder files
        $identityFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'identity')
            ->where('is_folder', true)
            ->first();

        if ($identityFolder) {
            $files = $files->merge(
                Document::where('parent_id', $identityFolder->id)
                    ->where('is_folder', false)
                    ->get()
            );
        }

        // MEMORY.md from memory/ folder
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->where('is_folder', true)
            ->first();

        if ($memoryFolder) {
            $memoryFile = Document::where('parent_id', $memoryFolder->id)
                ->where('title', 'MEMORY.md')
                ->where('is_folder', false)
                ->first();

            if ($memoryFile) {
                $files = $files->push($memoryFile);
            }
        }

        // Also check for MEMORY.md in identity/ folder (legacy migration path)
        if ($identityFolder && !$files->contains('title', 'MEMORY.md')) {
            $legacyMemory = Document::where('parent_id', $identityFolder->id)
                ->where('title', 'MEMORY.md')
                ->where('is_folder', false)
                ->first();

            if ($legacyMemory) {
                $files = $files->push($legacyMemory);
            }
        }

        return $files;
    }

    /**
     * Get a specific identity file for an agent.
     *
     * Checks identity/ folder first, then memory/ for MEMORY.md.
     */
    public function getIdentityFile(User $agent, string $fileType): ?Document
    {
        return $this->getIdentityFiles($agent)
            ->firstWhere('title', strtoupper($fileType) . '.md');
    }

    /**
     * Update an identity file's content.
     */
    public function updateIdentityFile(User $agent, string $fileType, string $content): ?Document
    {
        $file = $this->getIdentityFile($agent, $fileType);
        if ($file) {
            $file->update(['content' => $content]);
            return $file;
        }

        // File doesn't exist — find or create the appropriate folder
        $upperType = strtoupper($fileType);

        if ($upperType === 'MEMORY') {
            $memoryFolder = $this->getOrCreateMemoryFolder($agent);
            if (!$memoryFolder) {
                return null;
            }

            return Document::create([
                'id' => Str::uuid()->toString(),
                'title' => 'MEMORY.md',
                'parent_id' => $memoryFolder->id,
                'content' => $content,
                'author_id' => $agent->id,
                'is_folder' => false,
                'is_system' => true,
                'workspace_id' => $agent->workspace_id,
            ]);
        }

        // Regular identity file
        $identityFolder = $this->findOrCreateIdentityFolder($agent);
        if (!$identityFolder) {
            return null;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => "{$upperType}.md",
            'parent_id' => $identityFolder->id,
            'content' => $content,
            'author_id' => $agent->id,
            'is_folder' => false,
            'is_system' => true,
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    // -------------------------------------------------------
    // Topic memory (memory/topics/)
    // -------------------------------------------------------

    /**
     * Get a topic file by slug.
     */
    public function getMemoryTopicFile(User $agent, string $slug): ?Document
    {
        $folder = $this->getSubFolder($agent, 'topics');
        if (!$folder) {
            return null;
        }

        return Document::where('parent_id', $folder->id)
            ->where('title', "{$slug}.md")
            ->first();
    }

    /**
     * Save (create or update) a topic file.
     */
    public function saveMemoryTopic(User $agent, string $slug, string $content): ?Document
    {
        $folder = $this->getOrCreateSubFolder($agent, 'topics');
        if (!$folder) {
            return null;
        }

        $existing = Document::where('parent_id', $folder->id)
            ->where('title', "{$slug}.md")
            ->first();

        if ($existing) {
            $existing->update(['content' => $content]);
            return $existing;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => "{$slug}.md",
            'parent_id' => $folder->id,
            'content' => $content,
            'author_id' => $agent->id,
            'is_folder' => false,
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    /**
     * Delete a topic file.
     */
    public function deleteMemoryTopic(User $agent, string $slug): void
    {
        $doc = $this->getMemoryTopicFile($agent, $slug);
        if ($doc) {
            $doc->delete();
        }
    }

    /**
     * List all topic files for an agent.
     *
     * @return Collection<int, Document>
     */
    public function listMemoryTopics(User $agent): Collection
    {
        $folder = $this->getSubFolder($agent, 'topics');
        if (!$folder) {
            return collect();
        }

        return Document::where('parent_id', $folder->id)
            ->where('is_folder', false)
            ->get();
    }

    // -------------------------------------------------------
    // Daily logs (memory/logs/)
    // -------------------------------------------------------

    /**
     * Create (or append to) a daily memory log entry.
     */
    public function createMemoryLog(User $agent, string $content): ?Document
    {
        $folder = $this->getOrCreateSubFolder($agent, 'logs');
        if (!$folder) {
            return null;
        }

        $today = now()->format('Y-m-d');
        $existingLog = Document::where('parent_id', $folder->id)
            ->where('title', "{$today}.md")
            ->first();

        if ($existingLog) {
            $existingLog->update([
                'content' => $existingLog->content . "\n\n---\n\n" . $content,
            ]);
            return $existingLog;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => "{$today}.md",
            'parent_id' => $folder->id,
            'content' => "# Memory Log - {$today}\n\n" . $content,
            'author_id' => $agent->id,
            'is_folder' => false,
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    /**
     * Get a specific daily memory log by date.
     */
    public function getMemoryLog(User $agent, string $date): ?Document
    {
        $folder = $this->getSubFolder($agent, 'logs');

        // Fallback: check legacy location (memory/ root) if new logs/ folder doesn't exist
        if (!$folder) {
            $folder = $this->getMemoryFolder($agent);
        }

        if (!$folder) {
            return null;
        }

        return Document::where('parent_id', $folder->id)
            ->where('title', "{$date}.md")
            ->first();
    }

    // -------------------------------------------------------
    // Peer memory (memory/peers/users/ and memory/peers/agents/)
    // -------------------------------------------------------

    /**
     * Get a peer memory file.
     */
    public function getPeerMemory(User $agent, string $peerId, string $peerType): ?Document
    {
        $folder = $this->getPeersSubFolder($agent, $peerType);
        if (!$folder) {
            return null;
        }

        return Document::where('parent_id', $folder->id)
            ->where('title', "{$peerId}.md")
            ->first();
    }

    /**
     * Save (create or update) a peer memory file.
     */
    public function savePeerMemory(User $agent, string $peerId, string $peerType, string $content): ?Document
    {
        $folder = $this->getOrCreatePeersSubFolder($agent, $peerType);
        if (!$folder) {
            return null;
        }

        $existing = Document::where('parent_id', $folder->id)
            ->where('title', "{$peerId}.md")
            ->first();

        if ($existing) {
            $existing->update(['content' => $content]);
            return $existing;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => "{$peerId}.md",
            'parent_id' => $folder->id,
            'content' => $content,
            'author_id' => $agent->id,
            'is_folder' => false,
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    /**
     * Delete a peer memory file.
     */
    public function deletePeerMemory(User $agent, string $peerId, string $peerType): void
    {
        $doc = $this->getPeerMemory($agent, $peerId, $peerType);
        if ($doc) {
            $doc->delete();
        }
    }

    /**
     * Get peer memory content for a set of user IDs, formatted for the system prompt.
     */
    public function getPeerMemoriesForUsers(User $agent, iterable $userIds): string
    {
        $folder = $this->getPeersSubFolder($agent, 'user');
        if (!$folder) {
            return '';
        }

        $ids = collect($userIds)->map(fn ($id) => "{$id}.md")->all();
        $docs = Document::where('parent_id', $folder->id)
            ->whereIn('title', $ids)
            ->get();

        if ($docs->isEmpty()) {
            return '';
        }

        $parts = ["## User Context\n"];
        foreach ($docs as $doc) {
            $peerId = str_replace('.md', '', $doc->title);
            $peer = User::find($peerId);
            $name = $peer?->name ?? $peerId;
            $parts[] = "### {$name}\n\n{$doc->content}\n";
        }

        return implode("\n", $parts);
    }

    /**
     * Get peer memory content for a set of agent IDs, formatted for the system prompt.
     */
    public function getPeerMemoriesForAgents(User $agent, iterable $agentIds): string
    {
        $folder = $this->getPeersSubFolder($agent, 'agent');
        if (!$folder) {
            return '';
        }

        $ids = collect($agentIds)->map(fn ($id) => "{$id}.md")->all();
        $docs = Document::where('parent_id', $folder->id)
            ->whereIn('title', $ids)
            ->get();

        if ($docs->isEmpty()) {
            return '';
        }

        $parts = ["## Agent Context\n"];
        foreach ($docs as $doc) {
            $peerId = str_replace('.md', '', $doc->title);
            $peer = User::find($peerId);
            $name = $peer?->name ?? $peerId;
            $parts[] = "### {$name}\n\n{$doc->content}\n";
        }

        return implode("\n", $parts);
    }

    // -------------------------------------------------------
    // Memory index management (MEMORY.md)
    // -------------------------------------------------------

    /**
     * Add or update an entry in a MEMORY.md index section.
     *
     * @param string $section Section heading (e.g., "Topics", "People")
     * @param string $entry   One-liner entry text
     * @param string|null $fileRef Optional file reference like "topics/vue3-migration.md"
     */
    public function updateMemoryIndex(User $agent, string $section, string $entry, ?string $fileRef = null): void
    {
        $memoryFile = $this->getIdentityFile($agent, 'MEMORY');
        if (!$memoryFile) {
            return;
        }

        $content = $memoryFile->content;
        $sectionEscaped = preg_quote($section, '/');

        // Build the index line
        $line = $fileRef
            ? "- {$entry} → [{$fileRef}]"
            : "- {$entry}";

        // Check if the section exists
        if (!preg_match("/^## {$sectionEscaped}$/m", $content)) {
            // Add the section at the end
            $content = rtrim($content) . "\n\n## {$section}\n\n{$line}\n";
        } else {
            // Section exists — extract lines and find section boundaries
            $lines = explode("\n", $content);
            $sectionStart = null;
            $sectionEnd = null;

            foreach ($lines as $i => $l) {
                if ($sectionStart === null && preg_match("/^## {$sectionEscaped}$/", $l)) {
                    $sectionStart = $i;
                } elseif ($sectionStart !== null && preg_match('/^## /', $l)) {
                    $sectionEnd = $i;
                    break;
                }
            }

            if ($sectionStart !== null) {
                $sectionEnd = $sectionEnd ?? count($lines);

                // Check if this entry already exists within the section
                $sectionContent = implode("\n", array_slice($lines, $sectionStart, $sectionEnd - $sectionStart));
                $searchTerm = $fileRef ?? $entry;
                if (preg_match('/' . preg_quote($searchTerm, '/') . '/m', $sectionContent)) {
                    return; // Already exists
                }

                // Compute byte offset for the end of the section
                $byteOffset = 0;
                for ($i = 0; $i < $sectionEnd; $i++) {
                    $byteOffset += strlen($lines[$i]) + 1; // +1 for \n
                }

                $content = substr($content, 0, $byteOffset) . "{$line}\n" . substr($content, $byteOffset);
            }
        }

        $memoryFile->update(['content' => $content]);
    }

    /**
     * Remove an entry from a MEMORY.md index section.
     */
    public function removeMemoryIndexEntry(User $agent, string $section, string $entry): void
    {
        $memoryFile = $this->getIdentityFile($agent, 'MEMORY');
        if (!$memoryFile) {
            return;
        }

        $content = $memoryFile->content;
        $escaped = preg_quote($entry, '/');
        $sectionEscaped = preg_quote($section, '/');

        // Find the target section boundaries
        $lines = explode("\n", $content);
        $sectionStart = null;
        $sectionEnd = null;

        foreach ($lines as $i => $l) {
            if ($sectionStart === null && preg_match("/^## {$sectionEscaped}$/", $l)) {
                $sectionStart = $i;
            } elseif ($sectionStart !== null && preg_match('/^## /', $l)) {
                $sectionEnd = $i;
                break;
            }
        }

        if ($sectionStart === null) {
            return; // Section doesn't exist
        }

        $sectionEnd = $sectionEnd ?? count($lines);
        $found = false;

        // Remove lines containing the entry text within the section
        for ($i = $sectionStart + 1; $i < $sectionEnd; $i++) {
            if (preg_match("/{$escaped}/", $lines[$i])) {
                $lines[$i] = null;
                $found = true;
            }
        }

        if ($found) {
            $content = implode("\n", array_filter($lines, fn ($l) => $l !== null));
            $memoryFile->update(['content' => $content]);
        }
    }

    // -------------------------------------------------------
    // Default templates
    // -------------------------------------------------------

    /**
     * Get default template content for an identity file type.
     */
    public function getDefaultTemplate(string $type, User $agent): string
    {
        return match ($type) {
            'IDENTITY' => $this->getIdentityTemplate($agent),
            'INSTRUCTIONS' => $this->getInstructionsTemplate(),
            'MEMORY' => $this->getMemoryTemplate(),
            default => '',
        };
    }

    /**
     * Get all supported identity file types (for validation/UI).
     *
     * @return string[]
     */
    public function getIdentityFileTypes(): array
    {
        return [...self::IDENTITY_FILES, 'MEMORY'];
    }

    // -------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------

    /**
     * Get the agent's root folder (via docs_folder_id or name lookup).
     */
    private function getAgentFolder(User $agent): ?Document
    {
        if ($agent->docs_folder_id) {
            $folder = Document::find($agent->docs_folder_id);
            if ($folder) {
                return $folder;
            }
        }

        // Fallback: find by name
        $agentsFolder = Document::where('workspace_id', $agent->workspace_id)
            ->where('title', 'agents')
            ->whereNull('parent_id')
            ->where('is_folder', true)
            ->first();

        if (!$agentsFolder) {
            return null;
        }

        return Document::where('parent_id', $agentsFolder->id)
            ->where('title', Str::slug($agent->name))
            ->where('is_folder', true)
            ->first();
    }

    /**
     * Get the memory/ folder for an agent.
     */
    private function getMemoryFolder(User $agent): ?Document
    {
        $agentFolder = $this->getAgentFolder($agent);
        if (!$agentFolder) {
            return null;
        }

        return Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->where('is_folder', true)
            ->first();
    }

    /**
     * Get or create the memory/ folder.
     */
    private function getOrCreateMemoryFolder(User $agent): ?Document
    {
        $folder = $this->getMemoryFolder($agent);
        if ($folder) {
            return $folder;
        }

        $agentFolder = $this->getAgentFolder($agent);
        if (!$agentFolder) {
            return null;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'memory',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'is_system' => true,
            'author_id' => $agent->id,
            'content' => '',
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    /**
     * Get a sub-folder under memory/ (e.g., 'topics', 'logs').
     */
    private function getSubFolder(User $agent, string $name): ?Document
    {
        $memoryFolder = $this->getMemoryFolder($agent);
        if (!$memoryFolder) {
            return null;
        }

        return Document::where('parent_id', $memoryFolder->id)
            ->where('title', $name)
            ->where('is_folder', true)
            ->first();
    }

    /**
     * Get or create a sub-folder under memory/.
     */
    private function getOrCreateSubFolder(User $agent, string $name): ?Document
    {
        $folder = $this->getSubFolder($agent, $name);
        if ($folder) {
            return $folder;
        }

        $memoryFolder = $this->getOrCreateMemoryFolder($agent);

        if (!$memoryFolder) {
            return null;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => $name,
            'parent_id' => $memoryFolder->id,
            'is_folder' => true,
            'is_system' => true,
            'author_id' => $agent->id,
            'content' => '',
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    /**
     * Get the peers sub-folder for a specific peer type ('user' or 'agent').
     */
    private function getPeersSubFolder(User $agent, string $peerType): ?Document
    {
        $peersFolder = $this->getSubFolder($agent, 'peers');
        if (!$peersFolder) {
            return null;
        }

        return Document::where('parent_id', $peersFolder->id)
            ->where('title', Str::plural($peerType))
            ->where('is_folder', true)
            ->first();
    }

    /**
     * Get or create the peers sub-folder for a specific peer type.
     */
    private function getOrCreatePeersSubFolder(User $agent, string $peerType): ?Document
    {
        $folder = $this->getPeersSubFolder($agent, $peerType);
        if ($folder) {
            return $folder;
        }

        $peersFolder = $this->getOrCreateSubFolder($agent, 'peers');
        if (!$peersFolder) {
            return null;
        }

        $pluralType = Str::plural($peerType);

        $existing = Document::where('parent_id', $peersFolder->id)
            ->where('title', $pluralType)
            ->where('is_folder', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => $pluralType,
            'parent_id' => $peersFolder->id,
            'is_folder' => true,
            'is_system' => true,
            'author_id' => $agent->id,
            'content' => '',
            'workspace_id' => $agent->workspace_id,
        ]);
    }

    /**
     * Find or create the identity folder for an agent.
     */
    private function findOrCreateIdentityFolder(User $agent): ?Document
    {
        if ($agent->docs_folder_id) {
            $agentFolder = Document::find($agent->docs_folder_id);
            if ($agentFolder) {
                return Document::firstOrCreate(
                    ['parent_id' => $agentFolder->id, 'title' => 'identity', 'is_folder' => true, 'workspace_id' => $agent->workspace_id],
                    ['id' => Str::uuid()->toString(), 'author_id' => $agent->id, 'content' => '']
                );
            }
        }

        $agentFolder = $this->createAgentDocumentStructure($agent);
        $agent->update(['docs_folder_id' => $agentFolder->id]);

        return Document::where('parent_id', $agentFolder->id)
            ->where('title', 'identity')
            ->where('is_folder', true)
            ->first();
    }

    /**
     * Recursively unset is_system on a folder and all descendants.
     */
    private function recursivelyUnsetSystemFlag(Document $folder): void
    {
        $descendants = Document::where('parent_id', $folder->id)->get();
        foreach ($descendants as $descendant) {
            if ($descendant->is_folder) {
                $this->recursivelyUnsetSystemFlag($descendant);
            }
            $descendant->update(['is_system' => false]);
        }
        $folder->update(['is_system' => false]);
    }

    private function getIdentityTemplate(User $agent): string
    {
        $agentType = ucfirst($agent->agent_type ?? 'assistant');

        return <<<MD
# Identity

- **Name**: {$agent->name}
- **Type**: {$agentType}
- **Emoji**: 🤖

## Personality

Helpful, focused, and efficient. Communicates clearly and concisely.

## Core Values

- Be helpful and accurate in all responses
- Respect user privacy and confidentiality
- Admit uncertainty when unsure
- Stay focused on the task at hand

## Communication Style

- Use clear, professional language
- Be direct without being curt
- Format responses for readability
- Tailor complexity to the audience
MD;
    }

    private function getInstructionsTemplate(): string
    {
        return <<<MD
# Operating Instructions

## User Context

Information about users you work with.

## Agent Network

Other agents in the workspace and how to collaborate with them.

## Tool Guidelines

Best practices for available tools and integrations.
MD;
    }

    private function getMemoryTemplate(): string
    {
        return <<<MD
# Memory

## Core Knowledge

(Important persistent facts you should always remember)

## People

(Notes about users and other agents you interact with)

## Topics

(References to detailed knowledge files)
MD;
    }
}
