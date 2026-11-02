<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * A task describes work to humans; an agent run describes the queue/runtime
         * attempt state for that work. Keeping the records separate prevents queue
         * retries, cancellation requests, and hard-worker failures from rewriting
         * the human-facing task history ambiguously.
         */
        Schema::create('agent_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('task_id')->unique();
            $table->string('agent_id')->nullable();
            $table->string('channel_id')->nullable();
            $table->string('trigger_message_id')->nullable();
            $table->string('kind');
            $table->uuid('dispatch_key')->nullable()->unique();
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->uuid('queue_job_uuid')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
            $table->foreign('trigger_message_id')->references('id')->on('messages')->nullOnDelete();
            $table->index(['workspace_id', 'status']);
            $table->index(['agent_id', 'status']);
            $table->index('queue_job_uuid');
            $table->index('heartbeat_at');
        });

        Schema::table('messages', function (Blueprint $table): void {
            // Only the irreversible response/error delivery is correlated. A
            // unique key turns retries into an exact lookup and database guard.
            $table->uuid('agent_run_id')->nullable()->unique()->after('external_message_id');
            $table->foreign('agent_run_id')->references('id')->on('agent_runs')->nullOnDelete();
        });

        Schema::table('task_steps', function (Blueprint $table): void {
            // Provider retries may report the same completed tool invocation.
            // Preserve one durable checkpoint per task/invocation pair.
            $table->string('tool_invocation_id')->nullable()->after('task_id');
            $table->unique(['task_id', 'tool_invocation_id'], 'task_steps_tool_invocation_unique');
        });

        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->uuid('workspace_id')->nullable()->after('id');
            $table->string('participant_key')->nullable()->after('workspace_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        DB::table('direct_messages')
            ->whereNull('workspace_id')
            ->update([
                'workspace_id' => DB::raw('(SELECT workspace_id FROM channels WHERE channels.id = direct_messages.channel_id)'),
            ]);

        DB::table('direct_messages')->orderBy('id')->eachById(function (object $directMessage): void {
            $participants = [(string) $directMessage->user1_id, (string) $directMessage->user2_id];
            sort($participants, SORT_STRING);
            DB::table('direct_messages')->where('id', $directMessage->id)->update([
                'participant_key' => implode(':', $participants),
            ]);
        });

        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropUnique('direct_messages_user1_id_user2_id_unique');
            $table->unique(
                ['workspace_id', 'participant_key'],
                'direct_messages_workspace_pair_unique',
            );
            $table->index(['workspace_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropUnique('direct_messages_workspace_pair_unique');
            $table->dropIndex(['workspace_id', 'updated_at']);
            $table->dropForeign(['workspace_id']);
            $table->dropColumn(['workspace_id', 'participant_key']);
            $table->unique(['user1_id', 'user2_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['agent_run_id']);
            $table->dropUnique(['agent_run_id']);
            $table->dropColumn('agent_run_id');
        });

        Schema::table('task_steps', function (Blueprint $table): void {
            $table->dropUnique('task_steps_tool_invocation_unique');
            $table->dropColumn('tool_invocation_id');
        });

        Schema::dropIfExists('agent_runs');
    }
};
