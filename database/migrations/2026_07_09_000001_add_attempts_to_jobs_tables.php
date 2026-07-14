<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('failed_count');
        });

        Schema::table('export_jobs', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });

        Schema::table('export_jobs', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
