<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy;
use App\Models\AgentPermission;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\Message;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 (Stream C2) permission model at the VFS boundary.
 *
 * Governance model: access becomes Space-level; until stored rows migrate,
 * the VFS must enforce exactly what exists today — folder deny rows,
 * DocumentPermission sharing rows, DM privacy, automation bodies and member
 * emails — and never invent new per-object grants.
 */
class VfsPermissionModelTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // Regression: deny-only folder rows used to leave the agent unrestricted.
    public function test_folder_deny_rows_hide_the_folder_and_its_descendants(): void
    {
        $finance = Document::factory()->create(['title' => 'Finance', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $secret = Document::factory()->create(['title' => 'Payroll', 'parent_id' => $finance->id, 'content' => 'salaries', 'author_id' => $this->agent->id]);
        $open = Document::factory()->create(['title' => 'Handbook', 'content' => 'welcome', 'author_id' => $this->agent->id]);
        $this->permission('folder', $finance->id, 'deny');

        $this->assertVfsCode('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$secret->id}"));
        $this->assertSame('welcome', $this->vfs->read($this->agent, "/docs/by-id/{$open->id}"));
        $this->assertStringNotContainsString('Payroll', $this->vfs->read($this->agent, '/docs'));

        // A deny wins over an allow on a parent folder.
        $root = Document::factory()->create(['title' => 'Company', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $finance->update(['parent_id' => $root->id]);
        $this->permission('folder', $root->id, 'allow');
        $this->assertVfsCode('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$secret->id}"));
    }

    // DocumentPermission rows (per-document sharing) were ignored by the VFS.
    public function test_document_permission_rows_restrict_reads_and_require_editor_for_writes(): void
    {
        $folder = Document::factory()->create(['title' => 'Shared', 'is_folder' => true, 'author_id' => $this->human()->id]);
        $doc = Document::factory()->create(['title' => 'Board memo', 'parent_id' => $folder->id, 'content' => 'confidential', 'author_id' => $this->human()->id]);
        $this->permission('folder', $folder->id, 'allow');
        DocumentPermission::create(['id' => (string) Str::uuid(), 'document_id' => $doc->id, 'user_id' => $this->human()->id, 'role' => 'editor']);

        $this->assertVfsCode('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$doc->id}"));

        DocumentPermission::create(['id' => (string) Str::uuid(), 'document_id' => $doc->id, 'user_id' => $this->agent->id, 'role' => 'viewer']);
        $this->assertSame('confidential', $this->vfs->read($this->agent, "/docs/by-id/{$doc->id}"));
        $this->assertFalse($this->vfs->canWriteDocument($this->agent, $doc->fresh(), null));
        $this->assertStringContainsString('editor role', (string) $this->vfs->documentWriteDenial($this->agent, $doc->fresh(), null));

        try {
            $this->vfs->patch($this->agent, "/docs/by-id/{$doc->id}", json_encode(['search' => 'confidential', 'replace' => 'public']));
            $this->fail('viewer must not patch');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
            $this->assertStringContainsString('editor role', $e->getMessage());
        }

        DocumentPermission::query()->where('user_id', $this->agent->id)->update(['role' => 'editor']);
        $this->assertTrue($this->vfs->canWriteDocument($this->agent, $doc->fresh(), null));
        $this->vfs->patch($this->agent, "/docs/by-id/{$doc->id}", json_encode(['search' => 'confidential', 'replace' => 'public']));
        $this->assertSame('public', $doc->fresh()->content);
    }

    // Capability metadata names the exact grant that would enable writing.
    public function test_unrestricted_agents_get_an_actionable_write_denial(): void
    {
        $doc = Document::factory()->create(['title' => 'Root note', 'content' => 'x', 'author_id' => $this->agent->id]);

        $stat = $this->vfs->stat($this->agent, "/docs/by-id/{$doc->id}");
        $this->assertSame('denied', $stat['metadata']['mutation_permission']);
        $this->assertStringContainsString('document-folder grant', $stat['metadata']['mutation_denied_reason']);
        $this->assertStringContainsString('Capabilities', $stat['metadata']['mutation_denied_reason']);

        $this->assertFalse($this->vfs->canWriteDocument($this->agent, null, null));
        $this->assertStringContainsString('document-folder grant', (string) $this->vfs->documentWriteDenial($this->agent, null, null));
    }

    // canWriteDocument is the contract Stream C1's create/move/trash helper uses.
    public function test_can_write_document_covers_creates_into_granted_folders_only(): void
    {
        $granted = Document::factory()->create(['title' => 'Ops', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $child = Document::factory()->create(['title' => 'Runbooks', 'is_folder' => true, 'parent_id' => $granted->id, 'author_id' => $this->agent->id]);
        $other = Document::factory()->create(['title' => 'Legal', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $granted->id, 'allow');

        $this->assertTrue($this->vfs->canWriteDocument($this->agent, null, $granted->id));
        $this->assertTrue($this->vfs->canWriteDocument($this->agent, null, $child->id));
        $this->assertFalse($this->vfs->canWriteDocument($this->agent, null, $other->id));
        $this->assertFalse($this->vfs->canWriteDocument($this->agent, null, (string) Str::uuid()));
        $system = Document::factory()->create(['title' => 'MEMORY.md', 'is_system' => true, 'parent_id' => $granted->id, 'author_id' => $this->agent->id]);
        $this->assertFalse($this->vfs->canWriteDocument($this->agent, $system, null));
    }

    // Matches the chat tools (ListChannels hides DMs): other principals' DM
    // transcripts are unreachable via listing, by-id guessing and search.
    public function test_dm_transcripts_of_other_principals_are_hidden(): void
    {
        $alice = $this->human();
        $bob = $this->human();
        $foreignDm = Channel::factory()->dm()->create(['name' => 'DM', 'creator_id' => $alice->id]);
        $this->member($foreignDm, $alice);
        $this->member($foreignDm, $bob);
        Message::factory()->create(['channel_id' => $foreignDm->id, 'author_id' => $alice->id, 'content' => 'private zebra talk', 'timestamp' => now()]);

        $ownDm = Channel::factory()->dm()->create(['name' => 'DM', 'creator_id' => $alice->id]);
        $this->member($ownDm, $alice);
        $this->member($ownDm, $this->agent);
        Message::factory()->create(['channel_id' => $ownDm->id, 'author_id' => $alice->id, 'content' => 'zebra briefing for Atlas', 'timestamp' => now()]);

        $listing = $this->vfs->read($this->agent, '/channels/by-id');
        $this->assertStringNotContainsString($foreignDm->id, $listing);
        $this->assertStringContainsString($ownDm->id, $listing);
        $this->assertVfsCode('not_found', fn () => $this->vfs->read($this->agent, "/channels/by-id/{$foreignDm->id}/messages/".now()->toDateString().'.md'));

        $matches = $this->vfs->searchWithMetadata($this->agent, 'zebra', ['/channels'])['matches'];
        $this->assertNotEmpty($matches);
        foreach ($matches as $match) {
            $this->assertStringNotContainsString($foreignDm->id, $match['path']);
        }

        // An admin may still allow one DM explicitly by id.
        $this->permission('channel', $foreignDm->id, 'allow');
        $this->assertStringContainsString('private zebra talk', $this->vfs->read($this->agent, "/channels/by-id/{$foreignDm->id}/messages/".now()->toDateString().'.md'));
    }

    // Colliding channel slugs get deterministic --{id8} suffixes; the bare
    // slug is ambiguous instead of silently resolving to the first match.
    public function test_channel_slug_collisions_get_deterministic_suffixes(): void
    {
        $first = Channel::factory()->public()->create(['name' => 'Sales', 'creator_id' => $this->agent->id]);
        $second = Channel::factory()->public()->create(['name' => 'sales', 'creator_id' => $this->agent->id]);
        $unique = Channel::factory()->public()->create(['name' => 'Support', 'creator_id' => $this->agent->id]);

        $paths = array_map(fn ($entry) => $entry->path, $this->vfs->list($this->agent, '/channels'));
        $this->assertContains('/channels/sales--'.substr($first->id, 0, 8), $paths);
        $this->assertContains('/channels/sales--'.substr($second->id, 0, 8), $paths);
        $this->assertContains('/channels/support', $paths);

        $this->assertSame($second->id, $this->vfs->stat($this->agent, '/channels/sales--'.substr($second->id, 0, 8))['backend_id'] ?? $this->channelIdFromListing('/channels/sales--'.substr($second->id, 0, 8)));
        $this->assertNotEmpty($this->vfs->list($this->agent, '/channels/support'));

        try {
            $this->vfs->list($this->agent, '/channels/sales');
            $this->fail('bare colliding slug must be ambiguous');
        } catch (VfsError $e) {
            $this->assertSame('ambiguous_match', $e->errorCode);
            $this->assertCount(2, $e->context['candidates']);
        }
        $this->assertNotNull($unique);
    }

    // Automation bodies (instructions, code, external results) need the
    // automation read tool; listing metadata stays visible.
    public function test_automation_bodies_require_the_automation_read_tool(): void
    {
        $automation = Automation::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Nightly sync',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'prompt' => 'Use token abc to sync',
            'script' => 'puts 1',
            'last_result' => ['output' => 'external data'],
            'is_active' => true,
            'cron_expression' => '0 2 * * *',
            'timezone' => 'UTC',
            'agent_id' => $this->agent->id,
            'created_by_id' => $this->agent->id,
        ]);

        $allowed = json_decode($this->vfs->read($this->agent, "/automations/{$automation->id}.json"), true);
        $this->assertSame('Use token abc to sync', $allowed['prompt']);

        $this->permission('tool', 'get_automation', 'deny');
        $redacted = json_decode($this->vfs->read($this->agent, "/automations/{$automation->id}.json"), true);
        $this->assertSame('Nightly sync', $redacted['name']);
        $this->assertArrayNotHasKey('prompt', $redacted);
        $this->assertArrayNotHasKey('script', $redacted);
        $this->assertArrayNotHasKey('last_result', $redacted);
        $this->assertContains('prompt', $redacted['redacted_fields']);
        $this->assertStringContainsString('nightly-sync--', $this->vfs->read($this->agent, '/automations'));
    }

    // Human emails are personal data: only an explicit members/contacts
    // grant (an allow row for list_members) reveals them.
    public function test_member_emails_require_an_explicit_members_grant(): void
    {
        $human = $this->human(['email' => 'pat@example.test']);

        $members = json_decode($this->vfs->read($this->agent, '/workspace/members.json'), true)['members'];
        $pat = collect($members)->firstWhere('user_id', $human->id);
        $this->assertNull($pat['email']);
        $this->assertTrue($pat['email_redacted']);

        $this->permission('tool', 'list_members', 'allow');
        $members = json_decode($this->vfs->read($this->agent, '/workspace/members.json'), true)['members'];
        $this->assertSame('pat@example.test', collect($members)->firstWhere('user_id', $human->id)['email']);
    }

    // Every decision, approval context and capability carries the governance
    // reversibility class so the future policy engine can gate on it.
    public function test_action_classes_are_carried_on_decisions(): void
    {
        $policy = app(VfsMutationPolicy::class);

        $this->assertSame(VfsActionClass::REVERSIBLE_EDIT, $policy->decide($this->agent, 'vfs_patch', 'patch', '/docs/by-id/x', ['vfs_patch'])->actionClass);
        $this->assertSame(VfsActionClass::DELETE, $policy->decide($this->agent, 'vfs_write', 'remove', '/files/a.txt', ['vfs_write'])->actionClass);
        $this->assertSame(VfsActionClass::DELETE, $policy->decide($this->agent, 'vfs_exec', 'exec_write', 'touch /files/a && rm /files/b', ['vfs_exec', 'vfs_write'])->actionClass);
        $this->assertSame(VfsActionClass::GOVERNANCE, $policy->decide($this->agent, 'vfs_patch', 'patch', '/agents/by-id/a1/identity/IDENTITY.md', ['vfs_patch', 'update_agent_identity_file'])->actionClass);
        $this->assertSame(VfsActionClass::STRUCTURAL, $policy->decide($this->agent, 'vfs_write', 'write', '/tables/crm/schema.json', ['vfs_write'])->actionClass);
        $this->assertSame('reversible_edit', $policy->decide($this->agent, 'vfs_patch', 'patch', '/docs/by-id/x', ['vfs_patch'])->actionMetadata()['action_class']);
    }

    private function channelIdFromListing(string $path): ?string
    {
        foreach ($this->vfs->list($this->agent, '/channels') as $entry) {
            if ($entry->path === $path) {
                return $entry->backendId;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $attributes */
    private function human(array $attributes = []): User
    {
        $human = User::factory()->create(['type' => 'human', ...$attributes]);
        WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'user_id' => $human->id],
            ['role' => 'member'],
        );

        return $human;
    }

    private function member(Channel $channel, User $user): void
    {
        ChannelMember::create(['id' => (string) Str::uuid(), 'channel_id' => $channel->id, 'user_id' => $user->id, 'role' => 'member']);
    }

    private function permission(string $scopeType, string $scopeKey, string $permission): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => $permission,
            'requires_approval' => false,
        ]);
    }

    private function assertVfsCode(string $code, callable $callback): void
    {
        try {
            $callback();
        } catch (VfsError $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());

            return;
        }

        $this->fail("Expected VFS error {$code}.");
    }
}
