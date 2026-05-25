<?php

namespace App\Domain\Agents\Application;

/**
 * Validated input for provisioning a workspace agent.
 *
 * The HTTP controller owns validation. This value object gives the application
 * action a stable shape that can also be used by agent tools or commands.
 */
class CreateAgentInput
{
    /**
     * @param  array<string, string>  $identity
     */
    public function __construct(
        public readonly string $name,
        public readonly string $agentType,
        public readonly string $brain,
        public readonly ?string $task = null,
        public readonly ?string $behavior = null,
        public readonly bool $isEphemeral = false,
        public readonly ?string $managerId = null,
        public readonly array $identity = [],
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'],
            agentType: $validated['agentType'],
            brain: $validated['brain'],
            task: $validated['task'] ?? null,
            behavior: $validated['behavior'] ?? null,
            isEphemeral: $validated['isEphemeral'] ?? false,
            managerId: $validated['managerId'] ?? null,
            identity: $validated['identity'] ?? [],
        );
    }
}
