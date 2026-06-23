<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

class AnalyticsCache
{
    private const TTL_SECONDS = 30;

    public static function remember(string $scope, int $id, string $segment, Closure $callback): mixed
    {
        return Cache::remember(
            "analytics:v1:{$scope}:{$id}:{$segment}",
            now()->addSeconds(self::TTL_SECONDS),
            $callback,
        );
    }

    public static function forget(string $scope, int $id, string $segment): void
    {
        Cache::forget("analytics:v1:{$scope}:{$id}:{$segment}");
    }
}
