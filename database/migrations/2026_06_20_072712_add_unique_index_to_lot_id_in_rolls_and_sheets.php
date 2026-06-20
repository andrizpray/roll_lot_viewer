<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roll_lots', function (Blueprint $table) {
            $table->unique('lot_id', 'roll_lots_lot_id_unique');
        });

        Schema::table('paper_sheets', function (Blueprint $table) {
            $table->unique('lot_id', 'paper_sheets_lot_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('roll_lots', function (Blueprint $table) {
            $table->dropUnique('roll_lots_lot_id_unique');
        });

        Schema::table('paper_sheets', function (Blueprint $table) {
            $table->dropUnique('paper_sheets_lot_id_unique');
        });
    }
};
