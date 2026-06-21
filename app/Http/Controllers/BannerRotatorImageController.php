<?php

namespace App\Http\Controllers;

use App\Models\BannerRotator;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerRotatorImageController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $rotator = BannerRotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $banner = $rotator->pickNextBanner();

        abort_if(! $banner, 404);

        $banner->stats()->create([
            'banner_rotator_id' => $rotator->id,
            'event_type' => 'impression',
            'page_url' => $request->query('page_url'),
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...ClientInfo::fromRequest($request),
        ]);

        return redirect()
            ->away($banner->image_url)
            ->withCookie(cookie(
                name: $this->selectedBannerCookieName($rotator->id),
                value: (string) $banner->id,
                minutes: 30,
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: $request->isSecure() ? 'None' : 'Lax',
            ));
    }

    private function selectedBannerCookieName(int $rotatorId): string
    {
        return "banner_rotator_{$rotatorId}_selected_banner";
    }
}
