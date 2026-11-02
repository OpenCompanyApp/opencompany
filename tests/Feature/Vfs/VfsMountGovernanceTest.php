<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\Events\VfsEventSink;
use App\Domain\Vfs\OpenCompany\Mounts\AbstractVfsMount;
use App\Domain\Vfs\OpenCompany\Mounts\VfsMountRegistry;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3 mount registry (audit §5.6, recommendation 25, issue 21).
 *
 * A mount registered on the container's VfsMountRegistry is routed by
 * longest prefix and listed in `ls /` and the README with its description,
 * and it is governed without any code of its own: the workspace binding is
 * asserted before it runs, the mutation policy (deny / approval) runs before
 * its write, and the audited facade records its reads and writes. These are
 * the guarantees future connection, skills and app mounts rely on.
 */
class VfsMountGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    private FakeNotesMount $mount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        $this->mount = new FakeNotesMount('/fake', 'Fake notes mount for governance tests.');
        app(VfsMountRegistry::class)->register($this->mount);
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // A registered mount appears in `ls /` after the built-ins, with its
    // description, and in the README mount map; it routes reads.
    public function test_registered_mount_is_listed_and_routed(): void
    {
        $root = $this->vfs->list($this->agent, '/');
        $names = array_map(fn (VfsEntry $entry): string => $entry->name, $root);

        // Built-in order is stable; the new mount comes last.
        $this->assertSame(['README.md', 'me', 'agents', 'docs', 'files', 'tables', 'lists', 'tasks', 'channels', 'tools', 'routines', 'approvals', 'workspace', 'fake'], $names);
        $fake = $root[array_search('fake', $names, true)];
        $this->assertSame('Fake notes mount for governance tests.', $fake->metadata['description']);
        $this->assertTrue($root[array_search('me', $names, true)]->isAlias());

        $this->assertStringContainsString('- /fake         Fake notes mount for governance tests.', $this->vfs->read($this->agent, '/README.md'));
        $this->assertSame('hello from fake', $this->vfs->read($this->agent, '/fake/hello.txt'));
        $this->assertSame('hello from fake', trim(app(VfsCommandExecutor::class)->execute($this->agent, 'cat /fake/hello.txt')['stdout']));
    }

    // Longest whole-segment prefix wins, so nested mounts such as
    // /files/drive are possible; /fakeish is not under /fake.
    public function test_nested_prefixes_resolve_longest_first(): void
    {
        $nested = new FakeNotesMount('/fake/nested', 'Nested.', ['inner.txt' => 'from nested']);
        app(VfsMountRegistry::class)->register($nested);

        $this->assertSame('from nested', $this->vfs->read($this->agent, '/fake/nested/inner.txt'));
        $this->assertSame('hello from fake', $this->vfs->read($this->agent, '/fake/hello.txt'));
        $this->assertNotContains('nested', array_map(fn (VfsEntry $entry): string => $entry->name, $this->vfs->list($this->agent, '/')));

        try {
            $this->vfs->read($this->agent, '/fakeish/hello.txt');
            $this->fail('expected not_found');
        } catch (VfsError $e) {
            $this->assertSame('not_found', $e->errorCode);
        }

        $this->expectException(\InvalidArgumentException::class);
        app(VfsMountRegistry::class)->register(new FakeNotesMount('/fake', 'Duplicate.'));
    }

    // The workspace assertion runs before the mount: a foreign binding fails
    // closed and the mount is never called.
    public function test_workspace_binding_is_asserted_before_the_mount_runs(): void
    {
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        app()->instance('currentWorkspace', $other);

        try {
            $this->vfs->read($this->agent, '/fake/hello.txt');
            $this->fail('expected permission_denied');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
            $this->assertSame('workspace_binding_mismatch', $e->context['reason']);
        }

        $this->assertSame([], $this->mount->calls);
    }

    // The facade runs the policy before a facade-governed mount mutates:
    // a denied vfs_write never reaches the mount, a gated one creates one
    // approval, an allowed one writes and is audited as a committed write.
    public function test_mutation_policy_and_audit_wrap_the_mount(): void
    {
        $permission = AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'vfs_write',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        try {
            $this->vfs->write($this->agent, '/fake/new.txt', 'x');
            $this->fail('expected permission_denied');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
            $this->assertSame('vfs_write', $e->context['denied_tool']);
        }
        $this->assertNotContains('write', array_column($this->mount->calls, 0));

        $permission->update(['permission' => 'allow', 'requires_approval' => true]);
        try {
            $this->vfs->write($this->agent, '/fake/new.txt', 'x');
            $this->fail('expected approval_required');
        } catch (VfsError $e) {
            $this->assertSame('approval_required', $e->errorCode);
            $this->assertSame('reversible_edit', $e->context['action_class']);
        }
        $this->assertSame(1, ApprovalRequest::query()->where('requester_id', $this->agent->id)->count());
        $this->assertNotContains('write', array_column($this->mount->calls, 0));

        $permission->update(['requires_approval' => false]);
        $result = $this->vfs->write($this->agent, '/fake/new.txt', 'written');
        $this->assertSame('created', $result['status']);
        $this->assertSame('written', $this->vfs->read($this->agent, '/fake/new.txt'));
        app(VfsEventSink::class)->flush();

        $events = VfsOperationEvent::query()->where('path', '/fake/new.txt')->get();
        $this->assertSame(1, $events->where('committed', true)->where('operation', 'write')->count());
        $this->assertSame(1, $events->where('type', 'vfs.denied')->count());
        $this->assertSame(1, $events->where('type', 'vfs.read')->count());
    }

    // Recursive search from / reaches the mount's files through the live walk
    // and its own search candidates, with friendly display paths.
    public function test_search_reaches_registered_mounts(): void
    {
        $result = $this->vfs->searchWithMetadata($this->agent, 'hello from', ['/fake']);

        $this->assertSame(['/fake/hello.txt'], array_values(array_unique(array_column($result['matches'], 'path'))));
        $this->assertContains('searchCandidates', array_column($this->mount->calls, 0));
    }
}

/**
 * In-memory mount used only by VfsMountGovernanceTest. It implements no
 * governance at all (AbstractVfsMount defaults: facade-governed) and records
 * every call so tests can prove what ran before it.
 */
final class FakeNotesMount extends AbstractVfsMount
{
    /** @var list<array{0: string, 1: string}> */
    public array $calls = [];

    /**
     * @param  array<string, string>  $files
     */
    public function __construct(
        private readonly string $prefix,
        private readonly string $description,
        private array $files = ['hello.txt' => 'hello from fake'],
    ) {}

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function list(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        $this->calls[] = ['list', $path];
        if ($path !== $this->prefix) {
            throw VfsError::notFound($path);
        }

        return array_map(
            fn (string $name): VfsEntry => new VfsEntry($name, "{$this->prefix}/{$name}", 'file', "{$this->prefix}/{$name}", capabilities: ['read', 'write']),
            array_keys($this->files),
        );
    }

    public function read(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        $this->calls[] = ['read', $path];
        if ($path === $this->prefix) {
            return implode("\n", array_keys($this->files));
        }
        $name = substr($path, strlen($this->prefix) + 1);

        return isset($this->files[$name]) ? VfsText::fit($this->files[$name], $budget, $path) : throw VfsError::notFound($path);
    }

    public function write(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters, bool $permissionAlreadyChecked): array
    {
        $this->calls[] = ['write', $path];
        $name = substr($path, strlen($this->prefix) + 1);
        $status = isset($this->files[$name]) ? 'updated' : 'created';
        $this->files[$name] = $content;

        return ['status' => $status, 'path' => $path, 'canonical_path' => $path];
    }

    public function searchCandidates(User $agent, string $pattern, string $path, VfsBudget $budget, bool $regex): array
    {
        $this->calls[] = ['searchCandidates', $path];

        return array_values(array_map(
            fn (string $name): string => "{$this->prefix}/{$name}",
            array_keys(array_filter($this->files, fn (string $content): bool => str_contains($content, $pattern))),
        ));
    }
}
