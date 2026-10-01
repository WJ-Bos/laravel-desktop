<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\HeadendFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A head-end system: polls its gateways and stores what they collect.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $site
 * @property string|null $city
 * @property string $protocol
 * @property string|null $ip_address
 * @property int $poll_interval_min
 * @property DeviceStatus $status
 * @property CarbonImmutable|null $last_poll_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['code', 'name', 'site', 'city', 'protocol', 'ip_address', 'poll_interval_min', 'status', 'last_poll_at'])]
class Headend extends Model
{
    /** @use HasFactory<HeadendFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Headend $headend) {
            $headend->code ??= 'HE-'.str_pad((string) (((int) static::max('id')) + 1), 2, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'poll_interval_min' => 'integer',
            'last_poll_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Gateway, $this>
     */
    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class);
    }

    /**
     * @return HasManyThrough<Meter, Gateway, $this>
     */
    public function meters(): HasManyThrough
    {
        return $this->hasManyThrough(Meter::class, Gateway::class);
    }
}
