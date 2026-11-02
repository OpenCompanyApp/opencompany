<?php

namespace App\Events;

use App\Domain\Vfs\Events\VfsEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Realtime "a VFS object changed" signal for open UI panels (Docs, Tables,
 * Files, Issues) so humans see agent edits live (pillar 7 stream).
 *
 * Broadcast on the workspace-private channel `workspace.{id}.vfs` (authorized
 * in routes/channels.php like the other workspace channels). The payload is a
 * refresh trigger only: object ids, canonical by-id path and the new version.
 * It never carries content, titles or friendly paths, because channel members
 * may not be allowed to read every object; panels refetch through the normal
 * permission-checked API.
 *
 * Dispatched by VfsAuditor after every committed VFS write, after the
 * surrounding transaction (if any) commits.
 */
class VfsObjectChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $type,
        public readonly string $operation,
        public readonly ?string $canonicalPath,
        public readonly ?string $backendType,
        public readonly ?string $backendId,
        public readonly ?string $version,
        public readonly ?string $actorId,
        public readonly string $occurredAt,
    ) {}

    public static function fromEvent(VfsEvent $event): self
    {
        return new self(
            workspaceId: $event->workspaceId,
            type: $event->type,
            operation: $event->operation,
            // Only by-id canonical paths are safe to broadcast; a friendly
            // path would leak titles to members who cannot read the object.
            canonicalPath: is_string($event->canonicalPath) && str_contains($event->canonicalPath, '/by-id/') ? $event->canonicalPath : null,
            backendType: $event->backendType,
            backendId: $event->backendId,
            version: $event->afterVersion,
            actorId: $event->actorId,
            occurredAt: $event->occurredAt->toIso8601ZuluString('microsecond'),
        );
    }

    public function broadcastAs(): string
    {
        return 'VfsObjectChanged';
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspace.'.$this->workspaceId.'.vfs')];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'operation' => $this->operation,
            'canonicalPath' => $this->canonicalPath,
            'backendType' => $this->backendType,
            'backendId' => $this->backendId,
            'version' => $this->version,
            'actorId' => $this->actorId,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
