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
        Schema::create('roll_lot_histories', function (Blueprint $table) {
            $table->id();
            $table->string('lot_id')->index();
            $table->string('item_id')->index();
            $table->decimal('weight', 15, 2);
            $table->string('papertype');
            $table->string('gramature');
            $table->string('playbond')->nullable();
            $table->string('width');
            $table->string('rew_id')->nullable();
            $table->string('grade')->nullable();
            $table->text('comments')->nullable();
            $table->decimal('diameter', 10, 2)->nullable();
            $table->string('thickness')->nullable();
            $table->text('description_raw');
            $table->date('source_tr_date')->nullable();
            $table->time('source_tr_time')->nullable();
            $table->foreignId('import_batch_id')->constrained()->onDelete('cascade');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // Additional indexes
        Schema::table('roll_lot_histories', function (Blueprint $table) {
            $table->index('papertype');
            $table->index('grade');
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roll_lot_histories');
    }
};
