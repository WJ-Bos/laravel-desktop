<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\EventSeverity;
use App\Enums\MeterStatus;
use App\Enums\MeterType;
use App\Models\Gateway;
use App\Models\Headend;
use App\Models\Meter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A small metering network with 60 days of half-hourly load profiles:
 * 8 headends × 3 gateways × 3 meters (9 meters per headend, 72 in total),
 * a mix of meter types and load shapes (residential, commercial,
 * industrial, rooftop PV), plus communication gaps and meter events.
 */
class MeteringSeeder extends Seeder
{
    private const DAYS = 60;

    /** @var list<array{string, string, string}> [name, site, city] */
    private const HEADENDS = [
        ['Century City', 'Century Blvd', 'Cape Town'],
        ['Rosebank', 'Oxford Rd', 'Johannesburg'],
        ['Umhlanga Ridge', 'Ridgeside Dr', 'Durban'],
        ['Menlyn', 'Atterbury Rd', 'Pretoria'],
        ['Stellenbosch Techno Park', 'Techno Ave', 'Stellenbosch'],
        ['Midrand Waterfall', 'Waterfall Dr', 'Midrand'],
        ['Gqeberha Harbour', 'Baakens St', 'Gqeberha'],
        ['Bloemfontein CBD', 'Nelson Mandela Dr', 'Bloemfontein'],
    ];

    /**
     * Meter archetypes: type, maker, model, class, ratios, profile, kW range, tariff.
     *
     * @var list<array{type: MeterType, manufacturer: string, model: string, class: string, ct: string|null, vt: string|null, profile: string, kw: array{0: float, 1: float}, tariff: string, label: string}>
     */
    private const ARCHETYPES = [
        ['type' => MeterType::SinglePhase, 'manufacturer' => 'PMT', 'model' => 'MOL1100', 'class' => '1', 'ct' => null, 'vt' => null, 'profile' => 'residential', 'kw' => [0.6, 2.2], 'tariff' => 'Homepower 4', 'label' => 'Unit'],
        ['type' => MeterType::SinglePhase, 'manufacturer' => 'Landis+Gyr', 'model' => 'E350', 'class' => '1', 'ct' => null, 'vt' => null, 'profile' => 'residential', 'kw' => [0.8, 2.6], 'tariff' => 'Homepower 4', 'label' => 'Apartment'],
        ['type' => MeterType::DinRail, 'manufacturer' => 'PMT', 'model' => 'MOL200 DIN', 'class' => '1', 'ct' => null, 'vt' => null, 'profile' => 'commercial', 'kw' => [3.0, 9.0], 'tariff' => 'Businessrate 2', 'label' => 'Shop'],
        ['type' => MeterType::DinRail, 'manufacturer' => 'Eastron', 'model' => 'SDM630', 'class' => '1', 'ct' => null, 'vt' => null, 'profile' => 'commercial', 'kw' => [4.0, 12.0], 'tariff' => 'Businessrate 2', 'label' => 'Tenant'],
        ['type' => MeterType::ThreePhase, 'manufacturer' => 'PMT', 'model' => 'MOL4000', 'class' => '0.5S', 'ct' => null, 'vt' => null, 'profile' => 'commercial', 'kw' => [12.0, 35.0], 'tariff' => 'Businessrate 3', 'label' => 'Office floor'],
        ['type' => MeterType::ThreePhase, 'manufacturer' => 'Elster', 'model' => 'A1140', 'class' => '1', 'ct' => null, 'vt' => null, 'profile' => 'commercial', 'kw' => [10.0, 28.0], 'tariff' => 'Businessrate 3', 'label' => 'Restaurant'],
        ['type' => MeterType::Ct, 'manufacturer' => 'PMT', 'model' => 'MOL4000-CT', 'class' => '0.5S', 'ct' => '400/5', 'vt' => null, 'profile' => 'commercial', 'kw' => [60.0, 180.0], 'tariff' => 'Miniflex', 'label' => 'Building main'],
        ['type' => MeterType::Ct, 'manufacturer' => 'Landis+Gyr', 'model' => 'E650', 'class' => '0.5S', 'ct' => '600/5', 'vt' => null, 'profile' => 'industrial', 'kw' => [90.0, 260.0], 'tariff' => 'Miniflex', 'label' => 'Workshop'],
        ['type' => MeterType::CtVt, 'manufacturer' => 'Elster', 'model' => 'A1800', 'class' => '0.2S', 'ct' => '200/5', 'vt' => '11000/110', 'profile' => 'industrial', 'kw' => [350.0, 900.0], 'tariff' => 'Megaflex', 'label' => 'MV intake'],
    ];

    private CarbonImmutable $end;

    public function run(): void
    {
        mt_srand(4242);
        fake()->seed(4242);

        // Readings run up to the last completed half-hour.
        $now = CarbonImmutable::now();
        $this->end = $now->setTime($now->hour, $now->minute >= 30 ? 30 : 0);

        $meterSeq = 0;
        $readings = [];

        foreach (self::HEADENDS as $h => [$name, $site, $city]) {
            $headendStatus = $h === 6 ? DeviceStatus::Degraded : DeviceStatus::Online;

            $headend = Headend::create([
                'code' => sprintf('HE-%02d', $h + 1),
                'name' => $name,
                'site' => $site,
                'city' => $city,
                'protocol' => 'DLMS/COSEM',
                'ip_address' => sprintf('10.%d.0.10', 20 + $h),
                'poll_interval_min' => 30,
                'status' => $headendStatus,
                'last_poll_at' => $this->end->subMinutes(mt_rand(0, 6)),
            ]);

            for ($g = 0; $g < 3; $g++) {
                $offline = $h === 6 && $g === 2;

                $gateway = Gateway::create([
                    'headend_id' => $headend->id,
                    'serial' => sprintf('GW-%d%02d%d', 41, $h + 1, $g + 1),
                    'name' => ["{$site} – Block A", "{$site} – Block B", "{$site} – Plant room"][$g],
                    'comms' => ['HD-PLC/RF', 'HD-PLC/RF', 'LTE'][$g],
                    'firmware' => ['4.3.2', '4.3.2', '4.2.1'][($h + $g) % 3],
                    'signal_dbm' => mt_rand(-92, -58),
                    'status' => $offline ? DeviceStatus::Offline : DeviceStatus::Online,
                    'last_seen_at' => $offline ? $this->end->subDays(3)->subHours(5) : $this->end->subMinutes(mt_rand(0, 10)),
                ]);

                for ($m = 0; $m < 3; $m++) {
                    $meterSeq++;
                    $arch = self::ARCHETYPES[($g * 3 + $m + $h) % count(self::ARCHETYPES)];
                    $hasPv = in_array($arch['profile'], ['commercial', 'residential'], true) && mt_rand(1, 100) <= 30;

                    // A few meters with problems so the network isn't perfect.
                    $status = match (true) {
                        $offline => MeterStatus::NoComms,
                        $meterSeq === 17 => MeterStatus::Fault,
                        $meterSeq === 41 => MeterStatus::NoComms,
                        default => MeterStatus::Active,
                    };
                    $gapFrom = match (true) {
                        $offline => $this->end->subDays(3)->subHours(5),
                        $meterSeq === 41 => $this->end->subDays(1)->subHours(7),
                        default => null,
                    };

                    $meter = Meter::create([
                        'gateway_id' => $gateway->id,
                        'serial' => sprintf('2241%06d', 100000 + $meterSeq * 137),
                        'name' => "{$arch['label']} ".($arch['label'] === 'Unit' || $arch['label'] === 'Apartment' ? mt_rand(1, 48) : chr(65 + $m)).' – '.$site,
                        'account' => sprintf('ACC-%05d', 10000 + $meterSeq * 7),
                        'type' => $arch['type'],
                        'manufacturer' => $arch['manufacturer'],
                        'model' => $arch['model'],
                        'accuracy_class' => $arch['class'],
                        'ct_ratio' => $arch['ct'],
                        'vt_ratio' => $arch['vt'],
                        'tariff' => $arch['tariff'],
                        'has_generation' => $hasPv,
                        'status' => $status,
                        'installed_at' => $this->end->subDays(mt_rand(200, 1400))->toDateString(),
                        'last_reading_at' => $gapFrom ?? $this->end,
                    ]);

                    $kw = $arch['kw'][0] + (mt_rand() / mt_getrandmax()) * ($arch['kw'][1] - $arch['kw'][0]);
                    $this->profile($readings, $meter, $arch['profile'], $kw, $hasPv, $gapFrom, $status === MeterStatus::Fault);

                    if (count($readings) >= 6000) {
                        $this->flush($readings);
                    }
                }
            }
        }

        $this->flush($readings);

        $this->events();
    }

    /**
     * Insert buffered readings in chunks that stay under SQLite's bound-parameter limit.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function flush(array &$rows): void
    {
        foreach (array_chunk($rows, 2000) as $chunk) {
            DB::table('readings')->insert($chunk);
        }

        $rows = [];
    }

    /**
     * Append one meter's half-hourly profile to $rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function profile(array &$rows, Meter $meter, string $profile, float $baseKw, bool $pv, ?CarbonImmutable $gapFrom, bool $faulty): void
    {
        $phases = $meter->type->phases();
        $nominalV = $meter->vt_ratio !== null ? 63.5 : 230.0;
        $pfBase = match ($profile) {
            'industrial' => 0.86,
            'commercial' => 0.93,
            default => 0.97,
        };
        $pvPeakKw = $pv ? $baseKw * (0.6 + mt_rand(0, 40) / 100) : 0.0;
        $start = $this->end->subDays(self::DAYS);

        for ($t = $start->addMinutes(30); $t <= $this->end; $t = $t->addMinutes(30)) {
            if ($gapFrom !== null && $t > $gapFrom) {
                break;
            }

            // Random comms dropouts (~0.4%) leave holes in the profile.
            if (mt_rand(1, 1000) <= 4) {
                continue;
            }

            $hour = $t->hour + $t->minute / 60 - 0.25; // midpoint of the interval
            $weekday = $t->dayOfWeekIso; // 1 = Mon … 7 = Sun
            $shape = $this->shape($profile, $hour, $weekday);
            $kw = max(0.05, $baseKw * $shape * (1 + $this->noise() * 0.08));

            $solar = 0.0;
            if ($pv && $hour > 6.5 && $hour < 18) {
                $cloud = 0.55 + 0.45 * abs(sin(crc32($t->toDateString()) % 97));
                $solar = $pvPeakKw * max(0, sin(M_PI * ($hour - 6.5) / 11.5)) * $cloud;
            }

            $net = $kw - $solar;
            $importKwh = max(0, $net) * 0.5;
            $exportKwh = max(0, -$net) * 0.5;
            $pf = min(0.995, max(0.7, $pfBase + $this->noise() * 0.02));
            $kvarh = $importKwh * tan(acos($pf));

            $v = [];
            for ($p = 0; $p < 3; $p++) {
                $sag = mt_rand(1, 4000) === 1 ? 0.82 : 1.0;
                $v[$p] = $p < $phases ? round($nominalV * (1.0 + $this->noise() * 0.012 - 0.01 * $shape / 2) * $sag, 2) : null;
            }

            $kva = $pf > 0 ? max(0, $net) / $pf : 0;
            $amps = $phases === 1
                ? ($v[0] ? $kva * 1000 / $v[0] : 0)
                : $kva * 1000 / (3 * $nominalV * ($meter->vt_ratio !== null ? 11000 / 110 : 1));

            $current = [];
            for ($p = 0; $p < 3; $p++) {
                $current[$p] = $p < $phases ? round($amps * (1 + $this->noise() * 0.05), 2) : null;
            }

            $quality = $faulty && $t > $this->end->subDays(9) ? 'estimated' : (mt_rand(1, 400) === 1 ? 'estimated' : 'actual');

            $rows[] = [
                'meter_id' => $meter->id,
                'read_at' => $t->toDateTimeString(),
                'kwh_import' => round($importKwh, 3),
                'kwh_export' => round($exportKwh, 3),
                'kvarh_import' => round($kvarh, 3),
                'kvarh_export' => 0,
                'kva_demand' => round($kva, 3),
                'power_factor' => round($pf, 3),
                'voltage_l1' => $v[0],
                'voltage_l2' => $v[1],
                'voltage_l3' => $v[2],
                'current_l1' => $current[0],
                'current_l2' => $current[1],
                'current_l3' => $current[2],
                'quality' => $quality,
            ];
        }
    }

    /**
     * Relative load (≈0.1–1.0) by profile, time of day and weekday.
     */
    private function shape(string $profile, float $hour, int $weekday): float
    {
        $weekend = $weekday >= 6;
        $bump = fn (float $center, float $width) => exp(-(($hour - $center) ** 2) / (2 * $width ** 2));

        return match ($profile) {
            'residential' => 0.18 + 0.55 * $bump(7.0, 1.2) + 0.85 * $bump(19.0, 2.0) + ($weekend ? 0.2 * $bump(12.5, 3) : 0),
            'industrial' => $weekday === 7
                ? 0.35
                : ($weekend
                    ? 0.4 + 0.25 * $bump(10.0, 2.5)
                    : 0.35 + 0.65 * ($hour > 6 && $hour < 22 ? 1 : 0) * (0.85 + 0.15 * $bump(11.0, 3))),
            default => $weekend
                ? 0.22 + ($weekday === 6 ? 0.3 * $bump(11.0, 2.5) : 0)
                : 0.2 + 0.8 * $bump(11.5, 3.2) + 0.1 * $bump(15.5, 1.5),
        };
    }

    private function noise(): float
    {
        return (mt_rand() / mt_getrandmax()) * 2 - 1;
    }

    private function events(): void
    {
        $catalog = [
            ['power_fail', EventSeverity::Warning, 'Power failure on all phases'],
            ['power_restore', EventSeverity::Info, 'Supply restored'],
            ['voltage_sag', EventSeverity::Warning, 'Voltage sag below 90% of nominal'],
            ['reverse_energy', EventSeverity::Warning, 'Reverse energy flow detected'],
            ['tamper', EventSeverity::Critical, 'Terminal cover opened'],
            ['ct_reversed', EventSeverity::Critical, 'CT polarity reversed on L2'],
            ['clock_sync', EventSeverity::Info, 'Clock synchronised with head-end'],
            ['comms_lost', EventSeverity::Critical, 'No response from meter'],
        ];

        $meters = Meter::query()->get(['id', 'type', 'status', 'last_reading_at']);
        $rows = [];

        foreach ($meters as $meter) {
            $count = mt_rand(1, 6);

            for ($i = 0; $i < $count; $i++) {
                [$type, $severity, $message] = $catalog[mt_rand(0, 6)];

                if ($type === 'ct_reversed' && ! in_array($meter->type, [MeterType::Ct, MeterType::CtVt], true)) {
                    [$type, $severity, $message] = $catalog[6];
                }

                $at = $this->end->subMinutes(mt_rand(30, self::DAYS * 1440));
                $rows[] = $this->eventRow($meter->id, $at, $type, $severity, $message, $at < $this->end->subDays(5));
            }

            if ($meter->status === MeterStatus::NoComms && $meter->last_reading_at) {
                $rows[] = $this->eventRow($meter->id, $meter->last_reading_at->addMinutes(40), 'comms_lost', EventSeverity::Critical, 'No response from meter', false);
            }

            if ($meter->status === MeterStatus::Fault) {
                $rows[] = $this->eventRow($meter->id, $this->end->subDays(9), 'self_check', EventSeverity::Critical, 'Measurement self-check failed; readings estimated', false);
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['occurred_at'], $b['occurred_at']));
        DB::table('meter_events')->insert($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRow(int $meterId, CarbonImmutable $at, string $type, EventSeverity $severity, string $message, bool $acknowledged): array
    {
        return [
            'meter_id' => $meterId,
            'occurred_at' => $at->toDateTimeString(),
            'type' => $type,
            'severity' => $severity->value,
            'message' => $message,
            'acknowledged' => $acknowledged,
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ];
    }
}
