<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ReadingResource;
use App\Models\Reading;
use App\Support\Grid\GridColumn;
use App\Support\Grid\GridQuery;

/**
 * Raw half-hourly interval data for the whole network (read-only).
 *
 * @extends GridController<Reading>
 */
class ReadingController extends GridController
{
    protected function grid(): GridQuery
    {
        $query = Reading::query()
            ->select('readings.*', 'meters.serial as meter_serial', 'meters.name as meter_name', 'gateways.serial as gateway_serial', 'headends.code as headend_code')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id');

        return GridQuery::for($query)
            ->columns([
                'read_at' => GridColumn::date('readings.read_at'),
                'meter_serial' => GridColumn::text('meters.serial'),
                'meter_name' => GridColumn::text('meters.name'),
                'meter_id' => GridColumn::number('readings.meter_id'),
                'gateway_serial' => GridColumn::text('gateways.serial'),
                'headend_code' => GridColumn::text('headends.code'),
                'headend_id' => GridColumn::number('gateways.headend_id'),
                'meter_type' => GridColumn::set('meters.type'),
                'kwh_import' => GridColumn::number('readings.kwh_import'),
                'kwh_export' => GridColumn::number('readings.kwh_export'),
                'kvarh_import' => GridColumn::number('readings.kvarh_import'),
                'kva_demand' => GridColumn::number('readings.kva_demand'),
                'kw' => GridColumn::number('((readings.kwh_import - readings.kwh_export) * 2)'),
                'power_factor' => GridColumn::number('readings.power_factor'),
                'voltage_l1' => GridColumn::number('readings.voltage_l1'),
                'voltage_l2' => GridColumn::number('readings.voltage_l2'),
                'voltage_l3' => GridColumn::number('readings.voltage_l3'),
                'current_l1' => GridColumn::number('readings.current_l1'),
                'current_l2' => GridColumn::number('readings.current_l2'),
                'current_l3' => GridColumn::number('readings.current_l3'),
                'quality' => GridColumn::set('readings.quality'),
            ])
            ->searchable(['meters.serial', 'meters.name', 'gateways.serial', 'headends.code'])
            ->defaultSort('read_at', 'desc');
    }

    protected function resource(): string
    {
        return ReadingResource::class;
    }

    protected function exportName(): string
    {
        return 'readings';
    }

    protected function exportColumns(): array
    {
        return [
            'Interval end' => fn (Reading $r) => $r->read_at->toDateTimeString(),
            'Meter' => 'meter_serial',
            'Meter name' => 'meter_name',
            'Gateway' => 'gateway_serial',
            'Headend' => 'headend_code',
            'kWh import' => 'kwh_import',
            'kWh export' => 'kwh_export',
            'kvarh import' => 'kvarh_import',
            'kvarh export' => 'kvarh_export',
            'kVA demand' => 'kva_demand',
            'PF' => 'power_factor',
            'V L1' => 'voltage_l1',
            'V L2' => 'voltage_l2',
            'V L3' => 'voltage_l3',
            'A L1' => 'current_l1',
            'A L2' => 'current_l2',
            'A L3' => 'current_l3',
            'Quality' => 'quality',
        ];
    }
}
