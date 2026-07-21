<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\BannerRotator;
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
        if (ClientInfo::isExcludedReferrer($request)) {
            return;
        }

        $rotator = $this->rotatorContext($request, $banner);

        $banner->stats()->create([
            'banner_rotator_id' => $rotator?->id,
            'event_type' => $eventType,
            'ref_url' => ClientInfo::referrerDomain($request),
        ]);
    }

    private function rotatorContext(Request $request, Banner $banner): ?BannerRotator
    {
        $rotatorSlug = $request->query('rotator');

        if (! is_string($rotatorSlug) || $rotatorSlug === '') {
            return null;
        }

        return BannerRotator::query()
            ->where('rotator_slug', $rotatorSlug)
            ->where('current_banner_id', $banner->id)
            ->whereHas('banners', fn($query) => $query->whereKey($banner->id))
            ->first();
    }
}
