<?php

namespace App\Http\Resources;

use App\Models\Reading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reading
 */
class ReadingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $num = fn (?string $v, int $digits = 3) => $v === null ? null : round((float) $v, $digits);

        return [
            'id' => $this->id,
            'meter_id' => $this->meter_id,
            'meter_serial' => $this->getAttribute('meter_serial'),
            'meter_name' => $this->getAttribute('meter_name'),
            'gateway_serial' => $this->getAttribute('gateway_serial'),
            'headend_code' => $this->getAttribute('headend_code'),
            'read_at' => $this->read_at->toIso8601String(),
            'kwh_import' => $num($this->kwh_import),
            'kwh_export' => $num($this->kwh_export),
            'kvarh_import' => $num($this->kvarh_import),
            'kvarh_export' => $num($this->kvarh_export),
            'kva_demand' => $num($this->kva_demand),
            'kw' => round(((float) $this->kwh_import - (float) $this->kwh_export) * 2, 3),
            'power_factor' => $num($this->power_factor),
            'voltage_l1' => $num($this->voltage_l1, 2),
            'voltage_l2' => $num($this->voltage_l2, 2),
            'voltage_l3' => $num($this->voltage_l3, 2),
            'current_l1' => $num($this->current_l1, 2),
            'current_l2' => $num($this->current_l2, 2),
            'current_l3' => $num($this->current_l3, 2),
            'quality' => $this->quality,
        ];
    }
}
