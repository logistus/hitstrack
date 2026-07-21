<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Support\ClientInfo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BannerImageController extends Controller
{
    public function __invoke(Request $request, string $slug): Response
    {
        $banner = Banner::query()
            ->where('banner_slug', $slug)
            ->firstOrFail();

        if (! ClientInfo::isExcludedReferrer($request)) {
            $banner->stats()->create([
                'event_type' => 'impression',
                'ref_url' => ClientInfo::referrerDomain($request),
            ]);
        }

        return new Response('', 302, [
            'Location' => $banner->image_url,
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
