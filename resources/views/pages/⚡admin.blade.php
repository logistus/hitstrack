<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\LinkRotator;
use App\Models\LinkTracker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin')] class extends Component
{
    public function mount(): void
    {
        abort_unless(
            config('app.admin_email') && auth()->user()?->email === config('app.admin_email'),
            403,
        );
    }

    public function with(): array
    {
        $today = today();
        $since = now()->subDay();

        return [
            'cards' => [
                [
                    'label' => __('Users'),
                    'value' => User::query()->count(),
                    'detail' => __(':count new in 24h', ['count' => number_format(User::query()->where('created_at', '>=', $since)->count())]),
                ],
                [
                    'label' => __('Link Assets'),
                    'value' => LinkTracker::query()->count() + LinkRotator::query()->count(),
                    'detail' => __(':trackers trackers, :rotators rotators', [
                        'trackers' => number_format(LinkTracker::query()->count()),
                        'rotators' => number_format(LinkRotator::query()->count()),
                    ]),
                ],
                [
                    'label' => __('Banner Assets'),
                    'value' => Banner::query()->count() + BannerRotator::query()->count(),
                    'detail' => __(':trackers trackers, :rotators rotators', [
                        'trackers' => number_format(Banner::query()->count()),
                        'rotators' => number_format(BannerRotator::query()->count()),
                    ]),
                ],
                [
                    'label' => __('Raw Events Today'),
                    'value' => $this->rawEventsSince($today),
                    'detail' => __('Tracker, rotator, and banner rows'),
                ],
            ],
            'rawTables' => [
                ['table' => 'tracker_stats', 'today' => DB::table('tracker_stats')->where('created_at', '>=', $today)->count(), 'total' => DB::table('tracker_stats')->count()],
                ['table' => 'rotator_stats', 'today' => DB::table('rotator_stats')->where('created_at', '>=', $today)->count(), 'total' => DB::table('rotator_stats')->count()],
                ['table' => 'banner_stats', 'today' => DB::table('banner_stats')->where('created_at', '>=', $today)->count(), 'total' => DB::table('banner_stats')->count()],
            ],
            'aggregateTables' => [
                ['table' => 'daily_link_referrer_stats', 'latest' => DB::table('daily_link_referrer_stats')->max('stat_date'), 'rows' => DB::table('daily_link_referrer_stats')->count()],
                ['table' => 'daily_link_breakdown_stats', 'latest' => DB::table('daily_link_breakdown_stats')->max('stat_date'), 'rows' => DB::table('daily_link_breakdown_stats')->count()],
                ['table' => 'daily_link_rotator_tracker_stats', 'latest' => DB::table('daily_link_rotator_tracker_stats')->max('stat_date'), 'rows' => DB::table('daily_link_rotator_tracker_stats')->count()],
                ['table' => 'daily_banner_referrer_stats', 'latest' => DB::table('daily_banner_referrer_stats')->max('stat_date'), 'rows' => DB::table('daily_banner_referrer_stats')->count()],
                ['table' => 'daily_banner_breakdown_stats', 'latest' => DB::table('daily_banner_breakdown_stats')->max('stat_date'), 'rows' => DB::table('daily_banner_breakdown_stats')->count()],
                ['table' => 'daily_banner_rotator_banner_stats', 'latest' => DB::table('daily_banner_rotator_banner_stats')->max('stat_date'), 'rows' => DB::table('daily_banner_rotator_banner_stats')->count()],
            ],
            'topUsers' => $this->topUsers($since),
        ];
    }

    private function rawEventsSince($since): int
    {
        return DB::table('tracker_stats')->where('created_at', '>=', $since)->count()
            + DB::table('rotator_stats')->where('created_at', '>=', $since)->count()
            + DB::table('banner_stats')->where('created_at', '>=', $since)->count();
    }

    private function topUsers($since)
    {
        $linkTrackerEvents = DB::table('tracker_stats')
            ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
            ->where('tracker_stats.created_at', '>=', $since)
            ->selectRaw('trackers.user_id as user_id')
            ->selectRaw('COUNT(*) as events')
            ->groupBy('trackers.user_id');

        $linkRotatorEvents = DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->where('rotator_stats.created_at', '>=', $since)
            ->selectRaw('rotators.user_id as user_id')
            ->selectRaw('COUNT(*) as events')
            ->groupBy('rotators.user_id');

        $bannerEvents = DB::table('banner_stats')
            ->join('banners', 'banners.id', '=', 'banner_stats.banner_id')
            ->where('banner_stats.created_at', '>=', $since)
            ->selectRaw('banners.user_id as user_id')
            ->selectRaw('COUNT(*) as events')
            ->groupBy('banners.user_id');

        $events = DB::query()
            ->fromSub($linkTrackerEvents->unionAll($linkRotatorEvents)->unionAll($bannerEvents), 'user_events')
            ->selectRaw('user_id')
            ->selectRaw('SUM(events) as events')
            ->groupBy('user_id');

        return DB::query()
            ->fromSub($events, 'ranked_users')
            ->join('users', 'users.id', '=', 'ranked_users.user_id')
            ->selectRaw('users.name')
            ->selectRaw('users.email')
            ->selectRaw('ranked_users.events')
            ->orderByDesc('ranked_users.events')
            ->limit(10)
            ->get();
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl">{{ __('Admin') }}</flux:heading>
        <flux:subheading>{{ __('System overview and traffic health checks.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <flux:card>
                <div class="space-y-2">
                    <flux:text>{{ $card['label'] }}</flux:text>
                    <flux:heading size="xl">{{ number_format($card['value']) }}</flux:heading>
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $card['detail'] }}</flux:text>
                </div>
            </flux:card>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <flux:card>
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Raw Tables') }}</flux:heading>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">{{ __('Table') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('Today') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rawTables as $row)
                            <tr>
                                <td class="py-3 pr-4 font-mono text-xs">{{ $row['table'] }}</td>
                                <td class="py-3 pr-4 text-right">{{ number_format($row['today']) }}</td>
                                <td class="py-3 text-right">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>

        <flux:card>
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Aggregate Tables') }}</flux:heading>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">{{ __('Table') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Latest') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Rows') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($aggregateTables as $row)
                            <tr>
                                <td class="py-3 pr-4 font-mono text-xs">{{ $row['table'] }}</td>
                                <td class="py-3 pr-4">{{ $row['latest'] ?: '-' }}</td>
                                <td class="py-3 text-right">{{ number_format($row['rows']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Most Active Users, Last 24h') }}</flux:heading>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">{{ __('User') }}</th>
                        <th class="py-2 text-right font-medium">{{ __('Events') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($topUsers as $user)
                        <tr>
                            <td class="py-3 pr-4">
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                            </td>
                            <td class="py-3 text-right">{{ number_format($user->events) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No events in the last 24 hours.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</section>
