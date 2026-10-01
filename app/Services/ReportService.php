<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Support\SqlDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Metering reports. Each returns the same shape so the Reports panel can
 * render any of them:
 *
 *   columns: [{field, header, type: text|number|decimal|percent|datetime}]
 *   rows:    list of associative arrays
 *   chart:   {kind: line|bar|pie, category: field, series: [{field, name}], unit}
 *   summary: [{label, value, type}]
 */
class ReportService
{
    public const TYPES = [
        'consumption-by-meter' => 'Consumption by meter',
        'consumption-by-headend' => 'Consumption by headend',
        'daily-consumption' => 'Daily consumption',
        'maximum-demand' => 'Maximum demand',
        'data-completeness' => 'Data completeness',
        'power-quality' => 'Power quality',
    ];

    /**
     * @return array<string, mixed>
     */
    public function run(string $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $window = [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()];

        $report = match ($type) {
            'consumption-by-meter' => $this->byMeter($window),
            'consumption-by-headend' => $this->byHeadend($window),
            'daily-consumption' => $this->daily($window),
            'maximum-demand' => $this->maximumDemand($window),
            'data-completeness' => $this->completeness($from, $to, $window),
            'power-quality' => $this->powerQuality($window),
            default => throw new InvalidArgumentException("Unknown report [{$type}]."),
        };

        return [
            'type' => $type,
            'title' => self::TYPES[$type],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$report,
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array<string, mixed>
     */
    private function byMeter(array $window): array
    {
        $rows = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id')
            ->whereBetween('readings.read_at', $window)
            ->groupBy('meters.id', 'meters.serial', 'meters.name', 'meters.type', 'headends.code')
            ->orderByDesc('kwh')
            ->selectRaw('meters.serial, meters.name, meters.type, headends.code as headend, sum(readings.kwh_import) as kwh, sum(readings.kwh_export) as export, sum(readings.kvarh_import) as kvarh, max(readings.kva_demand) as max_kva, max(readings.kwh_import) * 2 as peak_kw, avg(readings.power_factor) as pf, count(*) as n')
            ->get()
            ->map(fn ($r) => [
                'serial' => (string) $r->serial,
                'name' => (string) $r->name,
                'type' => MeterType::from((string) $r->type)->label(),
                'headend' => (string) $r->headend,
                'kwh' => round((float) $r->kwh, 1),
                'export' => round((float) $r->export, 1),
                'kvarh' => round((float) $r->kvarh, 1),
                'max_kva' => round((float) $r->max_kva, 2),
                'pf' => round((float) $r->pf, 3),
                'load_factor' => (float) $r->peak_kw > 0 ? round((float) $r->kwh / ((int) $r->n / 2) / (float) $r->peak_kw * 100, 1) : 0,
            ])->values()->all();

        return [
            'columns' => [
                ['field' => 'serial', 'header' => 'Meter', 'type' => 'text'],
                ['field' => 'name', 'header' => 'Name', 'type' => 'text'],
                ['field' => 'type', 'header' => 'Type', 'type' => 'text'],
                ['field' => 'headend', 'header' => 'Headend', 'type' => 'text'],
                ['field' => 'kwh', 'header' => 'kWh import', 'type' => 'decimal'],
                ['field' => 'export', 'header' => 'kWh export', 'type' => 'decimal'],
                ['field' => 'kvarh', 'header' => 'kvarh', 'type' => 'decimal'],
                ['field' => 'max_kva', 'header' => 'Max kVA', 'type' => 'decimal'],
                ['field' => 'pf', 'header' => 'Avg PF', 'type' => 'number3'],
                ['field' => 'load_factor', 'header' => 'Load factor', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'chart' => ['kind' => 'bar', 'category' => 'serial', 'series' => [['field' => 'kwh', 'name' => 'kWh import']], 'limit' => 15, 'unit' => 'kWh'],
            'summary' => [
                ['label' => 'Energy imported', 'value' => round(array_sum(array_column($rows, 'kwh')), 1), 'type' => 'kwh'],
                ['label' => 'Energy exported', 'value' => round(array_sum(array_column($rows, 'export')), 1), 'type' => 'kwh'],
                ['label' => 'Meters', 'value' => count($rows), 'type' => 'number'],
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array<string, mixed>
     */
    private function byHeadend(array $window): array
    {
        $rows = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id')
            ->whereBetween('readings.read_at', $window)
            ->groupBy('headends.id', 'headends.code', 'headends.name', 'headends.city')
            ->orderByDesc('kwh')
            ->selectRaw('headends.code, headends.name, headends.city, count(distinct meters.id) as meters, sum(readings.kwh_import) as kwh, sum(readings.kwh_export) as export')
            ->get();

        $total = (float) $rows->sum('kwh');
        $rows = $rows->map(fn ($r) => [
            'code' => (string) $r->code,
            'name' => (string) $r->name,
            'city' => (string) $r->city,
            'meters' => (int) $r->meters,
            'kwh' => round((float) $r->kwh, 1),
            'export' => round((float) $r->export, 1),
            'share' => $total > 0 ? round((float) $r->kwh / $total * 100, 1) : 0,
        ])->values()->all();

        return [
            'columns' => [
                ['field' => 'code', 'header' => 'Headend', 'type' => 'text'],
                ['field' => 'name', 'header' => 'Name', 'type' => 'text'],
                ['field' => 'city', 'header' => 'City', 'type' => 'text'],
                ['field' => 'meters', 'header' => 'Meters', 'type' => 'number'],
                ['field' => 'kwh', 'header' => 'kWh import', 'type' => 'decimal'],
                ['field' => 'export', 'header' => 'kWh export', 'type' => 'decimal'],
                ['field' => 'share', 'header' => 'Share', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'chart' => ['kind' => 'pie', 'category' => 'code', 'series' => [['field' => 'kwh', 'name' => 'kWh import']], 'unit' => 'kWh'],
            'summary' => [
                ['label' => 'Energy imported', 'value' => round($total, 1), 'type' => 'kwh'],
                ['label' => 'Headends', 'value' => count($rows), 'type' => 'number'],
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array<string, mixed>
     */
    private function daily(array $window): array
    {
        $day = SqlDate::bucket('read_at', 'day');

        $energy = DB::table('readings')
            ->whereBetween('read_at', $window)
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw("{$day} as day, sum(kwh_import) as kwh, sum(kwh_export) as export, sum(kvarh_import) as kvarh, count(*) as n")
            ->get();

        $peaks = DB::query()
            ->fromSub(
                DB::table('readings')->whereBetween('read_at', $window)->groupBy('read_at')->selectRaw('read_at, sum(kva_demand) as kva'),
                'per_interval',
            )
            ->groupBy('day')
            ->selectRaw(SqlDate::bucket('per_interval.read_at', 'day').' as day, max(kva) as peak')
            ->pluck('peak', 'day');

        $rows = $energy->map(fn ($r) => [
            'day' => (string) $r->day,
            'kwh' => round((float) $r->kwh, 1),
            'export' => round((float) $r->export, 1),
            'kvarh' => round((float) $r->kvarh, 1),
            'peak_kva' => round((float) ($peaks[$r->day] ?? 0), 1),
            'readings' => (int) $r->n,
        ])->values()->all();

        $kwh = array_sum(array_column($rows, 'kwh'));

        return [
            'columns' => [
                ['field' => 'day', 'header' => 'Day', 'type' => 'text'],
                ['field' => 'kwh', 'header' => 'kWh import', 'type' => 'decimal'],
                ['field' => 'export', 'header' => 'kWh export', 'type' => 'decimal'],
                ['field' => 'kvarh', 'header' => 'kvarh', 'type' => 'decimal'],
                ['field' => 'peak_kva', 'header' => 'Network peak kVA', 'type' => 'decimal'],
                ['field' => 'readings', 'header' => 'Readings', 'type' => 'number'],
            ],
            'rows' => $rows,
            'chart' => ['kind' => 'line', 'category' => 'day', 'series' => [['field' => 'kwh', 'name' => 'kWh import']], 'unit' => 'kWh'],
            'summary' => [
                ['label' => 'Energy imported', 'value' => round($kwh, 1), 'type' => 'kwh'],
                ['label' => 'Daily average', 'value' => count($rows) ? round($kwh / count($rows), 1) : 0, 'type' => 'kwh'],
                ['label' => 'Highest peak', 'value' => $rows ? max(array_column($rows, 'peak_kva')) : 0, 'type' => 'kva'],
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array<string, mixed>
     */
    private function maximumDemand(array $window): array
    {
        $peaks = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->whereBetween('readings.read_at', $window)
            ->groupBy('meters.id', 'meters.serial', 'meters.name', 'meters.tariff', 'meters.type')
            ->orderByDesc('max_kva')
            ->limit(25)
            ->selectRaw('meters.id, meters.serial, meters.name, meters.tariff, meters.type, max(readings.kva_demand) as max_kva')
            ->get();

        $rows = $peaks->map(function ($r) use ($window) {
            $at = DB::table('readings')
                ->where('meter_id', $r->id)
                ->whereBetween('read_at', $window)
                ->orderByDesc('kva_demand')
                ->value('read_at');

            return [
                'serial' => (string) $r->serial,
                'name' => (string) $r->name,
                'type' => MeterType::from((string) $r->type)->label(),
                'tariff' => (string) $r->tariff,
                'max_kva' => round((float) $r->max_kva, 2),
                'at' => $at !== null ? substr((string) $at, 0, 16) : '',
            ];
        })->values()->all();

        return [
            'columns' => [
                ['field' => 'serial', 'header' => 'Meter', 'type' => 'text'],
                ['field' => 'name', 'header' => 'Name', 'type' => 'text'],
                ['field' => 'type', 'header' => 'Type', 'type' => 'text'],
                ['field' => 'tariff', 'header' => 'Tariff', 'type' => 'text'],
                ['field' => 'max_kva', 'header' => 'Max demand kVA', 'type' => 'decimal'],
                ['field' => 'at', 'header' => 'Interval ending', 'type' => 'text'],
            ],
            'rows' => $rows,
            'chart' => ['kind' => 'bar', 'category' => 'serial', 'series' => [['field' => 'max_kva', 'name' => 'Max demand kVA']], 'limit' => 15, 'unit' => 'kVA'],
            'summary' => [
                ['label' => 'Highest demand', 'value' => $rows ? $rows[0]['max_kva'] : 0, 'type' => 'kva'],
                ['label' => 'Meters listed', 'value' => count($rows), 'type' => 'number'],
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array<string, mixed>
     */
    private function completeness(CarbonImmutable $from, CarbonImmutable $to, array $window): array
    {
        $end = min($to->endOfDay()->getTimestamp(), CarbonImmutable::now()->getTimestamp());
        $intervals = max(0, intdiv($end - $from->startOfDay()->getTimestamp(), 1800));

        $rows = DB::table('gateways')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id')
            ->leftJoin('meters', 'meters.gateway_id', '=', 'gateways.id')
            ->groupBy('gateways.id', 'gateways.serial', 'gateways.name', 'gateways.status', 'headends.code')
            ->orderBy('gateways.serial')
            ->selectRaw('gateways.id, gateways.serial, gateways.name, gateways.status, headends.code as headend, count(meters.id) as meters')
            ->get();

        $received = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->whereBetween('readings.read_at', $window)
            ->groupBy('meters.gateway_id')
            ->selectRaw("meters.gateway_id, count(*) as n, sum(case when readings.quality = 'estimated' then 1 else 0 end) as estimated")
            ->get()
            ->keyBy('gateway_id');

        $rows = $rows->map(function ($g) use ($received, $intervals) {
            $expected = (int) $g->meters * $intervals;
            $n = (int) ($received[$g->id]->n ?? 0);

            return [
                'gateway' => (string) $g->serial,
                'name' => (string) $g->name,
                'headend' => (string) $g->headend,
                'status' => (string) $g->status,
                'meters' => (int) $g->meters,
                'expected' => $expected,
                'received' => $n,
                'estimated' => (int) ($received[$g->id]->estimated ?? 0),
                'completeness' => $expected > 0 ? round(min(100, $n / $expected * 100), 2) : 0,
            ];
        })->sortBy('completeness')->values()->all();

        $expected = array_sum(array_column($rows, 'expected'));
        $receivedTotal = array_sum(array_column($rows, 'received'));

        return [
            'columns' => [
                ['field' => 'gateway', 'header' => 'Gateway', 'type' => 'text'],
                ['field' => 'name', 'header' => 'Name', 'type' => 'text'],
                ['field' => 'headend', 'header' => 'Headend', 'type' => 'text'],
                ['field' => 'status', 'header' => 'Status', 'type' => 'text'],
                ['field' => 'meters', 'header' => 'Meters', 'type' => 'number'],
                ['field' => 'expected', 'header' => 'Expected', 'type' => 'number'],
                ['field' => 'received', 'header' => 'Received', 'type' => 'number'],
                ['field' => 'estimated', 'header' => 'Estimated', 'type' => 'number'],
                ['field' => 'completeness', 'header' => 'Complete', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'chart' => ['kind' => 'bar', 'category' => 'gateway', 'series' => [['field' => 'completeness', 'name' => 'Complete %']], 'limit' => 15, 'unit' => '%'],
            'summary' => [
                ['label' => 'Network completeness', 'value' => $expected > 0 ? round($receivedTotal / $expected * 100, 2) : 0, 'type' => 'percent'],
                ['label' => 'Missing readings', 'value' => max(0, $expected - $receivedTotal), 'type' => 'number'],
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array<string, mixed>
     */
    private function powerQuality(array $window): array
    {
        $rows = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->whereBetween('readings.read_at', $window)
            ->groupBy('meters.id', 'meters.serial', 'meters.name', 'meters.vt_ratio')
            ->selectRaw('meters.serial, meters.name, meters.vt_ratio, min(readings.voltage_l1) as vmin, avg(readings.voltage_l1) as vavg, max(readings.voltage_l1) as vmax, avg(readings.power_factor) as pf, min(readings.power_factor) as pf_min')
            // Intervals more than 10% away from nominal (230 V, or 63.5 V secondary on VT meters).
            ->selectRaw('sum(case when readings.voltage_l1 < (case when meters.vt_ratio is null then 207 else 57.15 end) or readings.voltage_l1 > (case when meters.vt_ratio is null then 253 else 69.85 end) then 1 else 0 end) as excursions')
            ->get()
            ->map(fn ($r) => [
                'serial' => (string) $r->serial,
                'name' => (string) $r->name,
                'nominal' => $r->vt_ratio !== null ? '63.5 V (VT)' : '230 V',
                'vmin' => round((float) $r->vmin, 1),
                'vavg' => round((float) $r->vavg, 1),
                'vmax' => round((float) $r->vmax, 1),
                'excursions' => (int) $r->excursions,
                'pf' => round((float) $r->pf, 3),
                'pf_min' => round((float) $r->pf_min, 3),
            ])
            ->sortByDesc('excursions')
            ->values()
            ->all();

        return [
            'columns' => [
                ['field' => 'serial', 'header' => 'Meter', 'type' => 'text'],
                ['field' => 'name', 'header' => 'Name', 'type' => 'text'],
                ['field' => 'nominal', 'header' => 'Nominal', 'type' => 'text'],
                ['field' => 'vmin', 'header' => 'V min', 'type' => 'decimal'],
                ['field' => 'vavg', 'header' => 'V avg', 'type' => 'decimal'],
                ['field' => 'vmax', 'header' => 'V max', 'type' => 'decimal'],
                ['field' => 'excursions', 'header' => 'Out of ±10%', 'type' => 'number'],
                ['field' => 'pf', 'header' => 'Avg PF', 'type' => 'number3'],
                ['field' => 'pf_min', 'header' => 'Min PF', 'type' => 'number3'],
            ],
            'rows' => $rows,
            'chart' => ['kind' => 'bar', 'category' => 'serial', 'series' => [['field' => 'excursions', 'name' => 'Intervals out of ±10%']], 'limit' => 15, 'unit' => 'intervals'],
            'summary' => [
                ['label' => 'Voltage excursions', 'value' => array_sum(array_column($rows, 'excursions')), 'type' => 'number'],
                ['label' => 'Meters affected', 'value' => count(array_filter($rows, fn ($r) => $r['excursions'] > 0)), 'type' => 'number'],
            ],
        ];
    }
}
