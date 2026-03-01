<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_files', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('workspace_id');
            $table->string('parent_id')->nullable();
            $table->string('name');
            $table->boolean('is_folder')->default(false);
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('owner_id');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users');

            $table->unique(['workspace_id', 'parent_id', 'name'], 'wf_workspace_parent_name_unique');
            $table->index(['workspace_id', 'parent_id']);
            $table->index(['workspace_id', 'owner_id']);
        });

        // Self-referencing FK must be added after table creation
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('workspace_files')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_files');
    }
};
