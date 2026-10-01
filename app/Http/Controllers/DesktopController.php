<?php

namespace App\Http\Controllers;

use App\Models\UserLayout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the single-page desktop shell. Saved layouts are sent with the
 * first response so the workspace restores without a loading flash.
 */
class DesktopController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $layouts = UserLayout::query()
            ->where('user_id', $request->user()?->id)
            ->get(['key', 'data'])
            ->mapWithKeys(fn (UserLayout $layout) => [$layout->key => $layout->data]);

        return Inertia::render('Desktop', [
            'layouts' => (object) $layouts->all(),
        ]);
    }
}
