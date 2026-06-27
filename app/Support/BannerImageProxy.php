<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BannerImageProxy
{
    private const TRACKER_CACHE_TTL_DAYS = 7;

    public function responseFor(string $imageUrl): Response
    {
        $image = $this->fetchImage($imageUrl);

        if ($image === null) {
            return $this->badGateway();
        }

        return $this->imageResponse($image['body'], $image['content_type'], 'BYPASS');
    }

    public function cachedResponseFor(string $imageUrl): Response
    {
        $cacheKey = 'banner-image:v1:'.hash('sha256', $imageUrl);
        $cachedImage = Cache::get($cacheKey);

        if ($this->isCachedImage($cachedImage)) {
            return $this->imageResponse($cachedImage['body'], $cachedImage['content_type'], 'HIT');
        }

        $image = $this->fetchImage($imageUrl);

        if ($image === null) {
            return $this->badGateway();
        }

        Cache::put($cacheKey, $image, now()->addDays(self::TRACKER_CACHE_TTL_DAYS));

        return $this->imageResponse($image['body'], $image['content_type'], 'MISS');
    }

    private function fetchImage(string $imageUrl): ?array
    {
        if (! $this->isAllowedUrl($imageUrl)) {
            return null;
        }

        try {
            $remoteResponse = Http::timeout(8)
                ->connectTimeout(4)
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'User-Agent' => 'HitsTrack Banner Image Proxy',
                ])
                ->withOptions(['allow_redirects' => true])
                ->get($imageUrl)
                ->throw();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $contentType = $remoteResponse->header('Content-Type', 'application/octet-stream');

        if (! str_starts_with(strtolower($contentType), 'image/')) {
            return null;
        }

        return [
            'body' => $remoteResponse->body(),
            'content_type' => $contentType,
        ];
    }

    private function imageResponse(string $body, string $contentType, string $cacheStatus): Response
    {
        return response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Banner-Image-Cache' => $cacheStatus,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isCachedImage(mixed $image): bool
    {
        return is_array($image)
            && is_string($image['body'] ?? null)
            && is_string($image['content_type'] ?? null)
            && str_starts_with(strtolower($image['content_type']), 'image/');
    }

    private function isAllowedUrl(string $imageUrl): bool
    {
        $parts = parse_url($imageUrl);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    private function badGateway(): Response
    {
        return response('Banner image could not be loaded.', 502, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
