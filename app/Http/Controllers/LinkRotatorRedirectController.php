<?php

namespace App\Http\Controllers;

use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use App\Models\LinkTrackerStat;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkRotatorRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $rotator = LinkRotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $refUrl     = ClientInfo::referrerDomain($request);
        $tracker    = $rotator->pickNextTracker($refUrl);

        abort_if(! $tracker, 404);

        LinkRotatorStat::create([
            'rotator_id' => $rotator->id,
            'tracker_id' => $tracker->id,
            'ref_url'    => $refUrl,
            'ip_address' => $request->ip(),
        ]);

        LinkTrackerStat::create([
            'tracker_id' => $tracker->id,
            'rotator_id' => $rotator->id,
            'ref_url'    => $refUrl,
            'ip_address' => $request->ip(),
        ]);
        return redirect()->away($tracker->target_url);
    }
}
