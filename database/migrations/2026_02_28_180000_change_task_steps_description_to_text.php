<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agent progress steps can include detailed logs, so the description
        // needs text capacity rather than varchar capacity.
        Schema::table('task_steps', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_steps', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
