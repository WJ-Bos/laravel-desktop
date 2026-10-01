<?php

namespace Database\Factories;

use App\Enums\MeterStatus;
use App\Enums\MeterType;
use App\Models\Gateway;
use App\Models\Meter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway_id' => Gateway::factory(),
            'serial' => fake()->unique()->numerify('2241######'),
            'name' => fake()->company().' – Main DB',
            'account' => fake()->numerify('ACC-#####'),
            'type' => MeterType::ThreePhase,
            'manufacturer' => 'PMT',
            'model' => 'MOL4000',
            'accuracy_class' => '1',
            'ct_ratio' => null,
            'vt_ratio' => null,
            'tariff' => 'Businessrate',
            'has_generation' => false,
            'status' => MeterStatus::Active,
            'installed_at' => now()->subYears(2)->toDateString(),
            'last_reading_at' => now()->subMinutes(30),
        ];
    }
}
