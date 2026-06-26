<?php

namespace App\Http\Controllers;

use App\Jobs\SendGA4Event;
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

        $clientInfo = ClientInfo::fromRequest($request);

        $stat = LinkTrackerStat::create([
            'tracker_id' => $tracker->id,
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        SendGA4Event::dispatch('tracker_click', [
            'client_id'   => $this->resolveClientId($request),
            'tracker_id'  => $tracker->id,
            'destination' => $tracker->target_url,
            'country'     => $clientInfo['country_code'],
            'device_type' => $clientInfo['device_type'],
            'browser'     => $clientInfo['browser'],
            'os'          => $clientInfo['operating_system'],
            'referrer'    => ClientInfo::referrerDomain($request),
            'utm_source'  => $request->query('utm_source'),
            'utm_medium'  => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'click_id'    => $stat->id,
        ]);

        return redirect()->away($tracker->target_url);
    }

    private function resolveClientId(Request $request): string
    {
        $gaCookie = $request->cookie('_ga');

        if ($gaCookie && preg_match('/GA\d+\.\d+\.(.+)/', $gaCookie, $m)) {
            return $m[1];
        }

        return \Illuminate\Support\Str::uuid()->toString();
    }
}
