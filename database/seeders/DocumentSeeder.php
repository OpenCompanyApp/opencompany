<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\DocumentComment;
use App\Models\DocumentVersion;
use App\Models\DocumentAttachment;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    use WithoutModelEvents;

    private Workspace $workspace;

    public function run(): void
    {
        $this->workspace = Workspace::where('slug', 'default')->first();

        // Create folder structure
        $folders = $this->createFolders();

        // Create documents within folders
        $documents = $this->createDocuments($folders);

        // Add permissions
        $this->createPermissions($documents);

        // Add comments
        $this->createComments($documents);

        // Add versions
        $this->createVersions($documents);

        // Add attachments
        $this->createAttachments($documents);
    }

    private function createFolders(): array
    {
        $folders = [
            [
                'id' => 'folder-engineering',
                'title' => 'Engineering',
                'author_id' => 'h1',
            ],
            [
                'id' => 'folder-design',
                'title' => 'Design',
                'author_id' => 'a4',
            ],
            [
                'id' => 'folder-research',
                'title' => 'Research',
                'author_id' => 'a6',
            ],
            [
                'id' => 'folder-planning',
                'title' => 'Planning',
                'author_id' => 'a1',
            ],
        ];

        $createdFolders = [];

        foreach ($folders as $folderData) {
            $folder = Document::create([
                'id' => $folderData['id'],
                'title' => $folderData['title'],
                'content' => '',
                'author_id' => $folderData['author_id'],
                'is_folder' => true,
                'parent_id' => null,
                'workspace_id' => $this->workspace->id,
            ]);
            $createdFolders[$folderData['id']] = $folder;
        }

        return $createdFolders;
    }

    private function createDocuments(array $folders): array
    {
        $documents = [
            // Engineering docs
            [
                'title' => 'API Documentation',
                'content' => "# API Documentation\n\n## Overview\nThis document outlines the REST API endpoints for the Olympus platform.\n\n## Authentication\nAll API requests require a valid JWT token in the Authorization header.\n\n## Endpoints\n\n### Users\n- `GET /api/users` - List all users\n- `GET /api/users/:id` - Get user by ID\n- `POST /api/users` - Create new user\n\n### Tasks\n- `GET /api/tasks` - List all tasks\n- `POST /api/tasks` - Create new task\n- `PATCH /api/tasks/:id` - Update task",
                'author_id' => 'a2',
                'parent_id' => 'folder-engineering',
            ],
            [
                'title' => 'Architecture Overview',
                'content' => "# System Architecture\n\n## Technology Stack\n- **Frontend**: Vue 3 + Inertia.js\n- **Backend**: Laravel 11\n- **Database**: PostgreSQL\n- **Real-time**: Laravel Reverb\n\n## Components\n\n### API Layer\nRESTful API endpoints with JSON responses.\n\n### Agent System\nAI agents operate autonomously, coordinated by the manager agent.\n\n### Message Queue\nAsync job processing for non-blocking operations.",
                'author_id' => 'a5',
                'parent_id' => 'folder-engineering',
            ],
            [
                'title' => 'Database Schema',
                'content' => "# Database Schema\n\n## Core Tables\n\n### users\n- id (uuid, primary key)\n- name (varchar)\n- email (varchar, nullable)\n- type (enum: human, agent)\n- agent_type (varchar, nullable)\n\n### tasks\n- id (uuid, primary key)\n- title (varchar)\n- description (text)\n- status (enum)\n- assignee_id (uuid, foreign key)\n\n### messages\n- id (uuid, primary key)\n- content (text)\n- author_id (uuid)\n- channel_id (uuid)",
                'author_id' => 'a5',
                'parent_id' => 'folder-engineering',
            ],

            // Design docs
            [
                'title' => 'Brand Guidelines',
                'content' => "# Olympus Brand Guidelines\n\n## Colors\n- Primary: #1a1a1a (Dark)\n- Secondary: #ffffff (White)\n- Accent: #3b82f6 (Blue)\n\n## Typography\n- Headings: Inter, Semi-bold\n- Body: Inter, Regular\n- Code: JetBrains Mono\n\n## Spacing\nUse 4px base unit for all spacing.\n\n## Icons\nUsing Phosphor Icons for consistency.",
                'author_id' => 'a4',
                'parent_id' => 'folder-design',
            ],
            [
                'title' => 'Component Library',
                'content' => "# Component Library\n\n## Buttons\n- Primary: Dark background, white text\n- Secondary: Light background, dark text\n- Ghost: Transparent, with hover state\n\n## Cards\nRounded corners (12px), subtle shadow on hover.\n\n## Forms\n- Input fields with clear labels\n- Validation messages in red\n- Success states in green",
                'author_id' => 'a4',
                'parent_id' => 'folder-design',
            ],

            // Research docs
            [
                'title' => 'Competitor Analysis',
                'content' => "# Competitor Analysis\n\n## Overview\nAnalysis of key competitors in the AI collaboration space.\n\n## Competitors\n\n### Competitor A\n- Strengths: Large user base, established brand\n- Weaknesses: Slow feature development, limited AI capabilities\n\n### Competitor B\n- Strengths: Advanced AI features, modern UI\n- Weaknesses: High pricing, complex setup\n\n## Opportunities\n1. Focus on seamless AI-human collaboration\n2. Competitive pricing\n3. Superior real-time features",
                'author_id' => 'a6',
                'parent_id' => 'folder-research',
            ],
            [
                'title' => 'User Research Findings',
                'content' => "# User Research Findings\n\n## Methodology\n- 15 user interviews\n- 200 survey responses\n- 5 usability testing sessions\n\n## Key Insights\n\n### Pain Points\n1. Context switching between tools\n2. Difficulty tracking AI agent activities\n3. Lack of transparency in AI decisions\n\n### Desired Features\n1. Real-time collaboration with AI\n2. Clear task visibility\n3. Intuitive approval workflows",
                'author_id' => 'a6',
                'parent_id' => 'folder-research',
            ],

            // Planning docs
            [
                'title' => 'Q1 Roadmap',
                'content' => "# Q1 2026 Roadmap\n\n## Goals\n1. Launch v2.0 of the platform\n2. Onboard 100 new users\n3. Achieve 95% uptime\n\n## Milestones\n\n### January\n- Complete authentication system\n- Launch notification system\n\n### February\n- Mobile responsive design\n- Performance optimizations\n\n### March\n- Analytics dashboard\n- Integration APIs",
                'author_id' => 'a1',
                'parent_id' => 'folder-planning',
            ],
            [
                'title' => 'Team Structure',
                'content' => "# Team Structure\n\n## Human Roles\n- **Product Owner**: Rutger\n\n## AI Agents\n\n### Atlas (Manager)\nCoordinates all agent activities, manages priorities.\n\n### Echo (Writer)\nHandles documentation, content creation.\n\n### Nova (Analyst)\nData analysis, metrics, reporting.\n\n### Pixel (Creative)\nUI/UX design, visual assets.\n\n### Logic (Coder)\nSoftware development, technical implementation.\n\n### Scout (Researcher)\nMarket research, competitive analysis.",
                'author_id' => 'a1',
                'parent_id' => 'folder-planning',
            ],
            [
                'title' => 'Meeting Notes - Sprint Planning',
                'content' => "# Sprint Planning - Week 3\n\n## Date: January 15, 2026\n\n## Attendees\n- Rutger\n- Atlas\n- Logic\n- Nova\n\n## Agenda\n1. Review completed tasks\n2. Plan upcoming sprint\n3. Address blockers\n\n## Action Items\n- [ ] Logic: Complete auth module\n- [ ] Nova: Finish metrics report\n- [ ] Atlas: Update task priorities",
                'author_id' => 'h1',
                'parent_id' => 'folder-planning',
            ],
        ];

        $createdDocs = [];

        foreach ($documents as $docData) {
            $doc = Document::create([
                'id' => Str::uuid()->toString(),
                'title' => $docData['title'],
                'content' => $docData['content'],
                'author_id' => $docData['author_id'],
                'is_folder' => false,
                'parent_id' => $docData['parent_id'],
                'workspace_id' => $this->workspace->id,
            ]);
            $createdDocs[] = $doc;
        }

        return $createdDocs;
    }

    private function createPermissions(array $documents): void
    {
        $users = ['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'];

        foreach ($documents as $doc) {
            // Author always has editor access
            DocumentPermission::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $doc->id,
                'user_id' => $doc->author_id,
                'role' => 'editor',
            ]);

            // Add 2-4 additional permissions
            $otherUsers = collect($users)->reject(fn($id) => $id === $doc->author_id)->random(rand(2, 4));

            foreach ($otherUsers as $userId) {
                DocumentPermission::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $doc->id,
                    'user_id' => $userId,
                    'role' => rand(1, 3) === 1 ? 'editor' : 'viewer', // More viewers than editors
                ]);
            }
        }
    }

    private function createComments(array $documents): void
    {
        $commentTexts = [
            'Great documentation! This is very helpful.',
            'Can we add more details about error handling?',
            'I suggest we update this section with the latest changes.',
            'This needs to be reviewed before the release.',
            'Added some clarifications based on team feedback.',
            'Should we include code examples here?',
            'The structure looks good, minor typo on line 15.',
            'Can we add a diagram to explain this better?',
        ];

        foreach (array_slice($documents, 0, 6) as $doc) {
            $commentCount = rand(1, 4);
            $parentCommentId = null;

            for ($i = 0; $i < $commentCount; $i++) {
                $isResolved = rand(1, 4) === 1; // 25% chance of being resolved

                $comment = DocumentComment::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $doc->id,
                    'author_id' => collect(['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'])->random(),
                    'content' => $commentTexts[array_rand($commentTexts)],
                    'parent_id' => ($i > 0 && rand(1, 3) === 1) ? $parentCommentId : null,
                    'resolved' => $isResolved,
                    'resolved_by_id' => $isResolved ? 'h1' : null,
                    'resolved_at' => $isResolved ? now()->subDays(rand(0, 3)) : null,
                    'created_at' => now()->subDays(rand(1, 14)),
                ]);

                if ($i === 0) {
                    $parentCommentId = $comment->id;
                }
            }
        }
    }

    private function createVersions(array $documents): void
    {
        foreach (array_slice($documents, 0, 5) as $doc) {
            $versionCount = rand(2, 4);

            for ($v = 1; $v <= $versionCount; $v++) {
                DocumentVersion::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $doc->id,
                    'title' => $doc->title,
                    'content' => $doc->content . "\n\n<!-- Version $v changes -->",
                    'author_id' => $v === 1 ? $doc->author_id : collect(['h1', 'a1', 'a2'])->random(),
                    'version_number' => $v,
                    'change_description' => $v === 1 ? 'Initial version' : 'Updated content and formatting',
                    'created_at' => now()->subDays(($versionCount - $v) * 3),
                ]);
            }
        }
    }

    private function createAttachments(array $documents): void
    {
        $attachments = [
            ['architecture-diagram.png', 'image/png', 245000],
            ['api-spec.yaml', 'application/x-yaml', 18500],
            ['brand-assets.zip', 'application/zip', 4500000],
            ['research-data.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 156000],
            ['meeting-recording.mp4', 'video/mp4', 25000000],
            ['wireframes.pdf', 'application/pdf', 890000],
        ];

        foreach (array_slice($documents, 0, 6) as $index => $doc) {
            if (isset($attachments[$index])) {
                [$name, $type, $size] = $attachments[$index];

                DocumentAttachment::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $doc->id,
                    'filename' => Str::random(40) . '.' . pathinfo($name, PATHINFO_EXTENSION),
                    'original_name' => $name,
                    'mime_type' => $type,
                    'size' => $size,
                    'url' => '/storage/document-attachments/' . Str::random(40) . '/' . $name,
                    'uploaded_by_id' => $doc->author_id,
                ]);
            }
        }
    }
}
