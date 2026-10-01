<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLayoutRequest;
use App\Models\UserLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Per-user UI state: the dockview workspace ("desktop"), the dashboard widget
 * grid ("dashboard") and per-grid column state ("grid:orders", ...).
 */
class LayoutController extends Controller
{
    public const KEY_PATTERN = '[a-z0-9][a-z0-9:_-]{0,49}';

    public function show(Request $request, string $key): JsonResponse
    {
        $layout = UserLayout::query()
            ->where('user_id', $request->user()?->id)
            ->where('key', $key)
            ->first();

        return response()->json([
            'key' => $key,
            'data' => $layout?->data,
            'updated_at' => $layout?->updated_at?->toIso8601String(),
        ]);
    }

    public function update(SaveLayoutRequest $request, string $key): JsonResponse
    {
        $layout = UserLayout::query()->updateOrCreate(
            ['user_id' => $request->user()?->id, 'key' => $key],
            ['data' => $request->validated('data')],
        );

        return response()->json([
            'key' => $key,
            'data' => $layout->data,
            'updated_at' => $layout->updated_at?->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, string $key): Response
    {
        UserLayout::query()
            ->where('user_id', $request->user()?->id)
            ->where(fn ($q) => $q->where('key', $key)->orWhere('key', 'like', $key.':%'))
            ->delete();

        return response()->noContent();
    }
}
