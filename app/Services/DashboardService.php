<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\EventSeverity;
use App\Enums\MeterStatus;
use App\Enums\MeterType;
use App\Http\Resources\MeterEventResource;
use App\Models\MeterEvent;
use App\Support\SqlDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Network-wide aggregates for the dashboard, cached briefly. KPI changes
 * compare the selected range with the preceding period of equal length.
 */
class DashboardService
{
    public const CACHE_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->endOfDay();

        $key = sprintf('dashboard:v2:%s:%s', $from->toDateString(), $to->toDateString());

        return Cache::remember($key, self::CACHE_SECONDS, fn () => $this->build($from, $to));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $prevTo = $from->subDay()->endOfDay();
        $prevFrom = $prevTo->subDays($days - 1)->startOfDay();
        $granularity = $days <= 3 ? 'interval' : ($days <= 21 ? 'hour' : 'day');

        $current = $this->loadSeries($from, $to, $granularity);
        $previous = $this->loadSeries($prevFrom, $prevTo, $granularity);
        $daily = $this->dailyTotals($from, $to);

        $energy = $this->energy($from, $to);
        $prevEnergy = $this->energy($prevFrom, $prevTo);
        $peak = $this->peakDemand($from, $to);
        $prevPeak = $this->peakDemand($prevFrom, $prevTo);
        $completeness = $this->completeness($from, $to);
        $prevCompleteness = $this->completeness($prevFrom, $prevTo);

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previousFrom' => $prevFrom->toDateString(),
                'previousTo' => $prevTo->toDateString(),
                'days' => $days,
                'granularity' => $granularity,
            ],
            'kpis' => [
                'energy' => [
                    'value' => round($energy['import'], 1),
                    'previous' => round($prevEnergy['import'], 1),
                    'delta' => $this->delta($energy['import'], $prevEnergy['import']),
                    'spark' => array_map(fn ($d) => round($d['kwh'], 1), $daily),
                    'export' => round($energy['export'], 1),
                ],
                'peakDemand' => [
                    'value' => round($peak['kva'], 1),
                    'previous' => round($prevPeak['kva'], 1),
                    'delta' => $this->delta($peak['kva'], $prevPeak['kva']),
                    'spark' => array_map(fn ($d) => round($d['peak'], 1), $daily),
                    'at' => $peak['at'],
                ],
                'completeness' => [
                    'value' => $completeness['pct'],
                    'previous' => $prevCompleteness['pct'],
                    'delta' => $completeness['pct'] !== null && $prevCompleteness['pct'] !== null
                        ? round($completeness['pct'] - $prevCompleteness['pct'], 1)
                        : null,
                    'spark' => array_map(fn ($d) => $d['completeness'], $daily),
                    'missing' => $completeness['missing'],
                    'estimated' => $completeness['estimated'],
                ],
                'alarms' => $this->alarms($from, $to, $prevFrom, $prevTo),
            ],
            'loadProfile' => [
                'buckets' => array_column($current, 'bucket'),
                'kw' => array_column($current, 'kw'),
                'previousKw' => array_slice(array_column($previous, 'kw'), 0, count($current)),
            ],
            'byType' => $this->byType($from, $to),
            'topMeters' => $this->topMeters($from, $to),
            'heatmap' => $this->heatmap($from, $to),
            'network' => $this->network($from, $to),
            'recentEvents' => $this->recentEvents($from, $to),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function window(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->toDateTimeString(), $to->toDateTimeString()];
    }

    /**
     * Network load (sum of all meters, average kW) per bucket.
     *
     * @return list<array{bucket: string, kw: float}>
     */
    private function loadSeries(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $bucket = $granularity === 'interval' ? 'read_at' : SqlDate::bucket('read_at', $granularity === 'hour' ? 'hour' : 'day');
        $hoursPerBucket = match ($granularity) {
            'interval' => 0.5,
            'hour' => 1.0,
            default => 24.0,
        };

        $rows = DB::table('readings')
            ->whereBetween('read_at', $this->window($from, $to))
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->selectRaw("{$bucket} as bucket, sum(kwh_import) as kwh")
            ->get();

        // Average network kW over the bucket = energy / bucket hours.
        return array_values($rows->map(fn ($r) => [
            'bucket' => (string) $r->bucket,
            'kw' => round((float) $r->kwh / $hoursPerBucket, 1),
        ])->all());
    }

    /**
     * @return list<array{day: string, kwh: float, peak: float, completeness: float}>
     */
    private function dailyTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $day = SqlDate::bucket('read_at', 'day');

        $energy = DB::table('readings')
            ->whereBetween('read_at', $this->window($from, $to))
            ->groupBy('day')
            ->selectRaw("{$day} as day, sum(kwh_import) as kwh, count(*) as n")
            ->get()
            ->keyBy('day');

        $peaks = DB::query()
            ->fromSub(
                DB::table('readings')
                    ->whereBetween('read_at', $this->window($from, $to))
                    ->groupBy('read_at')
                    ->selectRaw('read_at, sum(kva_demand) as kva'),
                'per_interval',
            )
            ->groupBy('day')
            ->selectRaw(SqlDate::bucket('per_interval.read_at', 'day').' as day, max(kva) as peak')
            ->pluck('peak', 'day');

        $meters = $this->activeMeterCount();
        $now = CarbonImmutable::now();
        $series = [];

        for ($d = $from; $d <= $to && $d <= $now; $d = $d->addDay()) {
            $key = $d->toDateString();
            $n = (int) ($energy[$key]->n ?? 0);
            $intervals = $d->isSameDay($now) ? max(1, intdiv($now->hour * 60 + $now->minute, 30)) : 48;
            $expected = $meters * $intervals;
            $series[] = [
                'day' => $key,
                'kwh' => (float) ($energy[$key]->kwh ?? 0),
                'peak' => (float) ($peaks[$key] ?? 0),
                'completeness' => $expected > 0 ? round(min(100, $n / $expected * 100), 1) : 0.0,
            ];
        }

        return $series;
    }

    /**
     * @return array{import: float, export: float}
     */
    private function energy(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('readings')
            ->whereBetween('read_at', $this->window($from, $to))
            ->selectRaw('coalesce(sum(kwh_import), 0) as kwh_in, coalesce(sum(kwh_export), 0) as kwh_out')
            ->first();

        return ['import' => (float) ($row->kwh_in ?? 0), 'export' => (float) ($row->kwh_out ?? 0)];
    }

    /**
     * Coincident network peak: the half-hour with the highest total kVA.
     *
     * @return array{kva: float, at: string|null}
     */
    private function peakDemand(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('readings')
            ->whereBetween('read_at', $this->window($from, $to))
            ->groupBy('read_at')
            ->orderByDesc('kva')
            ->selectRaw('read_at, sum(kva_demand) as kva')
            ->first();

        return [
            'kva' => (float) ($row->kva ?? 0),
            'at' => isset($row->read_at) ? CarbonImmutable::parse((string) $row->read_at)->toIso8601String() : null,
        ];
    }

    private function activeMeterCount(): int
    {
        return DB::table('meters')->where('status', '!=', MeterStatus::Decommissioned->value)->count();
    }

    /**
     * Share of expected half-hourly readings that actually arrived.
     *
     * @return array{pct: float|null, missing: int, estimated: int}
     */
    private function completeness(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $end = min($to->getTimestamp(), CarbonImmutable::now()->getTimestamp());
        $intervals = max(0, intdiv($end - $from->getTimestamp(), 1800));
        $expected = $intervals * $this->activeMeterCount();

        $row = DB::table('readings')
            ->whereBetween('read_at', $this->window($from, $to))
            ->selectRaw("count(*) as n, sum(case when quality = 'estimated' then 1 else 0 end) as estimated")
            ->first();

        $n = (int) ($row->n ?? 0);

        return [
            'pct' => $expected > 0 ? round(min(100, $n / $expected * 100), 2) : null,
            'missing' => max(0, $expected - $n),
            'estimated' => (int) ($row->estimated ?? 0),
        ];
    }

    /**
     * Warning and critical events in the range, with what is still open.
     *
     * @return array<string, mixed>
     */
    private function alarms(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo): array
    {
        $severe = [EventSeverity::Warning->value, EventSeverity::Critical->value];

        $count = fn (CarbonImmutable $a, CarbonImmutable $b) => MeterEvent::query()
            ->whereIn('severity', $severe)
            ->whereBetween('occurred_at', $this->window($a, $b))
            ->count();

        $current = $count($from, $to);
        $previous = $count($prevFrom, $prevTo);

        $day = SqlDate::bucket('occurred_at', 'day');
        $perDay = MeterEvent::query()
            ->toBase()
            ->whereIn('severity', $severe)
            ->whereBetween('occurred_at', $this->window($from, $to))
            ->groupBy('day')
            ->selectRaw("{$day} as day, count(*) as n")
            ->pluck('n', 'day');

        $spark = [];
        for ($d = $from; $d <= $to && $d <= CarbonImmutable::now(); $d = $d->addDay()) {
            $spark[] = (int) ($perDay[$d->toDateString()] ?? 0);
        }

        return [
            'value' => $current,
            'previous' => $previous,
            'delta' => $this->delta($current, $previous),
            'spark' => $spark,
            'open' => MeterEvent::query()->where('acknowledged', false)->whereIn('severity', $severe)->count(),
            'critical' => MeterEvent::query()->where('acknowledged', false)->where('severity', EventSeverity::Critical->value)->count(),
        ];
    }

    /**
     * Energy by meter type, always in enum order so each type keeps its color.
     *
     * @return list<array{type: string, label: string, kwh: float, meters: int}>
     */
    private function byType(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->whereBetween('readings.read_at', $this->window($from, $to))
            ->groupBy('meters.type')
            ->selectRaw('meters.type, sum(readings.kwh_import) as kwh, count(distinct meters.id) as meters')
            ->get()
            ->keyBy('type');

        return array_map(fn (MeterType $t) => [
            'type' => $t->value,
            'label' => $t->label(),
            'kwh' => round((float) ($rows[$t->value]->kwh ?? 0), 1),
            'meters' => (int) ($rows[$t->value]->meters ?? 0),
        ], MeterType::cases());
    }

    /**
     * @return list<array{id: int, serial: string, name: string, kwh: float, max_kva: float}>
     */
    private function topMeters(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->whereBetween('readings.read_at', $this->window($from, $to))
            ->groupBy('meters.id', 'meters.serial', 'meters.name')
            ->orderByDesc('kwh')
            ->limit(10)
            ->selectRaw('meters.id, meters.serial, meters.name, sum(readings.kwh_import) as kwh, max(readings.kva_demand) as max_kva')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'serial' => (string) $r->serial,
                'name' => (string) $r->name,
                'kwh' => round((float) $r->kwh, 1),
                'max_kva' => round((float) $r->max_kva, 1),
            ])
            ->all());
    }

    /**
     * Average network load (kW) by weekday (Mon = 0) and hour of day.
     *
     * @return list<array{0: int, 1: int, 2: float}>
     */
    private function heatmap(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $weekday = SqlDate::weekday('per_interval.read_at');
        $hour = SqlDate::hour('per_interval.read_at');

        return array_values(DB::query()
            ->fromSub(
                DB::table('readings')
                    ->whereBetween('read_at', $this->window($from, $to))
                    ->groupBy('read_at')
                    ->selectRaw('read_at, sum(kwh_import) * 2 as kw'),
                'per_interval',
            )
            ->groupBy('wd', 'hr')
            ->selectRaw("{$weekday} as wd, {$hour} as hr, avg(kw) as kw")
            ->get()
            ->map(fn ($r) => [(int) $r->wd, (int) $r->hr, round((float) $r->kw, 1)])
            ->all());
    }

    /**
     * Per-headend health: gateways online, meters reporting, energy in range.
     *
     * @return list<array<string, mixed>>
     */
    private function network(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $energy = DB::table('readings')
            ->join('meters', 'meters.id', '=', 'readings.meter_id')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->whereBetween('readings.read_at', $this->window($from, $to))
            ->groupBy('gateways.headend_id')
            ->selectRaw('gateways.headend_id, sum(readings.kwh_import) as kwh')
            ->pluck('kwh', 'headend_id');

        $gateways = DB::table('gateways')
            ->groupBy('headend_id')
            ->selectRaw('headend_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as online', [DeviceStatus::Online->value])
            ->get()
            ->keyBy('headend_id');

        $meters = DB::table('meters')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->groupBy('gateways.headend_id')
            ->selectRaw('gateways.headend_id, count(*) as total, sum(case when meters.last_reading_at >= ? then 1 else 0 end) as reporting', [CarbonImmutable::now()->subHours(2)->toDateTimeString()])
            ->get()
            ->keyBy('headend_id');

        return array_values(DB::table('headends')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'city', 'status'])
            ->map(fn ($h) => [
                'id' => (int) $h->id,
                'code' => (string) $h->code,
                'name' => (string) $h->name,
                'city' => (string) $h->city,
                'status' => (string) $h->status,
                'gateways' => (int) ($gateways[$h->id]->total ?? 0),
                'gatewaysOnline' => (int) ($gateways[$h->id]->online ?? 0),
                'meters' => (int) ($meters[$h->id]->total ?? 0),
                'metersReporting' => (int) ($meters[$h->id]->reporting ?? 0),
                'kwh' => round((float) ($energy[$h->id] ?? 0), 1),
            ])
            ->all());
    }

    /**
     * @return array<int, mixed>
     */
    private function recentEvents(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = MeterEvent::query()
            ->select('meter_events.*', 'meters.serial as meter_serial', 'meters.name as meter_name', 'headends.code as headend_code')
            ->join('meters', 'meters.id', '=', 'meter_events.meter_id')
            ->join('gateways', 'gateways.id', '=', 'meters.gateway_id')
            ->join('headends', 'headends.id', '=', 'gateways.headend_id')
            ->whereBetween('occurred_at', $this->window($from, $to))
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get();

        return MeterEventResource::collection($events)->resolve();
    }

    private function delta(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
