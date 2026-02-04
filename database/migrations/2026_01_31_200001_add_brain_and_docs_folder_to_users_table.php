<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('brain')->nullable()->after('agent_type'); // e.g., 'glm:glm-4.7'
            $table->string('docs_folder_id')->nullable()->after('brain'); // Reference to agent's docs folder
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['brain', 'docs_folder_id']);
        });
    }
};
