<?php

namespace App\Http\Controllers;

use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkTrackerRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $tracker = LinkTracker::query()
            ->where('tracker_slug', $slug)
            ->firstOrFail();

        LinkTrackerStat::create([
            'tracker_id' => $tracker->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
        ]);
        return redirect()->away($tracker->target_url);
    }
}
