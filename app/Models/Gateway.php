<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GatewayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A data concentrator that reads its meters and reports to a headend.
 *
 * @property int $id
 * @property int $headend_id
 * @property string $serial
 * @property string $name
 * @property string $comms
 * @property string|null $firmware
 * @property int|null $signal_dbm
 * @property DeviceStatus $status
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Headend $headend
 */
#[Fillable(['headend_id', 'serial', 'name', 'comms', 'firmware', 'signal_dbm', 'status', 'last_seen_at'])]
class Gateway extends Model
{
    /** @use HasFactory<GatewayFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'signal_dbm' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Headend, $this>
     */
    public function headend(): BelongsTo
    {
        return $this->belongsTo(Headend::class);
    }

    /**
     * @return HasMany<Meter, $this>
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}
