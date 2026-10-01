<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Gateway;
use App\Models\Headend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gateway>
 */
class GatewayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'headend_id' => Headend::factory(),
            'serial' => fake()->unique()->numerify('GW-#####'),
            'name' => fake()->streetName().' DB',
            'comms' => fake()->randomElement(['HD-PLC/RF', 'LTE', 'RS-485']),
            'firmware' => fake()->randomElement(['4.2.1', '4.3.0', '4.3.2']),
            'signal_dbm' => fake()->numberBetween(-95, -55),
            'status' => DeviceStatus::Online,
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ];
    }
}
