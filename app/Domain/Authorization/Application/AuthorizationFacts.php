<?php

namespace App\Domain\Authorization\Application;

use App\Domain\Authorization\Engine\PolicyEngine;
use App\Models\AgentPermission;
use App\Models\AiGatewayApiKey;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentPermission;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;

/**
 * Loads the plain facts PolicyEngine decides on, from Eloquent models.
 *
 * Owns: the translation from the database to the fact shapes documented in
 * tests/conformance/authorization/README.md. Every fact is scoped by the
 * workspace the caller passes; nothing here decides anything.
 *
 * Does not own: the rules (PolicyEngine) or recording (AuthorizationLedger).
 *
 * Breadcrumb for future agents: keep this the only place that knows how a
 * model maps to facts, so a Rust engine can be fed the same shapes.
 */
class AuthorizationFacts
{
    /** @return array{id: string, owner_id: string|null, preset: string} */
    public function workspace(Workspace $workspace): array
    {
        return [
            'id' => (string) $workspace->id,
            'owner_id' => $this->nullableId($workspace->owner_id),
            'preset' => $workspace->effectiveGovernancePreset(),
        ];
    }

    /**
     * Facts for an acting principal (human, agent or AI Gateway key) in one
     * workspace.
     *
     * @return array<string, mixed>
     */
    public function actor(User|AiGatewayApiKey $actor, Workspace $workspace): array
    {
        if ($actor instanceof AiGatewayApiKey) {
            return [
                'kind' => 'api_key',
                'workspace_id' => (string) $actor->workspace_id,
                'revoked' => $actor->revoked_at !== null,
                'expires_at' => $actor->expires_at?->utc()->toIso8601String(),
                'scopes' => $actor->normalizedScopes(),
            ];
        }

        if ($actor->type === 'agent') {
            return [
                'kind' => 'agent',
                'name' => (string) $actor->name,
                'workspace_id' => $this->nullableId($actor->workspace_id),
                'manager_id' => $this->nullableId($actor->manager_id),
                'channel_grants' => $this->agentChannelGrants($actor),
                'contact_rules' => $this->agentContactRules($actor),
            ];
        }

        $role = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $actor->id)
            ->value('role');

        return [
            'kind' => 'human',
            'name' => (string) $actor->name,
            'workspace_role' => is_string($role) ? $role : null,
            'platform_operator' => $this->isPlatformOperator($actor),
        ];
    }

    /**
     * Resource facts for a model, a Workspace, or prebuilt facts (array).
     *
     * @return array<string, mixed>|null
     */
    public function resource(mixed $resource): ?array
    {
        return match (true) {
            $resource === null => null,
            is_array($resource) => $resource,
            $resource instanceof Workspace => ['type' => 'workspace', 'id' => (string) $resource->id, 'workspace_id' => (string) $resource->id],
            $resource instanceof Channel => $this->channel($resource),
            $resource instanceof Message => $this->message($resource),
            $resource instanceof Document => $this->document($resource),
            $resource instanceof DocumentComment => $this->documentComment($resource),
            $resource instanceof WorkspaceMember => $this->member($resource),
            $resource instanceof ApprovalRequest => $this->approval($resource),
            $resource instanceof User => $this->agent($resource),
            default => throw new \InvalidArgumentException('No authorization facts for '.get_debug_type($resource)),
        };
    }

    /** @return array<string, mixed> */
    public function channel(Channel $channel): array
    {
        $members = ChannelMember::query()
            ->where('channel_id', $channel->id)
            ->pluck('role', 'user_id')
            ->map(fn (mixed $role): string => is_string($role) && $role !== '' ? $role : 'member')
            ->all();

        // DM participants are authoritative even when a membership row is
        // missing (rows were not always created for older DMs).
        if ($channel->type === 'dm') {
            $dm = DirectMessage::query()->where('channel_id', $channel->id)->first(['user1_id', 'user2_id']);
            foreach ([$dm?->user1_id, $dm?->user2_id] as $participant) {
                if ($participant !== null) {
                    $members[(string) $participant] ??= 'member';
                }
            }
        }

        return [
            'type' => 'channel',
            'id' => (string) $channel->id,
            'workspace_id' => (string) $channel->workspace_id,
            'channel_type' => (string) $channel->type,
            'members' => $members,
        ];
    }

    /** @return array<string, mixed> */
    public function message(Message $message): array
    {
        $channel = Channel::query()->find($message->channel_id);
        $channelFacts = $channel !== null ? $this->channel($channel) : null;

        return [
            'type' => 'message',
            'id' => (string) $message->id,
            'workspace_id' => $channelFacts['workspace_id'] ?? null,
            'owner_id' => $this->nullableId($message->author_id),
            'channel' => $channelFacts,
        ];
    }

    /** @return array<string, mixed> */
    public function reaction(MessageReaction $reaction, Channel $channel): array
    {
        return [
            'type' => 'reaction',
            'id' => (string) $reaction->id,
            'workspace_id' => (string) $channel->workspace_id,
            'owner_id' => $this->nullableId($reaction->user_id),
            'channel' => $this->channel($channel),
        ];
    }

    /**
     * Document facts with its sharing chain (itself, then ancestors, trashed
     * ancestors included: a trashed parent's sharing rows still restrict).
     *
     * @return array<string, mixed>
     */
    public function document(Document $document): array
    {
        $chain = [];
        $node = $document;
        for ($depth = 0; $node !== null && $depth < 30; $depth++) {
            $chain[] = ['id' => (string) $node->id, 'author_id' => $this->nullableId($node->author_id)];
            if ($node->parent_id === null || in_array((string) $node->parent_id, array_column($chain, 'id'), true)) {
                break;
            }
            $node = Document::withTrashed()->where('workspace_id', $document->workspace_id)
                ->find($node->parent_id, ['id', 'parent_id', 'author_id']);
        }

        $shares = $this->sharesFor(array_column($chain, 'id'));
        foreach ($chain as $i => $link) {
            $chain[$i]['shares'] = $shares[$link['id']] ?? [];
        }

        return $this->documentFacts($document, $chain);
    }

    /**
     * Facts for many documents of one workspace with two queries in total
     * (list and search filtering).
     *
     * @param  iterable<Document>  $documents
     * @return array<string, array<string, mixed>> keyed by document id
     */
    public function documents(Workspace $workspace, iterable $documents): array
    {
        $tree = Document::withTrashed()->where('workspace_id', $workspace->id)
            ->get(['id', 'parent_id', 'author_id'])->keyBy('id');
        $shares = $this->sharesFor($tree->keys()->map(fn ($id) => (string) $id)->all());

        $facts = [];
        foreach ($documents as $document) {
            $chain = [];
            $id = (string) $document->id;
            for ($depth = 0; $id !== '' && $depth < 30 && ! in_array($id, array_column($chain, 'id'), true); $depth++) {
                $node = $tree->get($id);
                if ($node === null) {
                    break;
                }
                $chain[] = [
                    'id' => $id,
                    'author_id' => $this->nullableId($node->author_id),
                    'shares' => $shares[$id] ?? [],
                ];
                $id = (string) ($node->parent_id ?? '');
            }
            $facts[(string) $document->id] = $this->documentFacts($document, $chain);
        }

        return $facts;
    }

    /** @return array<string, mixed> */
    public function documentComment(DocumentComment $comment): array
    {
        $document = Document::withTrashed()->find($comment->document_id);

        return [
            'type' => 'document_comment',
            'id' => (string) $comment->id,
            'workspace_id' => $document?->workspace_id !== null ? (string) $document->workspace_id : null,
            'owner_id' => $this->nullableId($comment->author_id),
            'document' => $document !== null ? $this->document($document) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function agent(User $agent): array
    {
        if ($agent->type !== 'agent') {
            throw new \InvalidArgumentException('Agent facts need an agent user.');
        }

        return [
            'type' => 'agent',
            'id' => (string) $agent->id,
            'workspace_id' => $this->nullableId($agent->workspace_id),
            'manager_id' => $this->nullableId($agent->manager_id),
        ];
    }

    /** @return array<string, mixed> */
    public function member(WorkspaceMember $member): array
    {
        return [
            'type' => 'member',
            'id' => (string) $member->id,
            'workspace_id' => (string) $member->workspace_id,
            'user_id' => (string) $member->user_id,
            'role' => (string) $member->role,
        ];
    }

    /** @return array<string, mixed> */
    public function approval(ApprovalRequest $approval): array
    {
        $workspaceId = $approval->workspace_id ?: ($approval->channel?->workspace_id ?: null);
        if ($workspaceId === null) {
            $requester = $approval->requester;
            $workspaceId = $requester?->type === 'agent' ? $requester->workspace_id : null;
        }

        return [
            'type' => 'approval',
            'id' => (string) $approval->id,
            'workspace_id' => $workspaceId !== null ? (string) $workspaceId : null,
            'requester_id' => $this->nullableId($approval->requester_id),
        ];
    }

    /** @return array<string, mixed> */
    public function aiModel(string $modelId, Workspace $workspace): array
    {
        return ['type' => 'ai_model', 'id' => $modelId, 'workspace_id' => (string) $workspace->id];
    }

    /**
     * Agent channel allow-list (AgentPermission scope `channel`, allow).
     * Null means unrestricted, matching AgentPermissionResolver.
     *
     * @return list<string>|null
     */
    public function agentChannelGrants(User $agent): ?array
    {
        $ids = AgentPermission::forAgent($agent->id)->channels()->allowed()->pluck('scope_key')
            ->map(fn (mixed $id): string => (string) $id)->values()->all();

        return $ids === [] ? null : $ids;
    }

    /** Platform operators are an install-level email allowlist, never a role. */
    public function isPlatformOperator(User $user): bool
    {
        if ($user->type !== 'human' || ! is_string($user->email) || $user->email === '') {
            return false;
        }
        $allowed = config('authorization.platform_operator_emails', []);
        $allowed = is_string($allowed) ? explode(',', $allowed) : (array) $allowed;
        $allowed = array_values(array_filter(array_map(fn ($email) => strtolower(trim((string) $email)), $allowed)));

        return in_array(strtolower(trim($user->email)), $allowed, true);
    }

    /** @return array<string, array{permission: string, requires_approval: bool}> */
    private function agentContactRules(User $agent): array
    {
        return AgentPermission::forAgent($agent->id)
            ->where('scope_type', 'agent')
            ->get(['scope_key', 'permission', 'requires_approval'])
            ->mapWithKeys(fn (AgentPermission $row): array => [(string) $row->scope_key => [
                'permission' => (string) $row->permission,
                'requires_approval' => (bool) $row->requires_approval,
            ]])
            ->all();
    }

    /**
     * @param  list<string>  $documentIds
     * @return array<string, array<string, string>> document id => user id => role
     */
    private function sharesFor(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        /** @var Collection<int, DocumentPermission> $rows */
        $rows = collect();
        foreach (array_chunk($documentIds, 500) as $chunk) {
            $rows = $rows->concat(DocumentPermission::query()->whereIn('document_id', $chunk)->get(['document_id', 'user_id', 'role']));
        }

        $shares = [];
        foreach ($rows as $row) {
            $shares[(string) $row->document_id][(string) $row->user_id] = (string) $row->role;
        }

        return $shares;
    }

    /**
     * @param  list<array<string, mixed>>  $chain
     * @return array<string, mixed>
     */
    private function documentFacts(Document $document, array $chain): array
    {
        return [
            'type' => 'document',
            'id' => (string) $document->id,
            'workspace_id' => (string) $document->workspace_id,
            'is_folder' => (bool) $document->is_folder,
            'is_system' => (bool) $document->is_system,
            'author_id' => $this->nullableId($document->author_id),
            'chain' => $chain,
        ];
    }

    /** Engine version for callers that report it. */
    public function policyVersion(): string
    {
        return PolicyEngine::VERSION;
    }

    /** A model key as a string, or null when unset (ids may be nullable columns). */
    private function nullableId(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
