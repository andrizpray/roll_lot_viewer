<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_excel_file_creates_import_batch_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $file = UploadedFile::fake()->create('test.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'batch_id',
        ]);

        $batch = ImportBatch::first();
        $this->assertNotNull($batch);
        $this->assertEquals('processing', $batch->status);
        $this->assertEquals('test.xlsx', $batch->filename);

        Queue::assertPushed(\App\Jobs\ProcessExcelImport::class);
    }

    public function test_upload_rejects_non_excel_files(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 1024, 'text/plain');

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_list_import_batches_with_pagination(): void
    {
        // Create 25 import batches manually
        for ($i = 0; $i < 25; $i++) {
            ImportBatch::create([
                'filename' => 'test_' . $i . '.xlsx',
                'total_rows' => rand(100, 1000),
                'success_count' => rand(80, 900),
                'failed_count' => rand(0, 100),
                'status' => 'success',
            ]);
        }

        $response = $this->getJson('/api/imports');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'links',
        ]);
        $this->assertArrayHasKey('total', $response->json());
        $this->assertCount(20, $response->json('data')); // default per_page = 20
    }

    public function test_get_single_import_batch_details(): void
    {
        $batch = ImportBatch::create(['filename' => 'test.xlsx', 'status' => 'success', 'total_rows' => 100, 'success_count' => 95, 'failed_count' => 5]);

        $response = $this->getJson("/api/imports/{$batch->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $batch->id,
            'filename' => $batch->filename,
        ]);
    }

    public function test_get_batch_status(): void
    {
        $batch = ImportBatch::create([
            'filename' => 'test.xlsx',
            'status' => 'success',
            'total_rows' => 100,
            'success_count' => 95,
            'failed_count' => 5,
        ]);

        $response = $this->getJson("/api/imports/{$batch->id}/status");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $batch->id,
            'status' => 'success',
            'total_rows' => 100,
            'success_count' => 95,
            'failed_count' => 5,
        ]);
    }
}
