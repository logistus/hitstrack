<?php

namespace App\Support;

use Illuminate\Http\Request;

class ClientInfo
{
    private const EXCLUDED_REFERRER_PATHS = [
        'mysqbans.php',
        'mybanners.php',
    ];

    public static function isExcludedReferrer(Request $request): bool
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return false;
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);

        foreach (self::EXCLUDED_REFERRER_PATHS as $needle) {
            if (stripos($path, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function referrerDomain(Request $request): ?string
    {
        return self::domainFromUrl($request->headers->get('referer'));
    }

    public static function domainFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', strtolower($host));
    }

    /**
     * @return array{device_type: string|null, operating_system: string|null, browser: string|null, country_code: string|null}
     */
    public static function fromRequest(Request $request): array
    {
        $userAgent = $request->userAgent();

        if (! $userAgent) {
            return [
                'device_type' => null,
                'operating_system' => null,
                'browser' => null,
                'country_code' => self::countryCode($request),
            ];
        }

        return [
            'device_type' => self::deviceType($userAgent),
            'operating_system' => self::operatingSystem($userAgent),
            'browser' => self::browser($userAgent),
            'country_code' => self::countryCode($request),
        ];
    }

    private static function countryCode(Request $request): ?string
    {
        foreach (
            [
                'CF-IPCountry',
                'CloudFront-Viewer-Country',
                'X-Vercel-IP-Country',
                'X-Appengine-Country',
                'X-Country-Code',
                'X-Geo-Country',
                'X-Forwarded-Country',
            ] as $header
        ) {
            $countryCode = strtoupper((string) $request->headers->get($header));

            if (preg_match('/^[A-Z]{2}$/', $countryCode) === 1 && $countryCode !== 'XX') {
                return $countryCode;
            }
        }

        return null;
    }

    private static function deviceType(string $userAgent): string
    {
        if (preg_match('/ipad|tablet|playbook|silk|kindle|nexus 7|nexus 10|sm-t|tab/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/android/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    private static function operatingSystem(string $userAgent): string
    {
        return match (true) {
            preg_match('/windows nt/i', $userAgent) === 1 => 'Windows',
            preg_match('/cros/i', $userAgent) === 1 => 'Chrome OS',
            preg_match('/android/i', $userAgent) === 1 => 'Android',
            preg_match('/iphone|ipad|ipod/i', $userAgent) === 1 => 'iOS',
            preg_match('/mac os x|macintosh/i', $userAgent) === 1 => 'macOS',
            preg_match('/linux/i', $userAgent) === 1 => 'Linux',
            default => 'Other',
        };
    }

    private static function browser(string $userAgent): string
    {
        return match (true) {
            preg_match('/edg\//i', $userAgent) === 1 => 'Edge',
            preg_match('/opr\/|opera/i', $userAgent) === 1 => 'Opera',
            preg_match('/samsungbrowser/i', $userAgent) === 1 => 'Samsung Internet',
            preg_match('/firefox|fxios/i', $userAgent) === 1 => 'Firefox',
            preg_match('/crios|chrome/i', $userAgent) === 1 => 'Chrome',
            preg_match('/safari/i', $userAgent) === 1 => 'Safari',
            preg_match('/msie|trident/i', $userAgent) === 1 => 'Internet Explorer',
            default => 'Other',
        };
    }
}
