<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workspace files can carry user/agent notes separate from their storage
        // path and filename.
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
