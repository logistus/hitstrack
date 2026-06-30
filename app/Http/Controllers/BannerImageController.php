<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Support\ClientInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerImageController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
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

        // Görseli sunucu üzerinden proxy'lemek yerine, kullanıcının
        // tarayıcısını doğrudan kaynak URL'e yönlendiriyoruz. Böylece:
        // - Görsel kullanıcının kendi IP'sinden çekilir (datacenter IP
        //   engellemelerine takılmaz).
        // - Sunucu bant genişliği/CPU'su harcanmaz.
        // - Kaynak sitenin kendi hotlink/Referer kontrolleri tarayıcı
        //   bağlamında normal şekilde çalışır.
        return redirect()->away($banner->image_url, 302, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
