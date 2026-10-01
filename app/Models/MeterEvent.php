<?php

namespace App\Models;

use App\Enums\EventSeverity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meter_id
 * @property CarbonImmutable $occurred_at
 * @property string $type
 * @property EventSeverity $severity
 * @property string $message
 * @property bool $acknowledged
 * @property-read Meter $meter
 */
#[Fillable(['meter_id', 'occurred_at', 'type', 'severity', 'message', 'acknowledged'])]
class MeterEvent extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'severity' => EventSeverity::class,
            'acknowledged' => 'boolean',
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
