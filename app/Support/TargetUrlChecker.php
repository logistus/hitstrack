<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TargetUrlChecker
{
    private const MAX_REDIRECTS = 5;

    private const MAX_BODY_BYTES = 250000;

    public function check(string $url): array
    {
        $startedUrl = $this->normalizeUrl($url);

        $result = [
            'url' => $startedUrl,
            'final_url' => $startedUrl,
            'status' => 'unknown',
            'summary' => __('The URL could not be checked yet.'),
            'http_status' => null,
            'content_type' => null,
            'redirects' => [],
            'frame' => [
                'status' => 'unknown',
                'label' => __('Unknown'),
                'findings' => [],
            ],
            'security' => [
                'status' => 'unknown',
                'label' => __('Unknown'),
                'findings' => [],
            ],
            'headers' => [],
            'errors' => [],
        ];

        if (! $this->isAllowedUrl($startedUrl, $error)) {
            $result['status'] = 'danger';
            $result['summary'] = $error;
            $result['errors'][] = $error;
            $result['frame'] = [
                'status' => 'unknown',
                'label' => __('Not checked'),
                'findings' => [__('Frame behavior was not checked because the URL is not fetchable.')],
            ];
            $result['security'] = [
                'status' => 'danger',
                'label' => __('Blocked'),
                'findings' => [$error],
            ];

            return $result;
        }

        try {
            $response = $this->fetch($startedUrl, $result);
        } catch (ConnectionException $exception) {
            $result['status'] = 'danger';
            $result['summary'] = __('The URL could not be reached: :message', ['message' => $exception->getMessage()]);
            $result['errors'][] = $result['summary'];

            return $result;
        } catch (Throwable $exception) {
            $result['status'] = 'danger';
            $result['summary'] = __('The URL check failed: :message', ['message' => $exception->getMessage()]);
            $result['errors'][] = $result['summary'];

            return $result;
        }

        $headers = $this->normalizedHeaders($response);
        $body = Str::limit($response->body(), self::MAX_BODY_BYTES, '');
        $result['http_status'] = $response->status();
        $result['content_type'] = $headers['content-type'] ?? null;
        $result['headers'] = $headers;

        $result['frame'] = $this->checkFrameCompatibility($headers, $body);
        $result['security'] = $this->checkSuspiciousContent($startedUrl, $result['final_url'], $headers, $body);
        $result['status'] = $this->overallStatus($response, $result['frame']['status'], $result['security']['status']);
        $result['summary'] = $this->summaryFor($result['status'], $result['frame']['status'], $result['security']['status']);

        return $result;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return 'https://' . $url;
        }

        return $url;
    }

    private function fetch(string $url, array &$result): Response
    {
        $currentUrl = $url;

        for ($attempt = 0; $attempt <= self::MAX_REDIRECTS; $attempt++) {
            if (! $this->isAllowedUrl($currentUrl, $error)) {
                throw new ConnectionException($error);
            }

            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Range' => 'bytes=0-' . (self::MAX_BODY_BYTES - 1),
                    'User-Agent' => 'HitsTrack URL Checker/1.0',
                ])
                ->withOptions(['allow_redirects' => false])
                ->get($currentUrl);

            $result['final_url'] = $currentUrl;

            if (! $response->redirect()) {
                return $response;
            }

            $location = $response->header('Location');

            if (! $location) {
                return $response;
            }

            $nextUrl = $this->absoluteUrl($currentUrl, $location);

            $result['redirects'][] = [
                'from' => $currentUrl,
                'to' => $nextUrl,
                'status' => $response->status(),
            ];

            $currentUrl = $nextUrl;
        }

        throw new ConnectionException(__('Too many redirects. The checker stopped after :count redirects.', ['count' => self::MAX_REDIRECTS]));
    }

    private function isAllowedUrl(string $url, ?string &$error = null): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            $error = __('Only public http/https URLs can be checked.');

            return false;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, [80, 443], true)) {
            $error = __('Only standard HTTP/HTTPS ports can be checked.');

            return false;
        }

        $ips = $this->resolveHost($host);

        if ($ips === []) {
            $error = __('The host could not be resolved.');

            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $error = __('Private, local, or reserved network addresses cannot be checked.');

                return false;
            }
        }

        return true;
    }

    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);

        return collect($records)
            ->flatMap(fn (array $record) => array_filter([$record['ip'] ?? null, $record['ipv6'] ?? null]))
            ->unique()
            ->values()
            ->all();
    }

    private function absoluteUrl(string $baseUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $path = $base['path'] ?? '/';
        $directory = rtrim(str($path)->beforeLast('/')->toString(), '/');

        return "{$scheme}://{$host}{$port}{$directory}/{$location}";
    }

    private function normalizedHeaders(Response $response): array
    {
        return collect($response->headers())
            ->mapWithKeys(fn (array $value, string $key) => [strtolower($key) => implode(', ', $value)])
            ->all();
    }

    private function checkFrameCompatibility(array $headers, string $body): array
    {
        $findings = [];
        $status = 'safe';

        $xFrameOptions = strtolower($headers['x-frame-options'] ?? '');
        $csp = strtolower($headers['content-security-policy'] ?? '');
        $bodyLower = strtolower($body);

        if (str_contains($xFrameOptions, 'deny')) {
            $status = 'danger';
            $findings[] = __('X-Frame-Options is DENY, so this page blocks iframe embedding.');
        } elseif (str_contains($xFrameOptions, 'sameorigin')) {
            $status = 'danger';
            $findings[] = __('X-Frame-Options is SAMEORIGIN, so this page can only be framed by the same domain.');
        } elseif ($xFrameOptions !== '') {
            $status = 'warning';
            $findings[] = __('X-Frame-Options is present: :value', ['value' => $headers['x-frame-options']]);
        }

        if (preg_match('/frame-ancestors\s+([^;]+)/i', $headers['content-security-policy'] ?? '', $matches)) {
            $ancestors = trim($matches[1]);

            if (str_contains(strtolower($ancestors), "'none'") || str_contains(strtolower($ancestors), "'self'")) {
                $status = 'danger';
                $findings[] = __('Content-Security-Policy frame-ancestors is restrictive: :value', ['value' => $ancestors]);
            } else {
                $status = $status === 'safe' ? 'warning' : $status;
                $findings[] = __('Content-Security-Policy frame-ancestors is set: :value', ['value' => $ancestors]);
            }
        } elseif (str_contains($csp, 'frame-ancestors')) {
            $status = $status === 'safe' ? 'warning' : $status;
            $findings[] = __('Content-Security-Policy contains a frame-ancestors directive.');
        }

        $frameBreakerPatterns = [
            '/top\s*(!==|!=)\s*self/i',
            '/self\s*(!==|!=)\s*top/i',
            '/window\.top\.location/i',
            '/top\.location/i',
            '/parent\.location/i',
            '/frameElement/i',
            '/if\s*\(\s*top\s*!=/i',
        ];

        foreach ($frameBreakerPatterns as $pattern) {
            if (preg_match($pattern, $bodyLower)) {
                $status = $status === 'safe' ? 'warning' : $status;
                $findings[] = __('Possible JavaScript frame-breaker code was found in the page.');
                break;
            }
        }

        if ($findings === []) {
            $findings[] = __('No obvious iframe-blocking headers or frame-breaker scripts were found.');
        }

        return [
            'status' => $status,
            'label' => match ($status) {
                'danger' => __('Likely blocked'),
                'warning' => __('Needs manual test'),
                default => __('Likely embeddable'),
            },
            'findings' => $findings,
        ];
    }

    private function checkSuspiciousContent(string $startedUrl, string $finalUrl, array $headers, string $body): array
    {
        $findings = [];
        $status = 'safe';
        $bodyLower = strtolower($body);
        $contentType = strtolower($headers['content-type'] ?? '');

        if (! str_starts_with($startedUrl, 'https://')) {
            $status = 'warning';
            $findings[] = __('The URL does not use HTTPS.');
        }

        if (parse_url($startedUrl, PHP_URL_HOST) !== parse_url($finalUrl, PHP_URL_HOST)) {
            $status = 'warning';
            $findings[] = __('The URL redirects to a different host.');
        }

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            $status = $status === 'safe' ? 'warning' : $status;
            $findings[] = __('The response is not HTML: :type', ['type' => $headers['content-type']]);
        }

        $suspiciousPatterns = [
            '/eval\s*\(/i' => __('JavaScript eval() usage was found.'),
            '/atob\s*\(/i' => __('Base64 decoding JavaScript was found.'),
            '/document\.write\s*\(/i' => __('document.write() usage was found.'),
            '/fromcharcode/i' => __('String.fromCharCode-style obfuscation was found.'),
            '/<iframe[^>]+style=["\'][^"\']*(display\s*:\s*none|visibility\s*:\s*hidden|width\s*:\s*0|height\s*:\s*0)/i' => __('A hidden iframe pattern was found.'),
            '/onerror\s*=/i' => __('Inline onerror JavaScript handlers were found.'),
            '/navigator\.sendbeacon|XMLHttpRequest|fetch\s*\(/i' => __('Client-side network call code was found.'),
            '/coinhive|cryptonight|xmrig|webminer/i' => __('Known crypto-mining related terms were found.'),
        ];

        foreach ($suspiciousPatterns as $pattern => $message) {
            if (preg_match($pattern, $bodyLower)) {
                $status = $status === 'safe' ? 'warning' : $status;
                $findings[] = $message;
            }
        }

        if ($findings === []) {
            $findings[] = __('No obvious suspicious HTML or JavaScript patterns were found. This is not a malware guarantee.');
        } else {
            $findings[] = __('This is a heuristic scan only. Use a reputation service for stronger malware detection.');
        }

        return [
            'status' => $status,
            'label' => match ($status) {
                'danger' => __('Blocked'),
                'warning' => __('Suspicious / review'),
                default => __('No obvious issue'),
            },
            'findings' => array_values(array_unique($findings)),
        ];
    }

    private function overallStatus(Response $response, string $frameStatus, string $securityStatus): string
    {
        if ($response->status() >= 400 || $securityStatus === 'danger' || $frameStatus === 'danger') {
            return 'danger';
        }

        if ($response->redirect() || in_array('warning', [$frameStatus, $securityStatus], true)) {
            return 'warning';
        }

        return 'safe';
    }

    private function summaryFor(string $status, string $frameStatus, string $securityStatus): string
    {
        if ($status === 'danger' && $frameStatus === 'danger') {
            return __('This URL likely cannot be embedded in an iframe.');
        }

        if ($status === 'warning' || $securityStatus === 'warning') {
            return __('The URL is reachable, but it has findings that should be reviewed manually.');
        }

        return __('The URL is reachable and no obvious frame-breaker or suspicious code pattern was found.');
    }
}
