<?php

namespace App\Agents\Runtime\Context;

class AgentContextPlan
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly array $data) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
