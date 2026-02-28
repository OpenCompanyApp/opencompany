<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('content_format', 10)->default('markdown')->after('content');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->string('content_format', 10)->default('markdown')->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('content_format');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn('content_format');
        });
    }
};
