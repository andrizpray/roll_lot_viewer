<?php

namespace App\Console\Commands;

use App\Models\ImportError;
use App\Models\ImportJob;
use Illuminate\Console\Command;

class ImportStatusCommand extends Command
{
    protected $signature = 'import:status 
                            {id? : Import job ID (default: show all recent)}';

    protected $description = 'Cek status import job';

    public function handle(): int
    {
        $id = $this->argument('id');

        if ($id) {
            $job = ImportJob::find($id);

            if (!$job) {
                $this->error("Job ID $id tidak ditemukan.");
                return Command::FAILURE;
            }

            $this->info("Job #{$job->id}: {$job->filename}");
            $this->table(
                ['Type', 'Status', 'Total Rows', 'Success', 'Failed', 'Date'],
                [[
                    $job->type,
                    $job->status,
                    $job->total_rows,
                    $job->success_count,
                    $job->failed_count,
                    $job->created_at->format('Y-m-d H:i'),
                ]]
            );

            // Show errors if any
            $errors = ImportError::where('import_batch_id', $id)->get();
            if ($errors->isNotEmpty()) {
                $this->newLine();
                $this->warn('Errors:');
                $this->table(
                    ['Row', 'LotID', 'Reason'],
                    $errors->map(fn($e) => [
                        $e->row_number,
                        $e->lot_id ?? '-',
                        $e->reason,
                    ])->toArray()
                );
            }

            return Command::SUCCESS;
        }

        // Show recent jobs
        $jobs = ImportJob::orderBy('created_at', 'desc')->limit(10)->get();

        if ($jobs->isEmpty()) {
            $this->info('Belum ada import job.');
            return Command::SUCCESS;
        }

        $this->info('Recent import jobs:');
        $this->table(
            ['ID', 'Filename', 'Type', 'Status', 'Total', 'Success', 'Failed', 'Date'],
            $jobs->map(fn($j) => [
                $j->id,
                $j->filename,
                $j->type,
                $j->status,
                $j->total_rows,
                $j->success_count,
                $j->failed_count,
                $j->created_at->format('Y-m-d H:i'),
            ])->toArray()
        );

        return Command::SUCCESS;
    }
}
