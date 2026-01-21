<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->text('content');
            $table->string('author_id');
            $table->string('channel_id');
            $table->string('reply_to_id')->nullable();
            $table->boolean('is_approval_request')->default(false);
            $table->string('approval_request_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->string('pinned_by_id')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('timestamp');

            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->onDelete('set null');
            $table->foreign('pinned_by_id')->references('id')->on('users')->onDelete('set null');
        });

        // Add self-referential foreign key after table creation
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('reply_to_id')->references('id')->on('messages')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
