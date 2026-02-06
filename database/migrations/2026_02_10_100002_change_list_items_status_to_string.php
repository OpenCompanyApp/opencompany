<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_items', function (Blueprint $table) {
            $table->string('status', 50)->default('backlog')->change();
        });
    }

    public function down(): void
    {
        Schema::table('list_items', function (Blueprint $table) {
            $table->enum('status', ['backlog', 'in_progress', 'done'])->default('backlog')->change();
        });
    }
};
