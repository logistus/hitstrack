<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BannerImageProxy
{
    /**
     * Bazı CDN'ler/anti-bot servisleri tarayıcı gibi görünmeyen
     * istemcileri (boş Referer, generik UA) engelliyor. Bu yüzden
     * gerçekçi bir tarayıcı UA'sı ve makul bir Referer kullanıyoruz.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const MAX_REDIRECTS = 5;

    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB güvenlik sınırı

    public function responseFor(string $imageUrl, ?string $refererUrl = null): Response
    {
        if (! $this->isAllowedUrl($imageUrl)) {
            return $this->badGateway('blocked_url');
        }

        try {
            $remoteResponse = Http::timeout(8)
                ->connectTimeout(4)
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'User-Agent' => self::USER_AGENT,
                    'Referer' => $refererUrl ?: $this->originOf($imageUrl),
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                // Redirect'leri kendimiz takip ediyoruz ki her adımda
                // SSRF kontrolünü (private IP, localhost vb.) tekrar uygulayabilelim.
                ->withOptions(['allow_redirects' => false])
                ->get($imageUrl);

            $remoteResponse = $this->followRedirects($remoteResponse, $imageUrl, $refererUrl);
        } catch (ConnectionException | RequestException $e) {
            Log::warning('banner_image_proxy.fetch_failed', [
                'url' => $imageUrl,
                'status' => $e instanceof RequestException ? $e->response?->status() : null,
                'message' => $e->getMessage(),
            ]);

            return $this->badGateway('fetch_failed');
        }

        if ($remoteResponse === null) {
            return $this->badGateway('too_many_redirects');
        }

        if ($remoteResponse->failed()) {
            Log::warning('banner_image_proxy.upstream_error', [
                'url' => $imageUrl,
                'status' => $remoteResponse->status(),
            ]);

            return $this->badGateway('upstream_' . $remoteResponse->status());
        }

        $contentType = strtolower((string) $remoteResponse->header('Content-Type', ''));
        $contentType = trim(explode(';', $contentType)[0] ?? '');

        if (! str_starts_with($contentType, 'image/')) {
            Log::warning('banner_image_proxy.invalid_content_type', [
                'url' => $imageUrl,
                'content_type' => $contentType,
            ]);

            return $this->badGateway('invalid_content_type');
        }

        $body = $remoteResponse->body();

        if (strlen($body) > self::MAX_BYTES) {
            return $this->badGateway('payload_too_large');
        }

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Redirect'leri elle takip eder, her hedef URL'i tekrar
     * isAllowedUrl() ile doğrular (SSRF koruması).
     */
    private function followRedirects(HttpResponse $response, string $currentUrl, ?string $refererUrl, int $depth = 0): ?HttpResponse
    {
        if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            return $response;
        }

        if ($depth >= self::MAX_REDIRECTS) {
            return null;
        }

        $location = $response->header('Location');

        if (! $location) {
            return $response;
        }

        $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);

        if (! $this->isAllowedUrl($nextUrl)) {
            Log::warning('banner_image_proxy.redirect_blocked', [
                'from' => $currentUrl,
                'to' => $nextUrl,
            ]);

            return null;
        }

        $next = Http::timeout(8)
            ->connectTimeout(4)
            ->withHeaders([
                'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'User-Agent' => self::USER_AGENT,
                'Referer' => $refererUrl ?: $this->originOf($currentUrl),
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->withOptions(['allow_redirects' => false])
            ->get($nextUrl);

        return $this->followRedirects($next, $nextUrl, $refererUrl, $depth + 1);
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($currentUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $path = $parts['path'] ?? '/';
        $dir = rtrim(dirname($path), '/');

        return "{$scheme}://{$host}{$port}{$dir}/{$location}";
    }

    private function originOf(string $url): string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return "{$parts['scheme']}://{$parts['host']}{$port}/";
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

        // Host bir IP olarak verilmişse private/reserved aralıkları engelle.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // Host bir domain ise, DNS'in private/reserved bir IP'ye çözülmediğinden emin ol
        // (DNS rebinding / internal network erişimini engellemek için).
        $resolvedIps = $this->resolveHostIps($host);

        if ($resolvedIps === []) {
            return false;
        }

        foreach ($resolvedIps as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function resolveHostIps(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }

    private function badGateway(string $reason = 'unknown'): Response
    {
        return response('Banner image could not be loaded.', 502, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Proxy-Error' => $reason,
        ]);
    }
}
