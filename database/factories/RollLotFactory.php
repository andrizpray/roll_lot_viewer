<?php

namespace Database\Factories;

use App\Models\RollLot;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class RollLotFactory extends Factory
{
    protected $model = RollLot::class;

    public function definition(): array
    {
        return [
            'lot_id' => strtoupper($this->faker->bothify('LOT###??')),
            'item_id' => strtoupper($this->faker->bothify('ITEM###')),
            'weight' => $this->faker->randomFloat(2, 100, 5000),
            'papertype' => $this->faker->randomElement(['Paper Medium', 'PE B Kraft', 'BPTB B Kraft PE T/B', 'JR 1 B KRAFT']),
            'gramature' => $this->faker->randomElement(['MP150', 'BRP290', 'BPTB325', 'JRBK200', 'BK125']),
            'playbond' => $this->faker->optional(0.8)->randomElement(['E150', 'E200', 'E175']),
            'width' => $this->faker->randomElement(['900', '950', '1000', '1200', '3350']),
            'rew_id' => $this->faker->optional(0.7)->bothify('REW##'),
            'grade' => $this->faker->optional(0.7)->randomElement(['1', '2', '3', 'WIPB', '-']),
            'comments' => $this->faker->optional(0.3)->sentence(),
            'diameter' => $this->faker->optional(0.7)->randomFloat(2, 50, 200),
            'thickness' => $this->faker->optional(0.7)->bothify('##-##'),
            'description_raw' => 'Paper Medium MP150 E150 1000',
            'source_tr_date' => $this->faker->optional(0.7)->date(),
            'source_tr_time' => $this->faker->optional(0.7)->time(),
            'import_batch_id' => ImportBatch::factory(),
        ];
    }
}
