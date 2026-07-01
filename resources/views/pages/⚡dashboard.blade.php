<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\LinkRotator;
use App\Models\LinkTracker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    public function with(): array
    {
        $userId = Auth::id();
        $today = today();
        $since = now()->subDay();

        $linkHits = (int) DB::table('daily_link_referrer_stats')
            ->where('user_id', $userId)
            ->where('stat_date', '<', $today)
            ->sum('total_hits')
            + $this->todayTrackerHits($userId, $today)
            + $this->todayRotatorHits($userId, $today);

        $linkUniqueHits = (int) DB::table('daily_link_referrer_stats')
            ->where('user_id', $userId)
            ->where('stat_date', '<', $today)
            ->sum('daily_unique_hits')
            + $this->todayTrackerUniqueHits($userId, $today)
            + $this->todayRotatorUniqueHits($userId, $today);

        $bannerImpressions = (int) DB::table('daily_banner_referrer_stats')
            ->where('user_id', $userId)
            ->where('source_type', 'banner')
            ->where('stat_date', '<', $today)
            ->sum('impressions')
            + $this->todayBannerEvents($userId, $today, 'impression');

        $bannerClicks = (int) DB::table('daily_banner_referrer_stats')
            ->where('user_id', $userId)
            ->where('source_type', 'banner')
            ->where('stat_date', '<', $today)
            ->sum('clicks')
            + $this->todayBannerEvents($userId, $today, 'click');

        return [
            'cards' => [
                [
                    'label' => __('Link Hits'),
                    'value' => $linkHits,
                    'detail' => __(':count unique', ['count' => number_format($linkUniqueHits)]),
                    'route' => route('referrers'),
                ],
                [
                    'label' => __('Banner Impressions'),
                    'value' => $bannerImpressions,
                    'detail' => __(':count clicks', ['count' => number_format($bannerClicks)]),
                    'route' => route('banner-referrers'),
                ],
                [
                    'label' => __('Link Trackers'),
                    'value' => LinkTracker::query()->where('user_id', $userId)->count(),
                    'detail' => __(':count rotators', ['count' => number_format(LinkRotator::query()->where('user_id', $userId)->count())]),
                    'route' => route('linktrackers'),
                ],
                [
                    'label' => __('Banner Trackers'),
                    'value' => Banner::query()->where('user_id', $userId)->count(),
                    'detail' => __(':count rotators', ['count' => number_format(BannerRotator::query()->where('user_id', $userId)->count())]),
                    'route' => route('bannertrackers'),
                ],
            ],
            'latestLinkEvents' => $this->latestLinkEvents($userId, $since),
            'latestBannerImpressions' => $this->latestBannerImpressions($userId, $since),
        ];
    }

    private function latestLinkEvents(int $userId, $since)
    {
        $trackerEvents = DB::table('tracker_stats')
            ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
            ->where('trackers.user_id', $userId)
            ->whereNull('tracker_stats.rotator_id')
            ->where('tracker_stats.created_at', '>=', $since)
            ->select([
                'trackers.target_url',
                'tracker_stats.ref_url',
                'tracker_stats.created_at as shown_at',
            ]);

        $rotatorEvents = DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->join('trackers', 'trackers.id', '=', 'rotator_stats.tracker_id')
            ->where('rotators.user_id', $userId)
            ->where('rotator_stats.created_at', '>=', $since)
            ->select([
                'trackers.target_url',
                'rotator_stats.ref_url',
                'rotator_stats.created_at as shown_at',
            ]);

        return DB::query()
            ->fromSub($trackerEvents->unionAll($rotatorEvents), 'latest_link_events')
            ->orderByDesc('shown_at')
            ->limit(10)
            ->get();
    }

    private function latestBannerImpressions(int $userId, $since)
    {
        return DB::table('banner_stats')
            ->join('banners', 'banners.id', '=', 'banner_stats.banner_id')
            ->where('banners.user_id', $userId)
            ->where('banner_stats.event_type', 'impression')
            ->where('banner_stats.created_at', '>=', $since)
            ->select([
                'banners.name',
                'banners.image_url',
                'banners.alt_text',
                'banners.width',
                'banners.height',
                'banner_stats.ref_url',
                'banner_stats.created_at as shown_at',
            ])
            ->orderByDesc('banner_stats.created_at')
            ->limit(10)
            ->get();
    }

    private function todayTrackerHits(int $userId, $today): int
    {
        return (int) DB::table('tracker_stats')
            ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
            ->where('trackers.user_id', $userId)
            ->whereNull('tracker_stats.rotator_id')
            ->where('tracker_stats.created_at', '>=', $today)
            ->count();
    }

    private function todayRotatorHits(int $userId, $today): int
    {
        return (int) DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->where('rotators.user_id', $userId)
            ->where('rotator_stats.created_at', '>=', $today)
            ->count();
    }

    private function todayTrackerUniqueHits(int $userId, $today): int
    {
        return (int) DB::table('tracker_stats')
            ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
            ->where('trackers.user_id', $userId)
            ->whereNull('tracker_stats.rotator_id')
            ->where('tracker_stats.created_at', '>=', $today)
            ->distinct('tracker_stats.ip_address')
            ->count('tracker_stats.ip_address');
    }

    private function todayRotatorUniqueHits(int $userId, $today): int
    {
        return (int) DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->where('rotators.user_id', $userId)
            ->where('rotator_stats.created_at', '>=', $today)
            ->distinct('rotator_stats.ip_address')
            ->count('rotator_stats.ip_address');
    }

    private function todayBannerEvents(int $userId, $today, string $eventType): int
    {
        return (int) DB::table('banner_stats')
            ->join('banners', 'banners.id', '=', 'banner_stats.banner_id')
            ->where('banners.user_id', $userId)
            ->where('banner_stats.event_type', $eventType)
            ->where('banner_stats.created_at', '>=', $today)
            ->count();
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('A quick snapshot of your tracking activity.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <flux:card>
                <a href="{{ $card['route'] }}" wire:navigate class="block space-y-3">
                    <flux:text>{{ $card['label'] }}</flux:text>
                    <flux:heading size="xl">{{ number_format($card['value']) }}</flux:heading>
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $card['detail'] }}</flux:text>
                </a>
            </flux:card>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card>
            <div class="space-y-4 overflow-hidden">
                <flux:heading size="lg">{{ __('Recent Link Traffic') }}</flux:heading>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <tr>
                                <th class="py-2 pr-4 font-medium">{{ __('Target URL') }}</th>
                                <th class="py-2 pr-4 font-medium">{{ __('Shown On') }}</th>
                                <th class="py-2 font-medium">{{ __('Shown At') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($latestLinkEvents as $event)
                                <tr>
                                    <td class="max-w-64 py-3 pr-4">
                                        <a href="{{ $event->target_url }}" target="_blank" rel="noreferrer" class="block truncate text-white hover:underline">
                                            {{ $event->target_url }}
                                        </a>
                                    </td>
                                    <td class="max-w-56 py-3 pr-4">
                                        <span class="block truncate text-zinc-600 dark:text-zinc-400">
                                            {{ $event->ref_url ?: __('Direct / Unknown') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap py-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $event->shown_at ? \Illuminate\Support\Carbon::parse($event->shown_at)->format('M j, H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No link traffic in the last 24 hours.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="space-y-4 overflow-hidden">
                <flux:heading size="lg">{{ __('Recent Banner Impressions') }}</flux:heading>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <tr>
                                <th class="py-2 pr-4 font-medium">{{ __('Banner') }}</th>
                                <th class="py-2 pr-4 font-medium">{{ __('Shown On') }}</th>
                                <th class="py-2 font-medium">{{ __('Shown At') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($latestBannerImpressions as $impression)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <img
                                            src="{{ $impression->image_url }}"
                                            alt="{{ $impression->alt_text ?: $impression->name }}"
                                            width="{{ $impression->width ? max(1, (int) floor($impression->width / 2)) : null }}"
                                            height="{{ $impression->height ? max(1, (int) floor($impression->height / 2)) : null }}"
                                            class="max-h-16 max-w-40 rounded border border-zinc-200 object-contain dark:border-zinc-700">
                                    </td>
                                    <td class="max-w-56 py-3 pr-4">
                                        <span class="block truncate text-zinc-600 dark:text-zinc-400">
                                            {{ $impression->ref_url ?: __('Direct / Unknown') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap py-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $impression->shown_at ? \Illuminate\Support\Carbon::parse($impression->shown_at)->format('M j, H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No banner impressions in the last 24 hours.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </flux:card>
    </div>
</section>
