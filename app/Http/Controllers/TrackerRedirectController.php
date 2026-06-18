<?php

namespace App\Http\Controllers;

use App\Events\TrackerStatsUpdated;
use App\Models\Tracker;
use App\Models\TrackerStat;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrackerRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $tracker = Tracker::query()
            ->where('tracker_slug', $slug)
            ->firstOrFail();

        $clientInfo = ClientInfo::fromRequest($request);

        TrackerStat::create([
            'tracker_id' => $tracker->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        return redirect()->away($tracker->target_url);
    }
}
