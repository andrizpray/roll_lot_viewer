<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop legacy FK constraints — import_batch_id references import_jobs, not import_batches
        DB::statement('ALTER TABLE roll_lots DROP CONSTRAINT IF EXISTS roll_lots_import_batch_id_foreign');
        DB::statement('ALTER TABLE paper_sheets DROP CONSTRAINT IF EXISTS paper_sheets_import_batch_id_foreign');
        DB::statement('ALTER TABLE roll_lot_histories DROP CONSTRAINT IF EXISTS roll_lot_histories_import_batch_id_foreign');
        DB::statement('ALTER TABLE import_errors DROP CONSTRAINT IF EXISTS import_errors_import_batch_id_foreign');
    }

    public function down(): void
    {
        // No-op: FK was a design mistake, don't re-add
    }
};
