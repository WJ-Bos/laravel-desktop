<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceStatus;
use App\Http\Requests\SaveHeadendRequest;
use App\Http\Resources\HeadendResource;
use App\Models\Headend;
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
 * @extends GridController<Headend>
 */
class HeadendController extends GridController
{
    private const METERS_SQL = '(select count(*) from meters join gateways on gateways.id = meters.gateway_id where gateways.headend_id = headends.id)';

    /**
     * @return literal-string
     */
    private function reportingSql(): string
    {
        return '(select count(*) from meters join gateways on gateways.id = meters.gateway_id where gateways.headend_id = headends.id and meters.last_reading_at >= '.SqlDate::reportingCutoff().')';
    }

    /**
     * @return Builder<Headend>
     */
    private function withStats(): Builder
    {
        return Headend::query()
            ->select('headends.*')
            ->withCount(['gateways', 'gateways as gateways_online' => fn ($q) => $q->where('status', DeviceStatus::Online->value)])
            ->addSelect(DB::raw(self::METERS_SQL.' as meters_count'))
            ->addSelect(DB::raw($this->reportingSql().' as meters_reporting'));
    }

    protected function grid(): GridQuery
    {
        return GridQuery::for($this->withStats())
            ->columns([
                'code' => GridColumn::text('headends.code'),
                'name' => GridColumn::text('headends.name'),
                'site' => GridColumn::text('headends.site'),
                'city' => GridColumn::text('headends.city'),
                'protocol' => GridColumn::text('headends.protocol'),
                'ip_address' => GridColumn::text('headends.ip_address'),
                'poll_interval_min' => GridColumn::number('headends.poll_interval_min'),
                'status' => GridColumn::set('headends.status'),
                'gateways_count' => GridColumn::number('(select count(*) from gateways where gateways.headend_id = headends.id)'),
                'meters_count' => GridColumn::number(self::METERS_SQL),
                'meters_reporting' => GridColumn::number($this->reportingSql()),
                'last_poll_at' => GridColumn::date('headends.last_poll_at'),
            ])
            ->searchable(['headends.code', 'headends.name', 'headends.site', 'headends.city', 'headends.ip_address'])
            ->defaultSort('code');
    }

    protected function resource(): string
    {
        return HeadendResource::class;
    }

    protected function exportName(): string
    {
        return 'headends';
    }

    protected function exportColumns(): array
    {
        return [
            'Code' => 'code',
            'Name' => 'name',
            'Site' => 'site',
            'City' => 'city',
            'Protocol' => 'protocol',
            'IP address' => 'ip_address',
            'Poll interval (min)' => 'poll_interval_min',
            'Status' => 'status',
            'Gateways' => 'gateways_count',
            'Meters' => 'meters_count',
            'Meters reporting' => 'meters_reporting',
            'Last poll' => 'last_poll_at',
        ];
    }

    /**
     * The headend with every gateway and meter under it, plus live meter figures.
     */
    public function show(Headend $headend): HeadendResource
    {
        $headend = $this->withStats()->findOrFail($headend->id);
        $headend->load([
            'gateways' => fn ($q) => $q->withCount('meters')->orderBy('serial'),
            'gateways.meters' => fn ($q) => $q->orderBy('serial'),
        ]);

        MeterStats::attach(new Collection($headend->gateways->flatMap(fn ($gateway) => $gateway->meters)->all()));

        return HeadendResource::make($headend);
    }

    public function store(SaveHeadendRequest $request): JsonResponse
    {
        $headend = Headend::create($request->validated());

        return HeadendResource::make($this->withStats()->findOrFail($headend->id))->response()->setStatusCode(201);
    }

    public function update(SaveHeadendRequest $request, Headend $headend): HeadendResource
    {
        $headend->update($request->validated());

        return HeadendResource::make($this->withStats()->findOrFail($headend->id));
    }

    public function destroy(Headend $headend): Response|JsonResponse
    {
        if ($headend->gateways()->exists()) {
            return response()->json([
                'message' => "{$headend->code} still has gateways. Move or delete them first.",
            ], 409);
        }

        $headend->delete();

        return response()->noContent();
    }

    public function duplicate(Headend $headend): JsonResponse
    {
        $copy = $headend->replicate(['code', 'last_poll_at']);
        $copy->name = $headend->name.' (copy)';
        $copy->save();

        return HeadendResource::make($this->withStats()->findOrFail($copy->id))->response()->setStatusCode(201);
    }
}
