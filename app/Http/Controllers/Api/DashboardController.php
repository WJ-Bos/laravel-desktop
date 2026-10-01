<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DateRangeRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DateRangeRequest $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->summary($request->from(), $request->to()));
    }
}
