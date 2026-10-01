<?php

namespace App\Http\Resources;

use App\Models\Headend;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Headend
 */
class HeadendResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $int = fn (string $key) => $this->getAttribute($key) !== null ? (int) $this->getAttribute($key) : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'site' => $this->site,
            'city' => $this->city,
            'protocol' => $this->protocol,
            'ip_address' => $this->ip_address,
            'poll_interval_min' => $this->poll_interval_min,
            'status' => $this->status->value,
            'last_poll_at' => $this->last_poll_at?->toIso8601String(),
            'gateways_count' => $int('gateways_count'),
            'gateways_online' => $int('gateways_online'),
            'meters_count' => $int('meters_count'),
            'meters_reporting' => $int('meters_reporting'),
            'gateways' => GatewayResource::collection($this->whenLoaded('gateways')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
