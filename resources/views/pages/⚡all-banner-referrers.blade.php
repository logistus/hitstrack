<?php

use App\Support\AnalyticsCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Banner Referrers')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'impressions';

    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage('referrerPage');
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['ref_url', 'impressions', 'clicks', 'unique_events', 'ctr', 'last_event_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'ref_url' ? 'asc' : 'desc';
        }

        $this->resetPage('referrerPage');
    }

    public function with(): array
    {
        $search = trim($this->search);

        $referrers = $this->referrerPerformanceQuery()
            ->when($search !== '', fn (Builder $query) => $query->where('ref_url', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->when($this->sortField !== 'ref_url', fn (Builder $query) => $query->orderBy('ref_url'))
            ->simplePaginate(7, pageName: 'referrerPage');

        return [
            'referrers' => $referrers,
            'summaryStats' => AnalyticsCache::remember(
                'all-banner-referrers',
                (int) Auth::id(),
                'summary',
                function (): array {
                    $allEvents = DB::query()->fromSub($this->bannerEventsQuery(), 'banner_events');

                    return [
                        'impressions' => (clone $allEvents)->where('event_type', 'impression')->count(),
                        'clicks' => (clone $allEvents)->where('event_type', 'click')->count(),
                        'referrers' => DB::query()->fromSub($this->referrerPerformanceQuery(), 'referrers')->count(),
                    ];
                },
            ),
        ];
    }

    public function referrerHref(?string $refUrl): ?string
    {
        if (! $refUrl) {
            return null;
        }

        return str_starts_with($refUrl, 'http://') || str_starts_with($refUrl, 'https://')
            ? $refUrl
            : 'https://' . $refUrl;
    }

    private function bannerEventsQuery(): Builder
    {
        return DB::table('banner_stats')
            ->join('banners', 'banners.id', '=', 'banner_stats.banner_id')
            ->where('banners.user_id', Auth::id())
            ->select([
                'banner_stats.ref_url',
                'banner_stats.ip_address',
                'banner_stats.event_type',
                'banner_stats.created_at as event_at',
            ]);
    }

    private function referrerPerformanceQuery(): Builder
    {
        return DB::query()
            ->fromSub($this->bannerEventsQuery(), 'banner_events')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw("SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_events')
            ->selectRaw("ROUND((SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) * 100.0) / NULLIF(SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END), 0), 2) as ctr")
            ->selectRaw('MAX(event_at) as last_event_at')
            ->groupByRaw("COALESCE(ref_url, '')");
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl">{{ __('Banner Referrers') }}</flux:heading>
        <flux:subheading>{{ __('Combined referrer performance across all banner trackers and banner rotators.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Impressions') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['impressions']) }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Clicks') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['clicks']) }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Referrers') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['referrers']) }}</flux:heading>
            </div>
        </flux:card>
    </div>

    <div class="space-y-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Filter by URL')"
            type="search"
            autocomplete="off"
            placeholder="example.com"
            class="max-w-md" />

        <flux:table :paginate="$referrers">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'ref_url'" :direction="$sortDirection" wire:click="sortBy('ref_url')" class="cursor-pointer">
                    {{ __('Ref URL') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'impressions'" :direction="$sortDirection" wire:click="sortBy('impressions')" class="cursor-pointer text-right">
                    {{ __('Impressions') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'clicks'" :direction="$sortDirection" wire:click="sortBy('clicks')" class="cursor-pointer text-right">
                    {{ __('Clicks') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'unique_events'" :direction="$sortDirection" wire:click="sortBy('unique_events')" class="cursor-pointer text-right">
                    {{ __('Unique IPs') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'ctr'" :direction="$sortDirection" wire:click="sortBy('ctr')" class="cursor-pointer text-right">
                    {{ __('CTR') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'last_event_at'" :direction="$sortDirection" wire:click="sortBy('last_event_at')" class="cursor-pointer text-right">
                    {{ __('Last Event') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($referrers as $referrer)
                    <flux:table.row wire:key="all-banner-referrer-{{ md5($referrer->ref_url ?: 'direct') }}">
                        <flux:table.cell>
                            @if ($href = $this->referrerHref($referrer->ref_url))
                                <flux:link href="{{ $href }}" target="_blank" rel="noreferrer" class="block max-w-2xl truncate">
                                    {{ $referrer->ref_url }}
                                </flux:link>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Direct / unknown') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($referrer->impressions) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($referrer->clicks) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($referrer->unique_events) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) ($referrer->ctr ?? 0), 2) }}%</flux:table.cell>
                        <flux:table.cell>
                            @if ($referrer->last_event_at)
                                @php($lastEventAt = \Carbon\Carbon::parse($referrer->last_event_at))
                                <span title="{{ $lastEventAt->format('Y-m-d H:i:s') }}">
                                    {{ $lastEventAt->diffForHumans(short: true) }}
                                </span>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Never') }}</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No referrer data yet.') }}
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
