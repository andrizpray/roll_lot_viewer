<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Skema untuk data "Mutasi Stock Sheet" — terpisah dari roll_lots karena
     * struktur kolom sumbernya berbeda (tidak ada RewID/Grade/Diameter/Thickness,
     * tapi ada Content Pack & Content Pallet).
     */
    public function up(): void
    {
        Schema::create('paper_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('lot_id')->index();
            $table->string('item_id')->index();
            $table->decimal('weight', 15, 2);
            $table->string('papertype')->index();
            $table->string('gramature');
            $table->string('dimension');
            $table->integer('content_pack')->nullable();
            $table->integer('content_pallet')->nullable();
            $table->text('description_raw')->nullable();
            $table->date('source_tr_date')->nullable();
            $table->time('source_tr_time')->nullable();
            $table->foreignId('import_batch_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_sheets');
    }
};
