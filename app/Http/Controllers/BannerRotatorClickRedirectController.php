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
            ?? $rotator->pickNextBanner();

        abort_if(! $banner, 404);

        $banner->stats()->create([
            'banner_rotator_id' => $rotator->id,
            'event_type' => 'click',
            'ref_url' => ClientInfo::referrerDomain($request),
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

    private function selectedBannerCookieName(int $rotatorId): string
    {
        return "banner_rotator_{$rotatorId}_selected_banner";
    }
}
