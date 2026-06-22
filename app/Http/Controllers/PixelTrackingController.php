<?php

namespace App\Http\Controllers;

use App\Models\PixelStat;
use App\Support\ClientInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelTrackingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $clientInfo = ClientInfo::fromRequest($request);
        $pageUrl = $request->query('page_url');
        $refUrl = $request->query('ref_url');
        $referrerDomain = $request->query->has('ref_url')
            ? ClientInfo::domainFromUrl(is_string($refUrl) ? $refUrl : null)
            : ClientInfo::referrerDomain($request);

        PixelStat::create([
            'page_url' => is_string($pageUrl) ? $pageUrl : null,
            'ref_url' => $referrerDomain,
            'ip_address' => $request->ip(),
            'device_type' => $clientInfo['device_type'],
            'operating_system' => $clientInfo['operating_system'],
            'browser' => $clientInfo['browser'],
        ]);

        return response(base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='), 200)
            ->header('Content-Type', 'image/gif')
            ->header('Content-Length', '43')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
