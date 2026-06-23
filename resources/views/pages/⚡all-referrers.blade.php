<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('All Referrers')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'total_hits';

    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage('referrerPage');
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['ref_url', 'total_hits', 'unique_hits', 'unique_rate'], true)) {
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
            ->paginate(25, pageName: 'referrerPage');

        $allEvents = DB::query()->fromSub($this->hitEventsQuery(), 'hit_events');

        return [
            'referrers' => $referrers,
            'summaryStats' => [
                'total_hits' => (clone $allEvents)->count(),
                'unique_hits' => (clone $allEvents)->distinct()->count('ip_address'),
                'referrers' => DB::query()->fromSub($this->referrerPerformanceQuery(), 'referrers')->count(),
            ],
        ];
    }

    public function referrerHref(?string $refUrl): ?string
    {
        if (! $refUrl) {
            return null;
        }

        return str_starts_with($refUrl, 'http://') || str_starts_with($refUrl, 'https://')
            ? $refUrl
            : 'https://'.$refUrl;
    }

    private function hitEventsQuery(): Builder
    {
        $userId = Auth::id();

        $directTrackerHits = DB::table('tracker_stats')
            ->join('trackers', 'trackers.id', '=', 'tracker_stats.tracker_id')
            ->where('trackers.user_id', $userId)
            ->whereNull('tracker_stats.rotator_id')
            ->select([
                'tracker_stats.ref_url',
                'tracker_stats.ip_address',
            ]);

        $rotatorHits = DB::table('rotator_stats')
            ->join('rotators', 'rotators.id', '=', 'rotator_stats.rotator_id')
            ->where('rotators.user_id', $userId)
            ->select([
                'rotator_stats.ref_url',
                'rotator_stats.ip_address',
            ]);

        return $directTrackerHits->unionAll($rotatorHits);
    }

    private function referrerPerformanceQuery(): Builder
    {
        return DB::query()
            ->fromSub($this->hitEventsQuery(), 'hit_events')
            ->selectRaw("COALESCE(ref_url, '') as ref_url")
            ->selectRaw('COUNT(*) as total_hits')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits')
            ->selectRaw('ROUND((COUNT(DISTINCT ip_address) * 100.0) / COUNT(*), 2) as unique_rate')
            ->groupByRaw("COALESCE(ref_url, '')");
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl">{{ __('All Referrers') }}</flux:heading>
        <flux:subheading>{{ __('Combined referrer performance across all link trackers and link rotators.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Total Hits') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['total_hits']) }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="space-y-2">
                <flux:text>{{ __('Unique Hits') }}</flux:text>
                <flux:heading size="xl">{{ number_format($summaryStats['unique_hits']) }}</flux:heading>
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

        <flux:pagination :paginator="$referrers" class="border-t-0 border-b pb-3 pt-0" />

        <flux:table :paginate="$referrers">
            <flux:table.columns>
                <flux:table.column
                    sortable
                    :sorted="$sortField === 'ref_url'"
                    :direction="$sortDirection"
                    wire:click="sortBy('ref_url')"
                    class="cursor-pointer">
                    {{ __('Ref URL') }}
                </flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$sortField === 'total_hits'"
                    :direction="$sortDirection"
                    wire:click="sortBy('total_hits')"
                    class="cursor-pointer text-right">
                    {{ __('Total Hits') }}
                </flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$sortField === 'unique_hits'"
                    :direction="$sortDirection"
                    wire:click="sortBy('unique_hits')"
                    class="cursor-pointer text-right">
                    {{ __('Unique Hits') }}
                </flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$sortField === 'unique_rate'"
                    :direction="$sortDirection"
                    wire:click="sortBy('unique_rate')"
                    class="cursor-pointer text-right">
                    {{ __('Unique Rate') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($referrers as $referrer)
                <flux:table.row wire:key="all-referrer-{{ md5($referrer->ref_url ?: 'direct') }}">
                    <flux:table.cell>
                        @if ($href = $this->referrerHref($referrer->ref_url))
                        <flux:link href="{{ $href }}" target="_blank" rel="noreferrer" class="block max-w-2xl truncate">
                            {{ $referrer->ref_url }}
                        </flux:link>
                        @else
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Direct / unknown') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format($referrer->total_hits) }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($referrer->unique_hits) }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($referrer->unique_rate, 2) }}%</flux:table.cell>
                </flux:table.row>
                @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
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
