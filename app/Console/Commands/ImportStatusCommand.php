<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use Illuminate\Console\Command;

class ImportStatusCommand extends Command
{
    protected $signature = 'import:status 
                            {id? : Import batch ID (default: show all recent)}';

    protected $description = 'Cek status import batch';

    public function handle(): int
    {
        $id = $this->argument('id');

        if ($id) {
            $batch = ImportBatch::with('errors')->find($id);

            if (!$batch) {
                $this->error("Batch ID $id tidak ditemukan.");
                return Command::FAILURE;
            }

            $this->info("Batch #{$batch->id}: {$batch->filename}");
            $this->table(
                ['Status', 'Total Rows', 'Success', 'Failed', 'Date'],
                [[
                    $batch->status,
                    $batch->total_rows,
                    $batch->success_count,
                    $batch->failed_count,
                    $batch->created_at->format('Y-m-d H:i'),
                ]]
            );

            if ($batch->errors->isNotEmpty()) {
                $this->newLine();
                $this->warn('Errors:');
                $this->table(
                    ['Row', 'LotID', 'Reason'],
                    $batch->errors->map(fn($e) => [
                        $e->row_number,
                        $e->lot_id ?? '-',
                        $e->reason,
                    ])->toArray()
                );
            }

            return Command::SUCCESS;
        }

        // Show recent batches
        $batches = ImportBatch::orderBy('created_at', 'desc')->limit(10)->get();

        if ($batches->isEmpty()) {
            $this->info('Belum ada import batch.');
            return Command::SUCCESS;
        }

        $this->info('Recent import batches:');
        $this->table(
            ['ID', 'Filename', 'Status', 'Total', 'Success', 'Failed', 'Date'],
            $batches->map(fn($b) => [
                $b->id,
                $b->filename,
                $b->status,
                $b->total_rows,
                $b->success_count,
                $b->failed_count,
                $b->created_at->format('Y-m-d H:i'),
            ])->toArray()
        );

        return Command::SUCCESS;
    }
}
