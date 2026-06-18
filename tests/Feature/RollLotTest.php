<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\RollLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_roll_lots_with_advanced_filter(): void
    {
        $batch = ImportBatch::create(['filename' => 'batch.xlsx', 'status' => 'success']);
        
        RollLot::create([
            'lot_id' => 'LOT001',
            'item_id' => 'ITEM001',
            'weight' => 1000,
            'papertype' => 'Paper Medium',
            'gramature' => 'MP150',
            'playbond' => 'E150',
            'width' => '1000',
            'source_tr_date' => '2026-06-01',
            'description_raw' => 'Paper Medium MP150 E150 1000',
            'import_batch_id' => $batch->id,
        ]);
        RollLot::create([
            'lot_id' => 'LOT002',
            'item_id' => 'ITEM002',
            'weight' => 1500,
            'papertype' => 'PE B Kraft',
            'gramature' => 'BRP290',
            'playbond' => 'E150',
            'width' => '950',
            'source_tr_date' => '2026-06-15',
            'description_raw' => 'PE B Kraft BRP290 E150 950',
            'import_batch_id' => $batch->id,
        ]);

        $response = $this->getJson('/api/roll-lots?mode=advanced&papertype=Paper');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('Paper Medium', $data['data'][0]['papertype']);
    }

    public function test_batch_search_by_lot_ids(): void
    {
        $batch = ImportBatch::create(['filename' => 'batch.xlsx', 'status' => 'success']);
        
        RollLot::create(['lot_id' => 'LOT001', 'item_id' => 'ITEM001', 'weight' => 1000, 'papertype' => 'Paper', 'gramature' => 'MP150', 'width' => '1000', 'description_raw' => 'Paper MP150 E150 1000', 'import_batch_id' => $batch->id]);
        RollLot::create(['lot_id' => 'LOT002', 'item_id' => 'ITEM002', 'weight' => 1500, 'papertype' => 'PE', 'gramature' => 'BRP290', 'width' => '950', 'description_raw' => 'PE BRP290 E150 950', 'import_batch_id' => $batch->id]);
        RollLot::create(['lot_id' => 'LOT003', 'item_id' => 'ITEM003', 'weight' => 2000, 'papertype' => 'BPTB', 'gramature' => 'BPTB325', 'width' => '900', 'description_raw' => 'BPTB BPTB325 E150 900', 'import_batch_id' => $batch->id]);

        $response = $this->getJson('/api/roll-lots?mode=batch&lot_ids=LOT001,LOT002');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta',
        ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.found_count'));
        $this->assertEquals(0, $response->json('meta.not_found_count'));
    }

    public function test_batch_search_returns_not_found_lot_ids(): void
    {
        $batch = ImportBatch::create(['filename' => 'batch.xlsx', 'status' => 'success']);
        
        RollLot::create(['lot_id' => 'LOT001', 'item_id' => 'ITEM001', 'weight' => 1000, 'papertype' => 'Paper', 'gramature' => 'MP150', 'width' => '1000', 'description_raw' => 'Paper MP150 E150 1000', 'import_batch_id' => $batch->id]);

        $response = $this->getJson('/api/roll-lots?mode=batch&lot_ids=LOT001,LOT002,LOT003');

        $response->assertStatus(200);

        $this->assertEquals(3, $response->json('meta.requested_count'));
        $this->assertEquals(1, $response->json('meta.found_count'));
        $this->assertEquals(2, $response->json('meta.not_found_count'));
        $this->assertEquals(['LOT002', 'LOT003'], $response->json('meta.not_found'));
    }

    public function test_batch_mode_ignores_advanced_filters(): void
    {
        $batch = ImportBatch::create(['filename' => 'batch.xlsx', 'status' => 'success']);
        
        RollLot::create([
            'lot_id' => 'LOT001',
            'item_id' => 'ITEM001',
            'weight' => 1000,
            'papertype' => 'Paper Medium',
            'gramature' => 'MP150',
            'playbond' => 'E150',
            'width' => '1000',
            'grade' => '1',
            'description_raw' => 'Paper Medium MP150 E150 1000',
            'import_batch_id' => $batch->id,
        ]);
        RollLot::create([
            'lot_id' => 'LOT002',
            'item_id' => 'ITEM002',
            'weight' => 1500,
            'papertype' => 'PE B Kraft',
            'gramature' => 'BRP290',
            'playbond' => 'E150',
            'width' => '950',
            'grade' => '2',
            'description_raw' => 'PE B Kraft BRP290 E150 950',
            'import_batch_id' => $batch->id,
        ]);

        // Even though grade filter is provided, batch mode should ignore it
        $response = $this->getJson('/api/roll-lots?mode=batch&lot_ids=LOT001&grade=2');

        $response->assertStatus(200);

        // Should return only LOT001, ignoring grade=2 filter
        $this->assertEquals('LOT001', $response->json('data.0.lot_id'));
        $this->assertEquals('1', $response->json('data.0.grade'));
    }

    public function test_advanced_filter_combinations(): void
    {
        $batch = ImportBatch::create(['filename' => 'batch.xlsx', 'status' => 'success']);
        
        RollLot::create([
            'lot_id' => 'LOT001',
            'item_id' => 'ITEM001',
            'weight' => 1000,
            'papertype' => 'Paper Medium',
            'gramature' => 'MP150',
            'playbond' => 'E150',
            'width' => '1000',
            'source_tr_date' => '2026-06-01',
            'description_raw' => 'Paper Medium MP150 E150 1000',
            'import_batch_id' => $batch->id,
        ]);

        $response = $this->getJson('/api/roll-lots?mode=advanced&item_id=ITEM001&gramature=MP150&date_from=2026-06-01');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['total']);
    }

    public function test_get_single_roll_lot(): void
    {
        $batch = ImportBatch::create(['filename' => 'batch.xlsx', 'status' => 'success']);
        $rollLot = RollLot::create([
            'lot_id' => 'LOT001',
            'item_id' => 'ITEM001',
            'weight' => 1000,
            'papertype' => 'Paper',
            'gramature' => 'MP150',
            'width' => '1000',
            'description_raw' => 'Paper MP150 E150 1000',
            'import_batch_id' => $batch->id,
        ]);

        $response = $this->getJson("/api/roll-lots/{$rollLot->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'lot_id' => 'LOT001',
        ]);
    }
}
