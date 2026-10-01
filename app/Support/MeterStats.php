<?php

namespace App\Support;

use App\Models\Meter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attaches live figures to a set of meters with two grouped queries:
 * `kw_now` (average kW over the latest interval) and `kwh_today`
 * (energy imported since midnight).
 */
final class MeterStats
{
    /**
     * @param  Collection<int, Meter>  $meters
     */
    public static function attach(Collection $meters): void
    {
        $ids = $meters->modelKeys();

        if ($ids === []) {
            return;
        }

        $today = DB::table('readings')
            ->whereIn('meter_id', $ids)
            ->where('read_at', '>', CarbonImmutable::today()->toDateTimeString())
            ->groupBy('meter_id')
            ->selectRaw('meter_id, sum(kwh_import) as kwh')
            ->pluck('kwh', 'meter_id');

        $latest = DB::table('readings as r')
            ->joinSub(
                DB::table('readings')->whereIn('meter_id', $ids)->groupBy('meter_id')->selectRaw('meter_id, max(read_at) as read_at'),
                'last',
                fn ($join) => $join->on('last.meter_id', '=', 'r.meter_id')->on('last.read_at', '=', 'r.read_at'),
            )
            ->selectRaw('r.meter_id, (r.kwh_import - r.kwh_export) * 2 as kw')
            ->pluck('kw', 'meter_id');

        foreach ($meters as $meter) {
            $meter->setAttribute('kwh_today', (float) ($today[$meter->id] ?? 0));
            $meter->setAttribute('kw_now', isset($latest[$meter->id]) ? (float) $latest[$meter->id] : null);
        }
    }
}
