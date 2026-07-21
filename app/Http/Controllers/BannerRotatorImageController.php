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

        $refUrl = ClientInfo::referrerDomain($request); // tek sefer hesapla
        $banner = $rotator->pickNextBanner($refUrl);

        abort_if(! $banner, 404);

        $rotator->forceFill(['current_banner_id' => $banner->id])->saveQuietly();

        $banner->stats()->create([
            'banner_rotator_id' => $rotator->id,
            'event_type' => 'impression',
            'ref_url' => $refUrl,
        ]);

        return redirect()->away($banner->image_url);
    }
}
