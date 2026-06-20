<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerClickRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $banner = Banner::query()
            ->where('banner_slug', $slug)
            ->firstOrFail();

        $this->recordEvent($request, $banner, 'click');

        return redirect()->away($banner->target_url);
    }

    protected function recordEvent(Request $request, Banner $banner, string $eventType): void
    {
        $banner->stats()->create([
            'event_type' => $eventType,
            'page_url' => $request->query('page_url'),
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...ClientInfo::fromRequest($request),
        ]);
    }
}
