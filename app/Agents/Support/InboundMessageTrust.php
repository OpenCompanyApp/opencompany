<?php

namespace App\Agents\Support;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Models\WorkspaceMember;

/**
 * Decides whether an inbound chat message is from inside the workspace, and
 * labels it as untrusted model input when it is not.
 *
 * Owns: the "who is a workspace insider" rule for model-facing message text.
 * An author is trusted only when it is an agent whose workspace_id is this
 * workspace, or a human with a workspace_members row for this workspace.
 * Everyone else — ephemeral Telegram/Slack/Discord shadow users, unlinked
 * external identities, deleted/missing authors — is external, and their text
 * is wrapped with UntrustedContent before it reaches the model.
 *
 * Does NOT own: channel access (AgentPermissionResolver), external identity
 * linking (chat adapters), or approval policy. Workspace scoping invariant:
 * membership is always checked against the message's own channel workspace,
 * never a human's other workspaces.
 *
 * Reuse breadcrumb: ChannelConversationLoader uses this for history. The
 * current-turn prompt built in RespondToChatMessage::buildPromptWithThreadContext
 * should use labelForModel() too so the newest inbound message is covered.
 */
final class InboundMessageTrust
{
    /** @var array<string, array<string, bool>> workspace id => author id => trusted */
    private array $cache = [];

    /**
     * Pre-resolve trust for many authors with one membership query.
     *
     * @param  iterable<User|null>  $authors
     */
    public function warm(iterable $authors, string $workspaceId): void
    {
        $humanIds = [];

        foreach ($authors as $author) {
            if (! $author instanceof User || isset($this->cache[$workspaceId][$author->id])) {
                continue;
            }

            if ($author->type === 'agent') {
                $this->cache[$workspaceId][$author->id] = $author->workspace_id === $workspaceId;
            } else {
                $humanIds[$author->id] = true;
            }
        }

        if ($humanIds === []) {
            return;
        }

        $members = WorkspaceMember::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('user_id', array_keys($humanIds))
            ->pluck('user_id')
            ->flip();

        foreach (array_keys($humanIds) as $id) {
            $this->cache[$workspaceId][$id] = $members->has($id);
        }
    }

    /**
     * Whether this author is a workspace insider for the given workspace.
     */
    public function isTrustedAuthor(?User $author, string $workspaceId): bool
    {
        if (! $author instanceof User) {
            return false;
        }

        if (! isset($this->cache[$workspaceId][$author->id])) {
            $this->warm([$author], $workspaceId);
        }

        return $this->cache[$workspaceId][$author->id] ?? false;
    }

    /**
     * Return message text for the model, wrapped when the author is external.
     *
     * The origin attribute names the channel provider (e.g. telegram) when
     * known, else the message source, so the model can tell where it came from.
     */
    public function labelForModel(Message $message, string $content, string $workspaceId, ?Channel $channel = null): string
    {
        $author = $message->relationLoaded('author') ? $message->author : $message->author()->first();

        if ($this->isTrustedAuthor($author, $workspaceId)) {
            return $content;
        }

        $origin = $channel?->external_provider ?: ($message->source ?: 'external');

        return UntrustedContent::wrap($content, 'inbound_message', (string) $origin);
    }
}
