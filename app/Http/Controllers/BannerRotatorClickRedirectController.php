<?php

namespace App\Http\Controllers;

use App\Models\BannerRotator;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerRotatorClickRedirectController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $rotator = BannerRotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $banner = $this->selectedBannerFromCookie($request, $rotator)
            ?? $this->selectedBannerFromRecentImpression($request, $rotator)
            ?? $rotator->pickNextBanner();

        abort_if(! $banner, 404);

        $banner->stats()->create([
            'banner_rotator_id' => $rotator->id,
            'event_type' => 'click',
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...ClientInfo::fromRequest($request),
        ]);

        return redirect()->away($banner->target_url);
    }

    private function selectedBannerFromCookie(Request $request, BannerRotator $rotator)
    {
        $bannerId = $request->cookie($this->selectedBannerCookieName($rotator->id));

        if (! ctype_digit((string) $bannerId)) {
            return null;
        }

        return $rotator->banners()
            ->where('banners.id', (int) $bannerId)
            ->first();
    }

    private function selectedBannerFromRecentImpression(Request $request, BannerRotator $rotator)
    {
        $referrerDomain = ClientInfo::referrerDomain($request);

        $recentImpression = $rotator->stats()
            ->where('event_type', 'impression')
            ->where('ip_address', $request->ip())
            ->when(
                $referrerDomain,
                fn ($query) => $query->where('ref_url', $referrerDomain),
                fn ($query) => $query->whereNull('ref_url'),
            )
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();

        if (! $recentImpression) {
            return null;
        }

        return $rotator->banners()
            ->where('banners.id', $recentImpression->banner_id)
            ->first();
    }

    private function selectedBannerCookieName(int $rotatorId): string
    {
        return "banner_rotator_{$rotatorId}_selected_banner";
    }
}
