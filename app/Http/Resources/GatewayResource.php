<?php

namespace App\Http\Resources;

use App\Models\Gateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Gateway
 */
class GatewayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metersCount = $this->getAttribute('meters_count');

        return [
            'id' => $this->id,
            'headend_id' => $this->headend_id,
            'headend_code' => $this->getAttribute('headend_code') ?? $this->whenLoaded('headend', fn () => $this->headend->code),
            'headend_name' => $this->getAttribute('headend_name') ?? $this->whenLoaded('headend', fn () => $this->headend->name),
            'serial' => $this->serial,
            'name' => $this->name,
            'comms' => $this->comms,
            'firmware' => $this->firmware,
            'signal_dbm' => $this->signal_dbm,
            'status' => $this->status->value,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'meters_count' => $metersCount !== null ? (int) $metersCount : null,
            'meters' => MeterResource::collection($this->whenLoaded('meters')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
