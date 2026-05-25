<?php

namespace Tests\Feature\Domain\Knowledge;

use App\Domain\Knowledge\Application\CreateAgentIdentityTree;
use App\Domain\Knowledge\Application\ReadAgentPromptDocuments;
use App\Domain\Knowledge\Application\UpdateAgentIdentityFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentIdentityDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_document_use_cases_create_read_and_update_prompt_files(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Archivist']);

        $folder = app(CreateAgentIdentityTree::class)->handle($agent, [
            'IDENTITY' => 'Original identity',
            'INSTRUCTIONS' => 'Original instructions',
        ]);
        $agent->update(['docs_folder_id' => $folder->id]);

        app(UpdateAgentIdentityFile::class)->handle($agent, 'IDENTITY', 'Updated identity');

        $files = app(ReadAgentPromptDocuments::class)->handle($agent);

        $this->assertSame('Updated identity', $files->firstWhere('title', 'IDENTITY.md')->content);
        $this->assertSame('Original instructions', $files->firstWhere('title', 'INSTRUCTIONS.md')->content);
    }

    public function test_identity_update_rejects_unknown_file_types(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Archivist']);
        $folder = app(CreateAgentIdentityTree::class)->handle($agent);
        $agent->update(['docs_folder_id' => $folder->id]);

        $this->expectException(\InvalidArgumentException::class);

        app(UpdateAgentIdentityFile::class)->handle($agent, 'SECRETS', 'Nope');
    }
}
