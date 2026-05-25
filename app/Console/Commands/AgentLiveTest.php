<?php

namespace App\Console\Commands;

use App\Agents\Runtime\AgentRunBuilder;
use App\Agents\Runtime\AgentRunOptions;
use App\Agents\Runtime\Subagents\GenericSubagent;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Integrations\IntegrationRuntime;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Runs an end-to-end local smoke test against the real agent runtime.
 *
 * This command intentionally performs real LLM and optional WorldBank calls, so
 * it is used for operator validation rather than unit-test coverage.
 */
class AgentLiveTest extends Command
{
    protected $signature = 'agent:live-test
        {--agent= : Agent id or name}
        {--channel= : Existing channel id}
        {--provider= : Provider override, e.g. z}
        {--model= : Model override, e.g. glm-5.1}
        {--prompt=Reply with one short sentence confirming the runtime is working. : Prompt to send}
        {--skip-worldbank : Skip the no-key WorldBank integration probe}';

    protected $description = 'Run a real agent runtime call and optional no-key integration probe';

    public function handle(AgentRunBuilder $builder, IntegrationRuntime $integrations): int
    {
        $agent = $this->resolveAgent();
        if (! $agent) {
            $this->error('No agent found. Pass --agent= or create an agent first.');

            return Command::FAILURE;
        }

        $workspace = Workspace::find($agent->workspace_id) ?? Workspace::first();
        if (! $workspace) {
            $this->error('No workspace found for live test.');

            return Command::FAILURE;
        }

        // Bind workspace manually because this command does not run through the
        // HTTP ResolveWorkspace middleware.
        app()->instance('currentWorkspace', $workspace);

        $channel = $this->resolveChannel($agent, $workspace);
        $task = $this->createTask($agent, $channel, $workspace);

        $this->table(['Field', 'Value'], [
            ['Workspace', "{$workspace->name} ({$workspace->id})"],
            ['Agent', "{$agent->name} ({$agent->id})"],
            ['Channel', "{$channel->name} ({$channel->id})"],
            ['Task', $task->id],
            ['Provider override', $this->option('provider') ?: '(agent default)'],
            ['Model override', $this->option('model') ?: '(agent default)'],
        ]);

        if (! $this->option('skip-worldbank')) {
            $this->line('Probing WorldBank integration via IntegrationRuntime...');
            // WorldBank is intentionally keyless, making it a stable live probe
            // for the integration runtime without depending on user secrets.
            $worldbank = $integrations->call($agent, 'worldbank_country_info', ['code' => 'US']);
            $this->line('WorldBank evidence: '.json_encode(array_slice((array) $worldbank, 0, 1)));
        }

        $run = $builder->build(
            $agent,
            $channel->id,
            $task->id,
            new AgentRunOptions(
                timeout: 90,
                provider: $this->option('provider') ?: null,
                model: $this->option('model') ?: null,
            ),
        );

        $subagentTools = array_filter(
            iterator_to_array($run->agent()->tools()),
            fn ($tool) => $tool instanceof GenericSubagent,
        );
        // Surface subagent exposure in the command output because regressions
        // here often come from tool catalog/permission wiring, not model calls.
        $this->line('Generic subagent tools exposed: '.count($subagentTools));

        $this->line('Calling LLM through AgentRunBuilder...');
        $result = $run->run((string) $this->option('prompt'));

        $task->complete([
            'response' => $result->text,
            'events' => array_map(fn ($event) => $event->toArray(), $result->events),
        ]);

        $this->newLine();
        $this->info('Response:');
        $this->line($result->text);

        $this->newLine();
        $this->table(['Runtime Event', 'Data'], array_map(
            fn ($event) => [$event->type, json_encode($event->data)],
            $result->events,
        ));

        $this->table(['Usage', 'Value'], [
            ['Prompt tokens', $result->response->usage->promptTokens ?? 'n/a'],
            ['Completion tokens', $result->response->usage->completionTokens ?? 'n/a'],
        ]);

        return Command::SUCCESS;
    }

    private function resolveAgent(): ?User
    {
        $agent = $this->option('agent');
        if ($agent) {
            return User::query()
                ->where('type', 'agent')
                ->where(fn ($query) => $query->whereNull('agent_type')->orWhere('agent_type', '!=', 'system'))
                ->where(fn ($query) => $query->where('id', $agent)->orWhere('name', $agent))
                ->first();
        }

        return User::query()
            ->where('type', 'agent')
            ->where(fn ($query) => $query->whereNull('agent_type')->orWhere('agent_type', '!=', 'system'))
            ->whereNotNull('workspace_id')
            ->orderBy('name')
            ->first();
    }

    private function resolveChannel(User $agent, Workspace $workspace): Channel
    {
        $channelId = $this->option('channel');
        if ($channelId) {
            return Channel::where('workspace_id', $workspace->id)->findOrFail($channelId);
        }

        $channel = Channel::firstOrCreate([
            'workspace_id' => $workspace->id,
            'name' => 'agent-live-test',
        ], [
            'id' => Str::uuid()->toString(),
            'type' => 'private',
            'description' => 'Runtime live-test channel',
            'creator_id' => $agent->id,
            'is_ephemeral' => true,
        ]);

        // Ensure the selected agent can see the smoke-test channel even if the
        // channel was created by a previous run.
        ChannelMember::firstOrCreate([
            'channel_id' => $channel->id,
            'user_id' => $agent->id,
        ], [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return $channel;
    }

    private function createTask(User $agent, Channel $channel, Workspace $workspace): Task
    {
        return Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'title' => 'Agent runtime live test',
            'description' => (string) $this->option('prompt'),
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_MANUAL,
            'agent_id' => $agent->id,
            'requester_id' => $agent->id,
            'channel_id' => $channel->id,
            'started_at' => now(),
        ]);
    }
}
