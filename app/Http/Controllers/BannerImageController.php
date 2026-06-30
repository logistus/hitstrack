<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Support\BannerImageProxy;
use App\Support\ClientInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BannerImageController extends Controller
{
    public function __invoke(Request $request, string $slug, BannerImageProxy $imageProxy): Response
    {
        $banner = Banner::query()
            ->where('banner_slug', $slug)
            ->firstOrFail();

        $banner->stats()->create([
            'event_type' => 'impression',
            'ref_url' => ClientInfo::referrerDomain($request),
            'ip_address' => $request->ip(),
            ...ClientInfo::fromRequest($request),
        ]);

        return $banner->image_url;
    }
}
