<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'filename' => $this->faker->word() . '.xlsx',
            'total_rows' => $this->faker->numberBetween(100, 1000),
            'success_count' => $this->faker->numberBetween(80, 900),
            'failed_count' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['processing', 'success', 'failed']),
        ];
    }
}
