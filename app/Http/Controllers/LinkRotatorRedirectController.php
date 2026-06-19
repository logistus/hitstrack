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

        $tracker = $rotator->pickNextTracker();

        abort_if(! $tracker, 404);

        $clientInfo = ClientInfo::fromRequest($request);

        LinkRotatorStat::create([
            'rotator_id' => $rotator->id,
            'tracker_id' => $tracker->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        LinkTrackerStat::create([
            'tracker_id' => $tracker->id,
            'rotator_id' => $rotator->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        return redirect()->away($tracker->target_url);
    }
}
