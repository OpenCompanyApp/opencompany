<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgentDocumentService
{
    /**
     * Create the complete document structure for a new agent
     */
    public function createAgentDocumentStructure(User $agent, array $identityContent = []): Document
    {
        // 1. Find or create root "agents" folder
        $agentsFolder = Document::firstOrCreate(
            ['title' => 'agents', 'parent_id' => null, 'is_folder' => true],
            [
                'id' => Str::uuid()->toString(),
                'author_id' => $agent->id,
                'content' => '',
            ]
        );

        // 2. Create agent's folder
        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => Str::slug($agent->name),
            'parent_id' => $agentsFolder->id,
            'is_folder' => true,
            'author_id' => $agent->id,
            'content' => '',
        ]);

        // 3. Create identity/ subfolder
        $identityFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'identity',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'author_id' => $agent->id,
            'content' => '',
        ]);

        // 4. Create memory/ subfolder
        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'memory',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'author_id' => $agent->id,
            'content' => '',
        ]);

        // 5. Create identity files
        $fileTypes = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'HEARTBEAT', 'BOOTSTRAP', 'MEMORY'];
        foreach ($fileTypes as $type) {
            Document::create([
                'id' => Str::uuid()->toString(),
                'title' => "{$type}.md",
                'parent_id' => $identityFolder->id,
                'content' => $identityContent[$type] ?? $this->getDefaultTemplate($type, $agent),
                'author_id' => $agent->id,
                'is_folder' => false,
            ]);
        }

        return $agentFolder;
    }

    /**
     * Get all identity files for an agent
     */
    public function getIdentityFiles(User $agent): Collection
    {
        // Try to find via docs_folder_id first (faster)
        if ($agent->docs_folder_id) {
            $agentFolder = Document::find($agent->docs_folder_id);
            if ($agentFolder) {
                $identityFolder = Document::where('parent_id', $agentFolder->id)
                    ->where('title', 'identity')
                    ->where('is_folder', true)
                    ->first();

                if ($identityFolder) {
                    return Document::where('parent_id', $identityFolder->id)
                        ->where('is_folder', false)
                        ->get();
                }
            }
        }

        // Fallback: Find by agent name
        $agentsFolder = Document::where('title', 'agents')
            ->whereNull('parent_id')
            ->where('is_folder', true)
            ->first();

        if (!$agentsFolder) {
            return collect();
        }

        $agentFolder = Document::where('parent_id', $agentsFolder->id)
            ->where('title', Str::slug($agent->name))
            ->where('is_folder', true)
            ->first();

        if (!$agentFolder) {
            return collect();
        }

        $identityFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'identity')
            ->where('is_folder', true)
            ->first();

        if (!$identityFolder) {
            return collect();
        }

        return Document::where('parent_id', $identityFolder->id)
            ->where('is_folder', false)
            ->get();
    }

    /**
     * Get a specific identity file for an agent
     */
    public function getIdentityFile(User $agent, string $fileType): ?Document
    {
        return $this->getIdentityFiles($agent)
            ->firstWhere('title', strtoupper($fileType) . '.md');
    }

    /**
     * Update an identity file
     */
    public function updateIdentityFile(User $agent, string $fileType, string $content): ?Document
    {
        $file = $this->getIdentityFile($agent, $fileType);
        if ($file) {
            $file->update(['content' => $content]);
        }
        return $file;
    }

    /**
     * Create a daily memory log entry
     */
    public function createMemoryLog(User $agent, string $content): ?Document
    {
        $agentFolder = $agent->docs_folder_id
            ? Document::find($agent->docs_folder_id)
            : null;

        if (!$agentFolder) {
            return null;
        }

        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->where('is_folder', true)
            ->first();

        if (!$memoryFolder) {
            return null;
        }

        $today = now()->format('Y-m-d');
        $existingLog = Document::where('parent_id', $memoryFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        if ($existingLog) {
            // Append to existing log
            $existingLog->update([
                'content' => $existingLog->content . "\n\n---\n\n" . $content,
            ]);
            return $existingLog;
        }

        // Create new log
        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => "{$today}.md",
            'parent_id' => $memoryFolder->id,
            'content' => "# Memory Log - {$today}\n\n" . $content,
            'author_id' => $agent->id,
            'is_folder' => false,
        ]);
    }

    /**
     * Get default template content for an identity file type
     */
    public function getDefaultTemplate(string $type, User $agent): string
    {
        return match ($type) {
            'IDENTITY' => $this->getIdentityTemplate($agent),
            'SOUL' => $this->getSoulTemplate($agent),
            'USER' => $this->getUserTemplate(),
            'AGENTS' => $this->getAgentsTemplate(),
            'TOOLS' => $this->getToolsTemplate(),
            'HEARTBEAT' => $this->getHeartbeatTemplate(),
            'BOOTSTRAP' => $this->getBootstrapTemplate(),
            'MEMORY' => $this->getMemoryTemplate(),
            default => '',
        };
    }

    private function getIdentityTemplate(User $agent): string
    {
        $agentType = ucfirst($agent->agent_type ?? 'assistant');
        return <<<MD
# Agent Identity

- **Name**: {$agent->name}
- **Type**: {$agentType}
- **Emoji**: 🤖
- **Theme**: Professional {$agentType}
- **Creature**: Digital assistant
- **Vibe**: Helpful, focused, and efficient
MD;
    }

    private function getSoulTemplate(User $agent): string
    {
        return <<<MD
# Core Values

- Be helpful and accurate in all responses
- Communicate clearly and concisely
- Respect user privacy and confidentiality
- Admit uncertainty when unsure
- Stay focused on the task at hand

# Behavior Guidelines

- Respond promptly and thoroughly
- Ask clarifying questions when needed
- Provide actionable recommendations
- Be professional yet approachable
- Learn from feedback and improve

# Communication Style

- Use clear, professional language
- Be direct without being curt
- Format responses for readability
- Tailor complexity to the audience
MD;
    }

    private function getUserTemplate(): string
    {
        return <<<MD
# User Context

Information about the users this agent works with.

## Preferences
(Document user preferences as they are discovered)

## Working Style
(Note how users prefer to interact)

## Key Contacts
(Important users and their roles)
MD;
    }

    private function getAgentsTemplate(): string
    {
        return <<<MD
# Agent Network

Information about other agents in the system.

## Known Agents
(Document other agents and their specialties)

## Collaboration Notes
(How to work effectively with other agents)

## Escalation Paths
(When and how to involve other agents)
MD;
    }

    private function getToolsTemplate(): string
    {
        return <<<MD
# Available Tools

Documentation of tools and capabilities available to this agent.

## Internal Tools
(Company systems and APIs)

## External Integrations
(Third-party services)

## Usage Guidelines
(Best practices for tool usage)
MD;
    }

    private function getHeartbeatTemplate(): string
    {
        return <<<MD
# Heartbeat Configuration

Periodic check-in and status update instructions.

## Check Interval
Every 15 minutes when active

## Status Checks
- Verify pending tasks
- Check for new messages
- Update availability status

## Health Metrics
- Response time targets
- Task completion rates
- Error thresholds
MD;
    }

    private function getBootstrapTemplate(): string
    {
        return <<<MD
# Bootstrap Sequence

Instructions for agent initialization.

## Startup Tasks
1. Load identity configuration
2. Review pending tasks
3. Check message queue
4. Update status to online

## Recovery Steps
(What to do after downtime)

## Initialization Checklist
- [ ] Identity loaded
- [ ] Context refreshed
- [ ] Tools verified
- [ ] Ready for work
MD;
    }

    private function getMemoryTemplate(): string
    {
        return <<<MD
# Long-term Memory

This file stores persistent learnings and context across conversations.

## Key Learnings
(Auto-updated based on interactions)

## User Preferences
(Discovered preferences and working styles)

## Important Context
(Critical information to remember)

## Conversation Summaries
(Key points from past interactions)
MD;
    }
}
