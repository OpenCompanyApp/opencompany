<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel_id');
            $table->string('agent_id');
            $table->longText('summary');
            $table->integer('tokens_before')->default(0);
            $table->integer('tokens_after')->default(0);
            $table->integer('compaction_count')->default(0);
            $table->integer('messages_summarized')->default(0);
            $table->string('last_message_id')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['channel_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_summaries');
    }
};
