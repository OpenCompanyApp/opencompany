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
        Schema::create('data_table_columns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('table_id');
            $table->string('name');
            $table->string('type');
            $table->json('options')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('required')->default(false);
            $table->foreign('table_id')->references('id')->on('data_tables')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_table_columns');
    }
};
