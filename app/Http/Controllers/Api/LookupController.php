<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceStatus;
use App\Enums\EventSeverity;
use App\Enums\MeterStatus;
use App\Enums\MeterType;
use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Headend;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;

/**
 * Option lists for dropdowns and toolbar filters.
 */
class LookupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'headends' => Headend::query()->orderBy('code')->get(['id', 'code', 'name']),
            'gateways' => Gateway::query()
                ->join('headends', 'headends.id', '=', 'gateways.headend_id')
                ->orderBy('gateways.serial')
                ->get(['gateways.id', 'gateways.serial', 'gateways.name', 'gateways.headend_id', 'headends.code as headend_code']),
            'meterTypes' => array_map(fn (MeterType $t) => ['value' => $t->value, 'label' => $t->label()], MeterType::cases()),
            'meterStatuses' => array_map(fn (MeterStatus $s) => $s->value, MeterStatus::cases()),
            'deviceStatuses' => array_map(fn (DeviceStatus $s) => $s->value, DeviceStatus::cases()),
            'severities' => array_map(fn (EventSeverity $s) => $s->value, EventSeverity::cases()),
            'manufacturers' => Meter::query()->distinct()->orderBy('manufacturer')->pluck('manufacturer'),
            'tariffs' => Meter::query()->whereNotNull('tariff')->distinct()->orderBy('tariff')->pluck('tariff'),
            'comms' => ['HD-PLC/RF', 'LTE', 'RS-485', 'Ethernet'],
        ]);
    }
}
