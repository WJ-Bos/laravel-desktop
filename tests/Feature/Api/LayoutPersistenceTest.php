<?php

use App\Models\User;
use App\Models\UserLayout;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

$dockLayout = [
    'grid' => ['root' => ['type' => 'branch', 'data' => []], 'width' => 1200, 'height' => 800, 'orientation' => 'HORIZONTAL'],
    'panels' => [
        'dashboard' => ['id' => 'dashboard', 'contentComponent' => 'dashboard', 'title' => 'Dashboard'],
        'orders' => ['id' => 'orders', 'contentComponent' => 'grid', 'params' => ['module' => 'orders'], 'title' => 'Orders'],
    ],
    'activeGroup' => '1',
];

it('returns an empty layout before anything is saved', function () {
    $this->getJson('/api/layouts/desktop')
        ->assertOk()
        ->assertJson(['key' => 'desktop', 'data' => null]);
});

it('saves and restores a layout', function () use ($dockLayout) {
    $this->putJson('/api/layouts/desktop', ['data' => $dockLayout])
        ->assertOk()
        ->assertJsonPath('data.panels.orders.params.module', 'orders');

    $this->getJson('/api/layouts/desktop')
        ->assertOk()
        ->assertJsonPath('data', $dockLayout);
});

it('overwrites the previous layout instead of duplicating it', function () use ($dockLayout) {
    $this->putJson('/api/layouts/desktop', ['data' => $dockLayout])->assertOk();
    $this->putJson('/api/layouts/desktop', ['data' => ['panels' => []]])->assertOk();

    expect(UserLayout::where('user_id', $this->user->id)->count())->toBe(1);
    $this->getJson('/api/layouts/desktop')->assertJsonPath('data.panels', []);
});

it('keeps layouts separate per user', function () use ($dockLayout) {
    $this->putJson('/api/layouts/desktop', ['data' => $dockLayout])->assertOk();

    $this->actingAs(User::factory()->create());
    $this->getJson('/api/layouts/desktop')->assertJsonPath('data', null);
});

it('stores several layouts per user by key', function () {
    $this->putJson('/api/layouts/dashboard', ['data' => ['widgets' => [['i' => 'revenue', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]]]])->assertOk();
    $this->putJson('/api/layouts/grid:orders', ['data' => ['columns' => [['colId' => 'number', 'width' => 120]]]])->assertOk();

    $this->getJson('/api/layouts/dashboard')->assertJsonPath('data.widgets.0.i', 'revenue');
    $this->getJson('/api/layouts/grid:orders')->assertJsonPath('data.columns.0.colId', 'number');
});

it('resets a layout and its sub-keys', function () use ($dockLayout) {
    $this->putJson('/api/layouts/desktop', ['data' => $dockLayout])->assertOk();
    $this->putJson('/api/layouts/grid:orders', ['data' => ['columns' => []]])->assertOk();
    $this->putJson('/api/layouts/grid', ['data' => ['x' => 1]])->assertOk();

    $this->deleteJson('/api/layouts/desktop')->assertNoContent();
    $this->getJson('/api/layouts/desktop')->assertJsonPath('data', null);

    $this->deleteJson('/api/layouts/grid')->assertNoContent();
    expect(UserLayout::count())->toBe(0);
});

it('passes saved layouts to the desktop page', function () use ($dockLayout) {
    $this->putJson('/api/layouts/desktop', ['data' => $dockLayout])->assertOk();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Desktop')
            ->where('layouts.desktop.panels.orders.title', 'Orders'));
});

it('validates the payload', function () {
    $this->putJson('/api/layouts/desktop', [])->assertUnprocessable()->assertJsonValidationErrors('data');
    $this->putJson('/api/layouts/desktop', ['data' => 'not-an-object'])->assertUnprocessable()->assertJsonValidationErrors('data');
    $this->putJson('/api/layouts/desktop', ['data' => ['blob' => str_repeat('x', 300_000)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['data' => 'too large']);
});

it('rejects invalid layout keys', function () {
    $this->putJson('/api/layouts/'.urlencode('../etc'), ['data' => ['a' => 1]])->assertNotFound();
    $this->putJson('/api/layouts/UPPER', ['data' => ['a' => 1]])->assertNotFound();
});

it('requires authentication', function () {
    auth()->logout();

    $this->getJson('/api/layouts/desktop')->assertUnauthorized();
    $this->putJson('/api/layouts/desktop', ['data' => ['a' => 1]])->assertUnauthorized();
});
