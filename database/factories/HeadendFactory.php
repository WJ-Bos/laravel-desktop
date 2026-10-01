<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Headend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Headend>
 */
class HeadendFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('HE-###'),
            'name' => fake()->city().' Head-end',
            'site' => fake()->streetName(),
            'city' => fake()->city(),
            'protocol' => 'DLMS/COSEM',
            'ip_address' => fake()->localIpv4(),
            'poll_interval_min' => 30,
            'status' => DeviceStatus::Online,
            'last_poll_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ];
    }
}
