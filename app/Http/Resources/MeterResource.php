<?php

namespace App\Http\Resources;

use App\Models\Meter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meter
 */
class MeterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kwh = $this->getAttribute('kwh_30d');
        $demand = $this->getAttribute('max_kva_30d');

        return [
            'id' => $this->id,
            'gateway_id' => $this->gateway_id,
            'gateway_serial' => $this->getAttribute('gateway_serial') ?? $this->whenLoaded('gateway', fn () => $this->gateway->serial),
            'headend_id' => $this->getAttribute('headend_id') ?? $this->whenLoaded('gateway', fn () => $this->gateway->headend_id),
            'headend_code' => $this->getAttribute('headend_code') ?? $this->whenLoaded('gateway', fn () => $this->gateway->relationLoaded('headend') ? $this->gateway->headend->code : null),
            'serial' => $this->serial,
            'name' => $this->name,
            'account' => $this->account,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'phases' => $this->type->phases(),
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'accuracy_class' => $this->accuracy_class,
            'ct_ratio' => $this->ct_ratio,
            'vt_ratio' => $this->vt_ratio,
            'tariff' => $this->tariff,
            'has_generation' => $this->has_generation,
            'status' => $this->status->value,
            'installed_at' => $this->installed_at?->toDateString(),
            'last_reading_at' => $this->last_reading_at?->toIso8601String(),
            'kwh_30d' => $kwh !== null ? round((float) $kwh, 1) : null,
            'max_kva_30d' => $demand !== null ? round((float) $demand, 2) : null,
            'kwh_today' => $this->getAttribute('kwh_today') !== null ? round((float) $this->getAttribute('kwh_today'), 1) : null,
            'kw_now' => $this->getAttribute('kw_now') !== null ? round((float) $this->getAttribute('kw_now'), 2) : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
