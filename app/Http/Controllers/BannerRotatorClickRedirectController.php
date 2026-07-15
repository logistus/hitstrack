<?php

namespace App\Http\Controllers;

use App\Models\BannerRotator;
use Illuminate\Http\RedirectResponse;

class BannerRotatorClickRedirectController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        $rotator = BannerRotator::query()
            ->where('rotator_slug', $slug)
            ->firstOrFail();

        $banner = $rotator->banners()
            ->whereKey($rotator->current_banner_id)
            ->first();

        abort_if(! $banner, 404);

        return redirect()->to(route('bannertrackers.click', [
            'slug' => $banner->banner_slug,
            'rotator' => $rotator->rotator_slug,
        ]));
    }
}
