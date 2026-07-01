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
        ];
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
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Link') }}</flux:heading>
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:button :href="route('linktrackers')" wire:navigate icon="chart-bar">{{ __('Trackers') }}</flux:button>
                    <flux:button :href="route('linkrotators')" wire:navigate icon="arrows-right-left">{{ __('Rotators') }}</flux:button>
                    <flux:button :href="route('referrers')" wire:navigate icon="globe-alt">{{ __('Referrers') }}</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Banner') }}</flux:heading>
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:button :href="route('bannertrackers')" wire:navigate icon="photo">{{ __('Trackers') }}</flux:button>
                    <flux:button :href="route('bannerrotators')" wire:navigate icon="rectangle-stack">{{ __('Rotators') }}</flux:button>
                    <flux:button :href="route('banner-referrers')" wire:navigate icon="globe-alt">{{ __('Referrers') }}</flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</section>
