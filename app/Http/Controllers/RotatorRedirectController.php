<?php

namespace App\Http\Controllers;

use App\Models\Rotator;
use App\Models\RotatorStat;
use App\Models\TrackerStat;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RotatorRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $rotator = Rotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $tracker = $rotator->pickNextTracker();

        abort_if(! $tracker, 404);

        $clientInfo = ClientInfo::fromRequest($request);

        RotatorStat::create([
            'rotator_id' => $rotator->id,
            'tracker_id' => $tracker->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        TrackerStat::create([
            'tracker_id' => $tracker->id,
            'rotator_id' => $rotator->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        return redirect()->away($tracker->target_url);
    }
}
