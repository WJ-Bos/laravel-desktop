<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MeterEventResource;
use App\Models\MeterEvent;
use App\Support\Grid\GridColumn;
use App\Support\Grid\GridQuery;
use Illuminate\Http\Request;

/**
 * Meter events and alarms (power failures, tamper, sags, comms loss…).
 *
 * @extends GridController<MeterEvent>
 */
class MeterEventController extends GridController
{
    protected function grid(): GridQuery
    {
        $query = MeterEvent::query()
            ->select('meter_events.*', 'meters.serial as meter_serial', 'meters.name as meter_name', 'headends.code as headend_code')
            ->join('meters', 'meters.id', '=', 'meter_events.meter_id')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id');

        return GridQuery::for($query)
            ->columns([
                'occurred_at' => GridColumn::date('meter_events.occurred_at'),
                'meter_serial' => GridColumn::text('meters.serial'),
                'meter_name' => GridColumn::text('meters.name'),
                'meter_id' => GridColumn::number('meter_events.meter_id'),
                'headend_code' => GridColumn::text('headends.code'),
                'headend_id' => GridColumn::number('gateways.headend_id'),
                'type' => GridColumn::text('meter_events.type'),
                'severity' => GridColumn::set('meter_events.severity'),
                'message' => GridColumn::text('meter_events.message'),
                'acknowledged' => GridColumn::boolean('meter_events.acknowledged'),
            ])
            ->searchable(['meters.serial', 'meters.name', 'meter_events.type', 'meter_events.message', 'headends.code'])
            ->defaultSort('occurred_at', 'desc');
    }

    protected function resource(): string
    {
        return MeterEventResource::class;
    }

    protected function exportName(): string
    {
        return 'events';
    }

    protected function exportColumns(): array
    {
        return [
            'Occurred' => fn (MeterEvent $e) => $e->occurred_at->toDateTimeString(),
            'Meter' => 'meter_serial',
            'Meter name' => 'meter_name',
            'Headend' => 'headend_code',
            'Type' => 'type',
            'Severity' => 'severity',
            'Message' => 'message',
            'Acknowledged' => 'acknowledged',
        ];
    }

    public function acknowledge(Request $request, MeterEvent $event): MeterEventResource
    {
        $event->update(['acknowledged' => $request->boolean('acknowledged', true)]);

        return MeterEventResource::make($event);
    }
}
