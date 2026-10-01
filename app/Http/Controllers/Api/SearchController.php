<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Headend;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Record search for the Ctrl+K command palette.
 */
class SearchController extends Controller
{
    private const PER_TYPE = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = "%{$term}%";
        $results = [];

        Headend::query()
            ->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('city', 'like', $like))
            ->orderBy('code')->limit(self::PER_TYPE)->get()
            ->each(function (Headend $h) use (&$results) {
                $results[] = ['type' => 'headend', 'id' => $h->id, 'title' => "{$h->code} {$h->name}", 'subtitle' => "{$h->city} · {$h->status->value}"];
            });

        Gateway::query()->with('headend:id,code')
            ->where(fn ($q) => $q->where('serial', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('serial')->limit(self::PER_TYPE)->get()
            ->each(function (Gateway $g) use (&$results) {
                $results[] = ['type' => 'gateway', 'id' => $g->id, 'title' => $g->serial, 'subtitle' => "{$g->name} · {$g->headend->code}"];
            });

        Meter::query()
            ->where(fn ($q) => $q->where('serial', 'like', $like)->orWhere('name', 'like', $like)->orWhere('account', 'like', $like))
            ->orderBy('serial')->limit(self::PER_TYPE * 2)->get()
            ->each(function (Meter $m) use (&$results) {
                $results[] = ['type' => 'meter', 'id' => $m->id, 'title' => $m->serial, 'subtitle' => "{$m->name} · {$m->type->label()} · {$m->status->value}"];
            });

        return response()->json(['data' => $results]);
    }
}
