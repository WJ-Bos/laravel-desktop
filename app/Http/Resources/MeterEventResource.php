<?php

namespace App\Http\Resources;

use App\Models\MeterEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeterEvent
 */
class MeterEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meter_id' => $this->meter_id,
            'meter_serial' => $this->getAttribute('meter_serial'),
            'meter_name' => $this->getAttribute('meter_name'),
            'headend_code' => $this->getAttribute('headend_code'),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'type' => $this->type,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'acknowledged' => $this->acknowledged,
        ];
    }
}
