<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // behavior_mode lets operators tune an agent's autonomy without
        // changing its identity prompt or provider brain.
        Schema::table('users', function (Blueprint $table) {
            $table->string('behavior_mode')->nullable()->after('current_task');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('behavior_mode');
        });
    }
};
