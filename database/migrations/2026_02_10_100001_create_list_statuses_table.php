<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_statuses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color');
            $table->string('icon');
            $table->boolean('is_done')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // Seed default statuses
        $now = now();
        DB::table('list_statuses')->insert([
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Backlog',
                'slug' => 'backlog',
                'color' => 'neutral',
                'icon' => 'ph:circle-dashed',
                'is_done' => false,
                'is_default' => true,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'In Progress',
                'slug' => 'in_progress',
                'color' => 'blue',
                'icon' => 'ph:circle-half',
                'is_done' => false,
                'is_default' => false,
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Done',
                'slug' => 'done',
                'color' => 'green',
                'icon' => 'ph:check-circle',
                'is_done' => true,
                'is_default' => false,
                'position' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('list_statuses');
    }
};
