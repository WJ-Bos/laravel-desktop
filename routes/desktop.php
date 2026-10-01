<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\HeadendController;
use App\Http\Controllers\Api\LayoutController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\MeterEventController;
use App\Http\Controllers\Api\NetworkController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\DesktopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop shell + JSON API
|--------------------------------------------------------------------------
|
| The API is session-authenticated (same cookie as the Inertia app) and
| CSRF-protected, so the SPA calls it with the XSRF-TOKEN cookie.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DesktopController::class)->name('dashboard');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]))->name('ping');

        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('search', SearchController::class)->name('search');
        Route::get('lookups', [LookupController::class, 'index'])->name('lookups');
        Route::get('network', NetworkController::class)->name('network');

        Route::get('layouts/{key}', [LayoutController::class, 'show'])->where('key', LayoutController::KEY_PATTERN)->name('layouts.show');
        Route::put('layouts/{key}', [LayoutController::class, 'update'])->where('key', LayoutController::KEY_PATTERN)->name('layouts.update');
        Route::delete('layouts/{key}', [LayoutController::class, 'destroy'])->where('key', LayoutController::KEY_PATTERN)->name('layouts.destroy');

        Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');

        // Editable network assets.
        $grids = [
            'headends' => [HeadendController::class, 'headend'],
            'gateways' => [GatewayController::class, 'gateway'],
            'meters' => [MeterController::class, 'meter'],
        ];

        foreach ($grids as $uri => [$controller, $param]) {
            Route::get("{$uri}/export", [$controller, 'export'])->name("{$uri}.export");
            Route::post("{$uri}/{{$param}}/duplicate", [$controller, 'duplicate'])->name("{$uri}.duplicate");
            Route::apiResource($uri, $controller);
        }

        Route::get('meters/{meter}/profile', [MeterController::class, 'profile'])->name('meters.profile');

        // Read-only data.
        Route::get('readings', [ReadingController::class, 'index'])->name('readings.index');
        Route::get('readings/export', [ReadingController::class, 'export'])->name('readings.export');
        Route::get('events', [MeterEventController::class, 'index'])->name('events.index');
        Route::get('events/export', [MeterEventController::class, 'export'])->name('events.export');
        Route::patch('events/{event}', [MeterEventController::class, 'acknowledge'])->name('events.acknowledge');
    });
});
