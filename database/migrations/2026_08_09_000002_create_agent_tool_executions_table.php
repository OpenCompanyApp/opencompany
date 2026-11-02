<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tool_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('agent_run_id');
            $table->string('surface', 32);
            $table->unsignedInteger('sequence');
            $table->string('tool_name');
            $table->char('signature', 64);
            $table->string('tool_invocation_id')->nullable();
            $table->string('status')->default('running');
            $table->longText('result_payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('agent_run_id')->references('id')->on('agent_runs')->cascadeOnDelete();

            // A retry must encounter the same call at the same logical point.
            // A different signature at that point is divergent and is stopped
            // rather than risking a second external mutation.
            $table->unique(
                ['agent_run_id', 'surface', 'sequence'],
                'agent_tool_executions_run_surface_sequence_unique',
            );
            $table->index(['workspace_id', 'status']);
            $table->index(['agent_run_id', 'signature']);
            $table->index('tool_invocation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tool_executions');
    }
};
