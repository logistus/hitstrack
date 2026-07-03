<?php

namespace App\Http\Controllers;

use App\Jobs\SendGA4Event;
use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use App\Models\LinkTrackerStat;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LinkRotatorRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $rotator = LinkRotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $clientInfo = ClientInfo::fromRequest($request);
        $clientId   = $this->resolveClientId($request);
        $refUrl     = ClientInfo::referrerDomain($request);
        $tracker    = $rotator->pickNextTracker($refUrl);

        abort_if(! $tracker, 404);

        $rotatorStat = LinkRotatorStat::create([
            'rotator_id' => $rotator->id,
            'tracker_id' => $tracker->id,
            'ref_url'    => $refUrl,
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);

        LinkTrackerStat::create([
            'tracker_id' => $tracker->id,
            'rotator_id' => $rotator->id,
            'ref_url'    => $refUrl,
            'ip_address' => $request->ip(),
            ...$clientInfo,
        ]);
        /*
        SendGA4Event::dispatch('rotator_click', [
            'client_id'   => $clientId,
            'rotator_id'  => $rotator->id,
            'tracker_id'  => $tracker->id,
            'destination' => $tracker->target_url,
            'country'     => $clientInfo['country_code'],
            'device_type' => $clientInfo['device_type'],
            'browser'     => $clientInfo['browser'],
            'os'          => $clientInfo['operating_system'],
            'referrer'    => $refUrl,
            'utm_source'  => $request->query('utm_source'),
            'utm_medium'  => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'click_id'    => $rotatorStat->id,
        ]);
        */
        return redirect()->away($tracker->target_url);
    }

    private function resolveClientId(Request $request): string
    {
        $gaCookie = $request->cookie('_ga');

        if ($gaCookie && preg_match('/GA\d+\.\d+\.(.+)/', $gaCookie, $m)) {
            return $m[1];
        }

        return Str::uuid()->toString();
    }
}
