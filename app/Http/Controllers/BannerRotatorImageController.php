<?php

namespace App\Http\Controllers;

use App\Models\BannerRotator;
use App\Support\BannerImageProxy;
use App\Support\ClientInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BannerRotatorImageController extends Controller
{
    public function __invoke(Request $request, string $slug, BannerImageProxy $imageProxy): Response
    {
        $rotator = BannerRotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $banner = $rotator->pickNextBanner();

        abort_if(! $banner, 404);

        $banner->stats()->create([
            'banner_rotator_id' => $rotator->id,
            'event_type' => 'impression',
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...ClientInfo::fromRequest($request),
        ]);

        return $imageProxy->responseFor($banner->image_url);
    }
}
