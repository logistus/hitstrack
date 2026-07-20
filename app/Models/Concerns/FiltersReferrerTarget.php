<?php
// app/Models/Concerns/FiltersReferrerTarget.php

namespace App\Models\Concerns;

use App\Support\ClientInfo;
use Illuminate\Support\Collection;

trait FiltersReferrerTarget
{
    /**
     * target_url'i, tracker'ın gösterildiği sayfanın (referrer) domain'i ile
     * aynı olan öğeleri havuzdan çıkarır. Hepsi elenirse orijinal havuza döner
     * (boş sonuç dönmek yerine).
     */
    protected function withoutReferrerTarget(Collection $items, ?string $refUrl, string $targetAttribute = 'target_url'): Collection
    {
        $refDomain = $this->domainForComparison($refUrl);

        if (! $refDomain) {
            return $items;
        }

        $filtered = $items
            ->reject(fn($item) => $this->domainForComparison($item->{$targetAttribute}) === $refDomain)
            ->values();

        return $filtered->isEmpty() ? $items : $filtered;
    }

    protected function domainForComparison(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $domain = ClientInfo::domainFromUrl($url)
            ?? ClientInfo::domainFromUrl("https://{$url}");

        return $domain ?: null;
    }
}
