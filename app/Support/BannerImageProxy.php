<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class BannerImageProxy
{
    public function responseFor(string $imageUrl): Response
    {
        if (! $this->isAllowedUrl($imageUrl)) {
            return $this->badGateway();
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
            return $this->badGateway();
        }

        $contentType = $remoteResponse->header('Content-Type', 'application/octet-stream');

        if (! str_starts_with(strtolower($contentType), 'image/')) {
            return $this->badGateway();
        }

        return response($remoteResponse->body(), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
