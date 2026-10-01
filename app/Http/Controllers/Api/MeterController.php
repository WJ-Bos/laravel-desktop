<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\DateRangeRequest;
use App\Http\Requests\SaveMeterRequest;
use App\Http\Resources\MeterResource;
use App\Models\Meter;
use App\Services\MeterProfileService;
use App\Support\Grid\GridColumn;
use App\Support\Grid\GridQuery;
use App\Support\MeterStats;
use App\Support\SqlDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * @extends GridController<Meter>
 */
class MeterController extends GridController
{
    /**
     * @return literal-string
     */
    private function kwh30Sql(): string
    {
        return '(select coalesce(sum(kwh_import), 0) from readings where readings.meter_id = meters.id and readings.read_at > '.SqlDate::thirtyDaysAgo().')';
    }

    /**
     * Meters with their gateway and headend, 30-day energy and peak kVA demand.
     *
     * @return Builder<Meter>
     */
    private function baseQuery(): Builder
    {
        return Meter::query()
            ->select('meters.*', 'gateways.serial as gateway_serial', 'gateways.headend_id', 'headends.code as headend_code')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id')
            ->addSelect(DB::raw($this->kwh30Sql().' as kwh_30d'))
            ->addSelect(DB::raw('(select max(kva_demand) from readings where readings.meter_id = meters.id and readings.read_at > '.SqlDate::thirtyDaysAgo().') as max_kva_30d'));
    }

    protected function grid(): GridQuery
    {
        return GridQuery::for($this->baseQuery())
            ->columns([
                'serial' => GridColumn::text('meters.serial'),
                'name' => GridColumn::text('meters.name'),
                'account' => GridColumn::text('meters.account'),
                'type' => GridColumn::set('meters.type'),
                'manufacturer' => GridColumn::text('meters.manufacturer'),
                'model' => GridColumn::text('meters.model'),
                'accuracy_class' => GridColumn::text('meters.accuracy_class'),
                'ct_ratio' => GridColumn::text('meters.ct_ratio'),
                'vt_ratio' => GridColumn::text('meters.vt_ratio'),
                'tariff' => GridColumn::text('meters.tariff'),
                'has_generation' => GridColumn::boolean('meters.has_generation'),
                'status' => GridColumn::set('meters.status'),
                'gateway_serial' => GridColumn::text('gateways.serial'),
                'gateway_id' => GridColumn::number('meters.gateway_id'),
                'headend_code' => GridColumn::text('headends.code'),
                'headend_id' => GridColumn::number('gateways.headend_id'),
                'installed_at' => GridColumn::date('meters.installed_at'),
                'last_reading_at' => GridColumn::date('meters.last_reading_at'),
                'kwh_30d' => GridColumn::number($this->kwh30Sql()),
            ])
            ->searchable(['meters.serial', 'meters.name', 'meters.account', 'meters.model', 'gateways.serial', 'headends.code'])
            ->defaultSort('serial');
    }

    protected function resource(): string
    {
        return MeterResource::class;
    }

    protected function exportName(): string
    {
        return 'meters';
    }

    protected function exportColumns(): array
    {
        return [
            'Serial' => 'serial',
            'Name' => 'name',
            'Account' => 'account',
            'Type' => fn (Meter $m) => $m->type->label(),
            'Manufacturer' => 'manufacturer',
            'Model' => 'model',
            'Class' => 'accuracy_class',
            'CT ratio' => 'ct_ratio',
            'VT ratio' => 'vt_ratio',
            'Tariff' => 'tariff',
            'Generation' => 'has_generation',
            'Status' => 'status',
            'Gateway' => 'gateway_serial',
            'Headend' => 'headend_code',
            'Installed' => fn (Meter $m) => $m->installed_at?->toDateString(),
            'Last reading' => 'last_reading_at',
            'kWh (30 days)' => fn (Meter $m) => round((float) $m->getAttribute('kwh_30d'), 1),
        ];
    }

    public function show(Meter $meter): MeterResource
    {
        $meter = $this->baseQuery()->findOrFail($meter->id);
        MeterStats::attach(new Collection([$meter]));

        return MeterResource::make($meter);
    }

    /**
     * Load profile, daily energy and summary statistics for a date range.
     */
    public function profile(DateRangeRequest $request, Meter $meter, MeterProfileService $profiles): JsonResponse
    {
        return response()->json($profiles->profile($meter, $request->from(), $request->to()));
    }

    public function store(SaveMeterRequest $request): JsonResponse
    {
        $meter = Meter::create($request->validated());

        return MeterResource::make($this->baseQuery()->findOrFail($meter->id))->response()->setStatusCode(201);
    }

    public function update(SaveMeterRequest $request, Meter $meter): MeterResource
    {
        $meter->update($request->validated());

        return MeterResource::make($this->baseQuery()->findOrFail($meter->id));
    }

    public function destroy(Meter $meter): Response|JsonResponse
    {
        if ($meter->readings()->exists()) {
            return response()->json([
                'message' => "Meter {$meter->serial} has recorded readings and can't be deleted. Set its status to decommissioned instead.",
            ], 409);
        }

        $meter->delete();

        return response()->noContent();
    }

    public function duplicate(Meter $meter): JsonResponse
    {
        $copy = $meter->replicate(['last_reading_at']);
        $copy->serial = (string) ((int) Meter::query()->max('serial') + 1);
        $copy->name = $meter->name.' (copy)';
        $copy->installed_at = now();
        $copy->save();

        return MeterResource::make($this->baseQuery()->findOrFail($copy->id))->response()->setStatusCode(201);
    }
}
