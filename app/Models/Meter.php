<?php

namespace App\Models;

use App\Enums\MeterStatus;
use App\Enums\MeterType;
use Carbon\CarbonImmutable;
use Database\Factories\MeterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $gateway_id
 * @property string $serial
 * @property string $name
 * @property string|null $account
 * @property MeterType $type
 * @property string $manufacturer
 * @property string $model
 * @property string $accuracy_class
 * @property string|null $ct_ratio
 * @property string|null $vt_ratio
 * @property string|null $tariff
 * @property bool $has_generation
 * @property MeterStatus $status
 * @property CarbonImmutable|null $installed_at
 * @property CarbonImmutable|null $last_reading_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Gateway $gateway
 */
#[Fillable(['gateway_id', 'serial', 'name', 'account', 'type', 'manufacturer', 'model', 'accuracy_class', 'ct_ratio', 'vt_ratio', 'tariff', 'has_generation', 'status', 'installed_at', 'last_reading_at'])]
class Meter extends Model
{
    /** @use HasFactory<MeterFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => MeterType::class,
            'status' => MeterStatus::class,
            'has_generation' => 'boolean',
            'installed_at' => 'date',
            'last_reading_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Gateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * @return HasMany<Reading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * @return HasMany<MeterEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MeterEvent::class);
    }
}
