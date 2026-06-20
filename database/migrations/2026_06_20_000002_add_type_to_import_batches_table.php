<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom 'type' menyimpan hasil deteksi otomatis jenis file yang diupload:
     * 'roll' (Mutasi Harian Roll PM1/PM2) atau 'sheet' (Mutasi Stock Sheet).
     */
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('type')->default('roll')->after('filename')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
