<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->longText('content');
            $table->string('author_id');
            $table->string('parent_id')->nullable();
            $table->boolean('is_folder')->default(false);
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users');
        });

        // Add self-referential foreign key after table creation
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
