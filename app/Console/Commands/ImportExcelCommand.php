<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Jobs\ProcessExcelImport;
use Illuminate\Console\Command;

class ImportExcelCommand extends Command
{
    protected $signature = 'import:excel 
                            {path : Path to Excel file}
                            {--sync : Run synchronously (not queued)}';

    protected $description = 'Import Excel file ke database Roll Lot Viewer';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: $path");
            return Command::FAILURE;
        }

        $filename = basename($path);

        $this->info("Memulai import: $filename");

        // Create import batch
        $batch = ImportBatch::create([
            'filename' => $filename,
            'status' => 'processing',
        ]);

        if ($this->option('sync')) {
            $this->info('Running synchronously...');

            $job = new ProcessExcelImport($batch->id, $path);
            $job->handle();

            $batch->refresh();

            $this->table(
                ['Status', 'Total', 'Success', 'Failed'],
                [[
                    $batch->status,
                    $batch->total_rows,
                    $batch->success_count,
                    $batch->failed_count,
                ]]
            );

            $this->info('Import selesai.');
        } else {
            ProcessExcelImport::dispatch($batch->id, $path);

            $this->info("Job diproses di background. Batch ID: {$batch->id}");
            $this->info("Cek status: php artisan import:status {$batch->id}");
        }

        return Command::SUCCESS;
    }
}
