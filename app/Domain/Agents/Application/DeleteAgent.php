<?php

namespace App\Domain\Agents\Application;

use App\Domain\Knowledge\Application\DeleteAgentIdentityTree;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an agent and its protected system document tree.
 */
class DeleteAgent
{
    public function __construct(private DeleteAgentIdentityTree $identityTree) {}

    public function handle(User $agent): void
    {
        DB::transaction(function () use ($agent): void {
            $this->identityTree->handle($agent);
            $agent->delete();
        });
    }
}
