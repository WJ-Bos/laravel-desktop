<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One half-hourly interval from a meter's load profile. `read_at` marks the
 * end of the interval; energy values are the consumption within it.
 *
 * @property int $id
 * @property int $meter_id
 * @property CarbonImmutable $read_at
 * @property string $kwh_import
 * @property string $kwh_export
 * @property string $kvarh_import
 * @property string $kvarh_export
 * @property string $kva_demand
 * @property string|null $power_factor
 * @property string|null $voltage_l1
 * @property string|null $voltage_l2
 * @property string|null $voltage_l3
 * @property string|null $current_l1
 * @property string|null $current_l2
 * @property string|null $current_l3
 * @property string $quality
 * @property-read Meter $meter
 */
class Reading extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Meter, $this>
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
