<?php

use App\Enums\MeterStatus;
use App\Enums\MeterType;
use App\Models\Gateway;
use App\Models\Headend;
use App\Models\Meter;
use App\Models\MeterEvent;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    $this->actingAs(User::factory()->create());

    $this->headend = Headend::factory()->create(['code' => 'HE-01', 'name' => 'Century City']);
    $this->gateways = Gateway::factory()->count(3)->for($this->headend)->sequence(fn ($s) => ['serial' => 'GW-'.($s->index + 1)])->create();

    // 9 meters per headend: 3 per gateway.
    $this->meters = collect();
    foreach ($this->gateways as $g => $gateway) {
        foreach (range(1, 3) as $m) {
            $this->meters->push(Meter::factory()->for($gateway)->create([
                'serial' => sprintf('22410000%02d', $g * 3 + $m),
                'type' => $m === 3 ? MeterType::Ct : MeterType::ThreePhase,
                'ct_ratio' => $m === 3 ? '400/5' : null,
            ]));
        }
    }

    // One day of half-hourly readings: 1 kWh per interval (2 kW average).
    $rows = [];
    foreach ($this->meters as $meter) {
        for ($i = 1; $i <= 48; $i++) {
            $rows[] = [
                'meter_id' => $meter->id,
                'read_at' => now()->setTime(0, 0)->subDay()->addMinutes(30 * $i)->toDateTimeString(),
                'kwh_import' => 1, 'kwh_export' => 0, 'kvarh_import' => 0.3, 'kvarh_export' => 0,
                'kva_demand' => 2.1, 'power_factor' => 0.95,
                'voltage_l1' => 230, 'voltage_l2' => 231, 'voltage_l3' => 229,
                'current_l1' => 3, 'current_l2' => 3, 'current_l3' => 3,
                'quality' => 'actual',
            ];
        }
    }
    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table('readings')->insert($chunk);
    }
});

it('pages, sorts and filters the readings grid', function () {
    $this->getJson('/api/readings?start=0&end=50')->assertOk()->assertJsonPath('meta.total', 9 * 48)->assertJsonCount(50, 'data');

    $meter = $this->meters->first();
    $this->getJson('/api/readings?filter='.urlencode(json_encode(['meter_id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => $meter->id]])))
        ->assertOk()->assertJsonPath('meta.total', 48);

    $this->getJson('/api/readings?q=GW-2')->assertOk()->assertJsonPath('meta.total', 3 * 48);

    $this->getJson('/api/readings?filter='.urlencode(json_encode(['kw' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 2]])))
        ->assertOk()->assertJsonPath('meta.total', 9 * 48);
});

it('filters meters by type and sorts by serial', function () {
    $this->getJson('/api/meters?filter='.urlencode(json_encode(['type' => ['filterType' => 'set', 'values' => ['ct']]])))
        ->assertOk()->assertJsonPath('meta.total', 3);

    $this->getJson('/api/meters?sort='.urlencode(json_encode([['colId' => 'serial', 'sort' => 'desc']])))
        ->assertOk()->assertJsonPath('data.0.serial', '2241000009');
});

it('returns the network tree with 9 meters under the headend', function () {
    $tree = $this->getJson('/api/network')->assertOk()->json('data.0');

    expect($tree['code'])->toBe('HE-01')
        ->and($tree['gateways'])->toHaveCount(3)
        ->and(collect($tree['gateways'])->sum(fn ($g) => count($g['meters'])))->toBe(9);
});

it('shows a headend with its gateways and meters', function () {
    $this->getJson("/api/headends/{$this->headend->id}")
        ->assertOk()
        ->assertJsonPath('data.meters_count', 9)
        ->assertJsonCount(3, 'data.gateways')
        ->assertJsonCount(3, 'data.gateways.0.meters');
});

it('returns a meter load profile with summary figures', function () {
    $meter = $this->meters->first();

    $this->getJson("/api/meters/{$meter->id}/profile?from=2026-09-29&to=2026-09-29")
        ->assertOk()
        ->assertJsonPath('range.granularity', 'interval')
        ->assertJsonPath('summary.kwhImport', 47)
        ->assertJsonPath('summary.maxKva', 2.1);
});

it('validates meters, including CT ratio rules', function () {
    $gateway = $this->gateways->first();
    $base = ['gateway_id' => $gateway->id, 'manufacturer' => 'PMT', 'accuracy_class' => '1', 'status' => 'active'];

    $this->postJson('/api/meters', [...$base, 'serial' => 'abc', 'name' => 'X', 'type' => 'ct', 'model' => 'MOL4000-CT'])
        ->assertUnprocessable()->assertJsonValidationErrors(['serial', 'name', 'ct_ratio']);

    $this->postJson('/api/meters', [...$base, 'serial' => '2241999999', 'name' => 'New meter', 'type' => 'single_phase', 'model' => 'MOL1100', 'ct_ratio' => '100/5'])
        ->assertUnprocessable()->assertJsonValidationErrors(['ct_ratio']);

    $this->postJson('/api/meters', [...$base, 'serial' => '2241999999', 'name' => 'New meter', 'type' => 'single_phase', 'model' => 'MOL1100'])
        ->assertCreated()->assertJsonPath('data.type_label', 'Single-phase');
});

it('refuses to delete equipment that still has data or children', function () {
    $this->deleteJson("/api/meters/{$this->meters->first()->id}")->assertConflict();
    $this->deleteJson("/api/gateways/{$this->gateways->first()->id}")->assertConflict();
    $this->deleteJson("/api/headends/{$this->headend->id}")->assertConflict();
});

it('acknowledges events', function () {
    $event = MeterEvent::create(['meter_id' => $this->meters->first()->id, 'occurred_at' => now(), 'type' => 'tamper', 'severity' => 'critical', 'message' => 'Terminal cover opened']);

    $this->patchJson("/api/events/{$event->id}", ['acknowledged' => true])->assertOk()->assertJsonPath('data.acknowledged', true);
    $this->getJson('/api/events')->assertOk()->assertJsonPath('meta.total', 1);
});

it('builds the dashboard aggregates', function () {
    Meter::query()->update(['last_reading_at' => now()]);

    $data = $this->getJson('/api/dashboard?from=2026-09-29&to=2026-09-29')->assertOk()->json();

    // The midnight interval belongs to the next day, so 47 of the 48 fall on the 29th.
    expect($data['kpis']['energy']['value'])->toEqual(9 * 47)
        ->and($data['kpis']['peakDemand']['value'])->toEqual(18.9)
        ->and($data['network'][0])->toMatchArray(['meters' => 9, 'metersReporting' => 9]);
});

it('renders every report and exports CSV', function (string $type) {
    $this->getJson("/api/reports/{$type}?from=2026-09-29&to=2026-09-29")
        ->assertOk()
        ->assertJsonStructure(['title', 'columns', 'rows', 'chart', 'summary']);

    expect($this->get("/api/reports/{$type}/export?from=2026-09-29&to=2026-09-29")->assertOk()->streamedContent())->not->toBeEmpty();
})->with(array_keys(ReportService::TYPES));

it('reports missing readings per gateway', function () {
    $meter = $this->meters->first();
    $meter->update(['status' => MeterStatus::NoComms]);
    DB::table('readings')->where('meter_id', $meter->id)->delete();

    $rows = collect($this->getJson('/api/reports/data-completeness?from=2026-09-29&to=2026-09-29')->assertOk()->json('rows'));

    expect($rows->firstWhere('gateway', 'GW-1')['received'])->toBe(2 * 47)
        ->and($rows->first()['gateway'])->toBe('GW-1');
});
