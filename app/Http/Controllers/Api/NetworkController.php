<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Headend;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;

/**
 * The whole headend → gateway → meter hierarchy in one compact payload,
 * for the navigator tree.
 */
class NetworkController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $headends = Headend::query()
            ->with([
                'gateways' => fn ($q) => $q->orderBy('serial')->select('id', 'headend_id', 'serial', 'name', 'status'),
                'gateways.meters' => fn ($q) => $q->orderBy('serial')->select('id', 'gateway_id', 'serial', 'name', 'type', 'status'),
            ])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'city', 'status']);

        return response()->json([
            'data' => $headends->map(fn (Headend $h) => [
                'id' => $h->id,
                'code' => $h->code,
                'name' => $h->name,
                'city' => $h->city,
                'status' => $h->status->value,
                'gateways' => $h->gateways->map(fn (Gateway $g) => [
                    'id' => $g->id,
                    'serial' => $g->serial,
                    'name' => $g->name,
                    'status' => $g->status->value,
                    'meters' => $g->meters->map(fn (Meter $m) => [
                        'id' => $m->id,
                        'serial' => $m->serial,
                        'name' => $m->name,
                        'type' => $m->type->value,
                        'status' => $m->status->value,
                    ])->all(),
                ])->all(),
            ])->all(),
        ]);
    }
}
