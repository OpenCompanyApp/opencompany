<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_disks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->string('driver'); // local, s3, sftp
            $table->text('config')->nullable(); // encrypted:array
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->unique(['workspace_id', 'name']);
            $table->index(['workspace_id', 'is_default']);
        });

        // Seed a default "Local" disk for every existing workspace
        $workspaces = DB::table('workspaces')->pluck('id');
        $now = now();

        foreach ($workspaces as $workspaceId) {
            DB::table('workspace_disks')->insert([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspaceId,
                'name' => 'Local',
                'driver' => 'local',
                'config' => null,
                'is_default' => true,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_disks');
    }
};
