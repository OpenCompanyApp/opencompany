<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->string('workspace_disk_id')->nullable()->after('storage_disk');
            $table->foreign('workspace_disk_id')->references('id')->on('workspace_disks')->nullOnDelete();
            $table->index('workspace_disk_id');
        });

        // Backfill: link existing files to their workspace's default disk
        $defaultDisks = DB::table('workspace_disks')->where('is_default', true)->get();

        foreach ($defaultDisks as $disk) {
            DB::table('workspace_files')
                ->where('workspace_id', $disk->workspace_id)
                ->whereNull('workspace_disk_id')
                ->update(['workspace_disk_id' => $disk->id]);
        }
    }

    public function down(): void
    {
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->dropForeign(['workspace_disk_id']);
            $table->dropColumn('workspace_disk_id');
        });
    }
};
