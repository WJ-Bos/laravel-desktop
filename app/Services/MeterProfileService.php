<?php

namespace App\Services;

use App\Models\Meter;
use App\Support\SqlDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * One meter's load profile for a date range: an interval/hourly/daily
 * series for charts, daily energy totals and the usual MDM summary figures
 * (maximum demand, load factor, power factor, voltage band, completeness).
 */
class MeterProfileService
{
    /**
     * @return array<string, mixed>
     */
    public function profile(Meter $meter, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $granularity = $days <= 7 ? 'interval' : ($days <= 31 ? 'hour' : 'day');
        $window = [$from->toDateTimeString(), $to->toDateTimeString()];

        $bucket = match ($granularity) {
            'interval' => 'read_at',
            'hour' => SqlDate::bucket('read_at', 'hour'),
            default => SqlDate::bucket('read_at', 'day'),
        };

        $series = DB::table('readings')
            ->where('meter_id', $meter->id)
            ->whereBetween('read_at', $window)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->selectRaw("{$bucket} as bucket")
            ->selectRaw('avg(kwh_import) * 2 as kw, avg(kwh_export) * 2 as kw_export, max(kva_demand) as kva, avg(power_factor) as pf')
            ->selectRaw('avg(voltage_l1) as v1, avg(voltage_l2) as v2, avg(voltage_l3) as v3')
            ->selectRaw("sum(case when quality = 'estimated' then 1 else 0 end) as estimated")
            ->get();

        $daily = DB::table('readings')
            ->where('meter_id', $meter->id)
            ->whereBetween('read_at', $window)
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw(SqlDate::bucket('read_at', 'day').' as day, sum(kwh_import) as kwh_import, sum(kwh_export) as kwh_export, max(kva_demand) as max_kva, count(*) as intervals')
            ->get();

        $summary = DB::table('readings')
            ->where('meter_id', $meter->id)
            ->whereBetween('read_at', $window)
            ->selectRaw('count(*) as n, sum(kwh_import) as kwh_import, sum(kwh_export) as kwh_export, sum(kvarh_import) as kvarh')
            ->selectRaw('max(kva_demand) as max_kva, avg(power_factor) as pf, max(kwh_import) * 2 as peak_kw')
            ->selectRaw('min(voltage_l1) as vmin, max(voltage_l1) as vmax')
            ->selectRaw("sum(case when quality = 'estimated' then 1 else 0 end) as estimated")
            ->first();

        $peak = DB::table('readings')
            ->where('meter_id', $meter->id)
            ->whereBetween('read_at', $window)
            ->orderByDesc('kva_demand')
            ->value('read_at');

        $end = min($to->getTimestamp(), CarbonImmutable::now()->getTimestamp());
        $expected = max(1, (int) floor(($end - $from->getTimestamp()) / 1800));
        $n = (int) ($summary->n ?? 0);
        $hours = $n / 2;
        $peakKw = (float) ($summary->peak_kw ?? 0);
        $kwh = (float) ($summary->kwh_import ?? 0);

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'granularity' => $granularity,
            ],
            'series' => [
                'buckets' => $series->pluck('bucket')->map(fn ($b) => (string) $b)->all(),
                'kw' => $series->map(fn ($r) => round((float) $r->kw, 3))->all(),
                'kwExport' => $series->map(fn ($r) => round((float) $r->kw_export, 3))->all(),
                'kva' => $series->map(fn ($r) => round((float) $r->kva, 3))->all(),
                'pf' => $series->map(fn ($r) => $r->pf !== null ? round((float) $r->pf, 3) : null)->all(),
                'v1' => $series->map(fn ($r) => $r->v1 !== null ? round((float) $r->v1, 1) : null)->all(),
                'v2' => $series->map(fn ($r) => $r->v2 !== null ? round((float) $r->v2, 1) : null)->all(),
                'v3' => $series->map(fn ($r) => $r->v3 !== null ? round((float) $r->v3, 1) : null)->all(),
            ],
            'daily' => $daily->map(fn ($d) => [
                'day' => (string) $d->day,
                'kwh_import' => round((float) $d->kwh_import, 2),
                'kwh_export' => round((float) $d->kwh_export, 2),
                'max_kva' => round((float) $d->max_kva, 2),
                'intervals' => (int) $d->intervals,
            ])->values()->all(),
            'summary' => [
                'kwhImport' => round($kwh, 1),
                'kwhExport' => round((float) ($summary->kwh_export ?? 0), 1),
                'kvarh' => round((float) ($summary->kvarh ?? 0), 1),
                'maxKva' => round((float) ($summary->max_kva ?? 0), 2),
                'maxKvaAt' => $peak !== null ? CarbonImmutable::parse((string) $peak)->toIso8601String() : null,
                'avgPf' => $summary->pf !== null ? round((float) $summary->pf, 3) : null,
                'loadFactor' => $peakKw > 0 && $hours > 0 ? round($kwh / $hours / $peakKw * 100, 1) : null,
                'vMin' => $summary->vmin !== null ? round((float) $summary->vmin, 1) : null,
                'vMax' => $summary->vmax !== null ? round((float) $summary->vmax, 1) : null,
                'intervals' => $n,
                'expected' => $expected,
                'completeness' => round(min(100, $n / $expected * 100), 1),
                'estimated' => (int) ($summary->estimated ?? 0),
            ],
        ];
    }
}
