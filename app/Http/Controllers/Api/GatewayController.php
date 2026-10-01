<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SaveGatewayRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use App\Support\Grid\GridColumn;
use App\Support\Grid\GridQuery;
use App\Support\MeterStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @extends GridController<Gateway>
 */
class GatewayController extends GridController
{
    /**
     * @return Builder<Gateway>
     */
    private function baseQuery(): Builder
    {
        return Gateway::query()
            ->select('gateways.*', 'headends.code as headend_code', 'headends.name as headend_name')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id')
            ->withCount('meters');
    }

    protected function grid(): GridQuery
    {
        return GridQuery::for($this->baseQuery())
            ->columns([
                'serial' => GridColumn::text('gateways.serial'),
                'name' => GridColumn::text('gateways.name'),
                'headend_code' => GridColumn::text('headends.code'),
                'headend_name' => GridColumn::text('headends.name'),
                'headend_id' => GridColumn::number('gateways.headend_id'),
                'comms' => GridColumn::text('gateways.comms'),
                'firmware' => GridColumn::text('gateways.firmware'),
                'signal_dbm' => GridColumn::number('gateways.signal_dbm'),
                'status' => GridColumn::set('gateways.status'),
                'meters_count' => GridColumn::number('(select count(*) from meters where meters.gateway_id = gateways.id)'),
                'last_seen_at' => GridColumn::date('gateways.last_seen_at'),
            ])
            ->searchable(['gateways.serial', 'gateways.name', 'headends.code', 'headends.name'])
            ->defaultSort('serial');
    }

    protected function resource(): string
    {
        return GatewayResource::class;
    }

    protected function exportName(): string
    {
        return 'gateways';
    }

    protected function exportColumns(): array
    {
        return [
            'Serial' => 'serial',
            'Name' => 'name',
            'Headend' => 'headend_code',
            'Comms' => 'comms',
            'Firmware' => 'firmware',
            'Signal (dBm)' => 'signal_dbm',
            'Status' => 'status',
            'Meters' => 'meters_count',
            'Last seen' => 'last_seen_at',
        ];
    }

    public function show(Gateway $gateway): GatewayResource
    {
        $gateway = $this->baseQuery()->findOrFail($gateway->id);
        $gateway->load(['meters' => fn ($q) => $q->orderBy('serial')]);
        MeterStats::attach($gateway->meters);

        return GatewayResource::make($gateway);
    }

    public function store(SaveGatewayRequest $request): JsonResponse
    {
        $gateway = Gateway::create($request->validated());

        return GatewayResource::make($this->baseQuery()->findOrFail($gateway->id))->response()->setStatusCode(201);
    }

    public function update(SaveGatewayRequest $request, Gateway $gateway): GatewayResource
    {
        $gateway->update($request->validated());

        return GatewayResource::make($this->baseQuery()->findOrFail($gateway->id));
    }

    public function destroy(Gateway $gateway): Response|JsonResponse
    {
        if ($gateway->meters()->exists()) {
            return response()->json([
                'message' => "{$gateway->serial} still has meters assigned. Reassign them to another gateway first.",
            ], 409);
        }

        $gateway->delete();

        return response()->noContent();
    }

    public function duplicate(Gateway $gateway): JsonResponse
    {
        $copy = $gateway->replicate(['last_seen_at']);
        $copy->serial = substr($gateway->serial, 0, 15).'-C'.random_int(10, 99);
        $copy->name = $gateway->name.' (copy)';
        $copy->save();

        return GatewayResource::make($this->baseQuery()->findOrFail($copy->id))->response()->setStatusCode(201);
    }
}
