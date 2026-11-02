<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Data sovereignty (pillar 11, docs/planning/data-sovereignty.md, Phase 0):
 * shape the schema for a lossless `.ocarchive` round trip.
 *
 * - `channel_members.id` becomes a UUID. It was the only app table keyed by
 *   an auto-increment integer (the rest are framework infrastructure or
 *   instance secrets that never enter an archive; see
 *   database/schema/README.md, "Archive invariants").
 * - `workspace_files.content_hash` (sha256 of the bytes) makes file blobs
 *   addressable by content for deduplicated, incremental archives and
 *   backups. Existing rows stay null until rewritten or hashed by the
 *   exporter; every new write sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rebuild rather than alter: SQLite cannot drop a primary-key column,
        // and a rebuild keeps both engines on one code path.
        Schema::create('channel_members_v2', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('channel_id');
            $table->string('user_id');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->integer('unread_count')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->timestamp('last_read_at')->nullable();
        });
        foreach (DB::table('channel_members')->orderBy('id')->cursor() as $row) {
            DB::table('channel_members_v2')->insert([
                'id' => (string) Str::uuid7(),
                'channel_id' => $row->channel_id,
                'user_id' => $row->user_id,
                'role' => $row->role,
                'unread_count' => $row->unread_count,
                'joined_at' => $row->joined_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'last_read_at' => $row->last_read_at,
            ]);
        }
        Schema::drop('channel_members');
        Schema::rename('channel_members_v2', 'channel_members');
        Schema::table('channel_members', function (Blueprint $table): void {
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['channel_id', 'user_id']);
        });

        Schema::table('workspace_files', function (Blueprint $table): void {
            $table->string('content_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_files', function (Blueprint $table): void {
            $table->dropIndex(['content_hash']);
            $table->dropColumn('content_hash');
        });
        // channel_members keeps UUID keys; integer keys carried no meaning.
    }
};
