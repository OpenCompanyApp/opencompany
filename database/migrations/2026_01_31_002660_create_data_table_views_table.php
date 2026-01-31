<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_table_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('table_id');
            $table->string('name');
            $table->string('type')->default('grid');
            $table->json('filters')->nullable();
            $table->json('sorts')->nullable();
            $table->json('hidden_columns')->nullable();
            $table->foreign('table_id')->references('id')->on('data_tables')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_table_views');
    }
};
