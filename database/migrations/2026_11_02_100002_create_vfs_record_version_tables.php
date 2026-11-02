<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Object history for workspace files and table rows, shaped like
 * document_versions so the VFS `.history/` views and a later "revert this
 * run" change set treat all three the same way.
 *
 * Each row is the state an object had BEFORE change number `version_number`
 * replaced it, attributed to the actor and run that made that change, with
 * the version tokens on both sides of the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_file_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('workspace_id');
            $table->string('workspace_file_id');
            $table->unsignedInteger('version_number');
            $table->string('name');
            // Text bytes as-is; non-UTF-8 bytes base64 encoded (see `encoding`).
            $table->longText('content');
            $table->string('encoding', 16)->default('utf8');
            $table->string('content_hash', 64);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('author_id')->nullable();
            $table->string('run_id')->nullable();
            $table->string('before_version', 64)->nullable();
            $table->string('after_version', 64)->nullable();
            $table->string('change_description')->nullable();
            $table->timestamps();

            $table->unique(['workspace_file_id', 'version_number'], 'workspace_file_versions_file_number_unique');
            $table->index(['workspace_id', 'run_id']);
            $table->foreign('workspace_file_id')->references('id')->on('workspace_files')->onDelete('cascade');
        });

        Schema::create('data_table_row_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('workspace_id');
            $table->uuid('table_id');
            $table->uuid('row_id');
            $table->unsignedInteger('version_number');
            // Row data keyed by column id, exactly as stored on the row.
            $table->json('data');
            $table->string('author_id')->nullable();
            $table->string('run_id')->nullable();
            $table->string('before_version', 64)->nullable();
            $table->string('after_version', 64)->nullable();
            $table->string('change_description')->nullable();
            $table->timestamps();

            $table->unique(['row_id', 'version_number'], 'data_table_row_versions_row_number_unique');
            $table->index(['workspace_id', 'run_id']);
            $table->foreign('row_id')->references('id')->on('data_table_rows')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_table_row_versions');
        Schema::dropIfExists('workspace_file_versions');
    }
};
