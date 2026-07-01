<?php

use App\Models\LinkTracker;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Trackers')] class extends Component
{
    use WithPagination;

    public ?int $editingTrackerId = null;

    public ?int $deletingTrackerId = null;

    public string $deletingTrackerSlug = '';

    public string $tracker_name = '';

    public string $target_url = '';

    public function createTracker(): void
    {
        if ($this->trackerLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your link tracker limit has been reached.'));

            return;
        }

        $this->resetForm();

        Flux::modal('tracker-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'tracker_name' => ['nullable', 'string', 'max:255'],
            'target_url' => ['required', 'url', 'max:255'],
        ]);

        $validated['tracker_name'] = filled($validated['tracker_name'])
            ? trim($validated['tracker_name'])
            : null;

        if ($this->editingTrackerId) {
            LinkTracker::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingTrackerId)
                ->update($validated);

            $this->resetForm();
            Flux::modal('tracker-form')->close();

            Flux::toast(variant: 'success', text: __('Link tracker updated.'));

            return;
        }

        if ($this->trackerLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your link tracker limit has been reached.'));

            return;
        }

        LinkTracker::create([
            ...$validated,
            'tracker_slug' => $this->generateTrackerSlug(),
            'user_id' => Auth::id(),
        ]);

        $this->resetForm();
        Flux::modal('tracker-form')->close();

        Flux::toast(variant: 'success', text: __('Link tracker created.'));
    }

    public function editTracker(int $trackerId): void
    {
        $tracker = LinkTracker::query()
            ->where('user_id', Auth::id())
            ->findOrFail($trackerId);

        $this->editingTrackerId = $tracker->id;
        $this->tracker_name = $tracker->tracker_name ?? '';
        $this->target_url = $tracker->target_url;

        $this->resetValidation();

        Flux::modal('tracker-form')->show();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        Flux::modal('tracker-form')->close();
    }

    public function closeTrackerModal(): void
    {
        $this->resetForm();
    }

    public function confirmDeleteTracker(int $trackerId): void
    {
        $tracker = LinkTracker::query()
            ->where('user_id', Auth::id())
            ->findOrFail($trackerId);

        $this->deletingTrackerId = $tracker->id;
        $this->deletingTrackerSlug = $tracker->tracker_slug;

        Flux::modal('delete-tracker')->show();
    }

    public function deleteTracker(): void
    {
        if (! $this->deletingTrackerId) {
            return;
        }

        LinkTracker::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->deletingTrackerId)
            ->delete();

        if ($this->editingTrackerId === $this->deletingTrackerId) {
            $this->resetForm();
        }

        $this->resetDeleteState();
        Flux::modal('delete-tracker')->close();

        Flux::toast(variant: 'success', text: __('Link tracker deleted.'));
    }

    public function closeDeleteModal(): void
    {
        $this->resetDeleteState();
    }

    public function cancelDelete(): void
    {
        $this->resetDeleteState();
        Flux::modal('delete-tracker')->close();
    }

    private function resetForm(): void
    {
        $this->reset('editingTrackerId', 'tracker_name', 'target_url');
        $this->resetValidation();
    }

    private function resetDeleteState(): void
    {
        $this->reset('deletingTrackerId', 'deletingTrackerSlug');
    }

    private function generateTrackerSlug(): string
    {
        do {
            $slug = Str::random(6);
        } while (LinkTracker::query()->where('tracker_slug', $slug)->exists());

        return $slug;
    }

    private function trackerLimit(): ?int
    {
        return Auth::user()?->userType?->max_link_trackers;
    }

    private function trackerCount(): int
    {
        return LinkTracker::query()->where('user_id', Auth::id())->count();
    }

    private function trackerLimitReached(): bool
    {
        $limit = $this->trackerLimit();

        return $limit !== null && $this->trackerCount() >= $limit;
    }

    public function with(): array
    {
        $trackerCount = $this->trackerCount();
        $trackerLimit = $this->trackerLimit();

        return [
            'trackers' => LinkTracker::query()
                ->select('trackers.*')
                ->selectRaw(
                    "(
                        COALESCE((
                            SELECT SUM(total_hits)
                            FROM daily_link_referrer_stats
                            WHERE source_type = ?
                                AND source_id = trackers.id
                                AND stat_date < ?
                        ), 0)
                        +
                        COALESCE((
                            SELECT COUNT(*)
                            FROM tracker_stats
                            WHERE tracker_stats.tracker_id = trackers.id
                                AND tracker_stats.created_at >= ?
                        ), 0)
                    ) as stats_count",
                    ['tracker', today()->toDateString(), today()],
                )
                ->selectRaw(
                    "(
                        COALESCE((
                            SELECT SUM(daily_unique_hits)
                            FROM daily_link_referrer_stats
                            WHERE source_type = ?
                                AND source_id = trackers.id
                                AND stat_date < ?
                        ), 0)
                        +
                        COALESCE((
                            SELECT COUNT(DISTINCT ip_address)
                            FROM tracker_stats
                            WHERE tracker_stats.tracker_id = trackers.id
                                AND tracker_stats.created_at >= ?
                        ), 0)
                    ) as unique_hits_count",
                    ['tracker', today()->toDateString(), today()],
                )
                ->where('user_id', Auth::id())
                ->withMax('stats', 'created_at')
                ->latest()
                ->simplePaginate(25),
            'usage' => [
                'count' => $trackerCount,
                'limit' => $trackerLimit,
                'remaining' => $trackerLimit === null ? null : max(0, $trackerLimit - $trackerCount),
                'reached' => $trackerLimit !== null && $trackerCount >= $trackerLimit,
                'can_upgrade' => $trackerLimit !== null
                    && $trackerCount >= $trackerLimit
                    && strtolower(trim((string) Auth::user()?->userType?->label)) === 'free',
            ],
        ];
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Trackers') }}</flux:heading>
            <flux:heading size="xl">{{ __('Trackers') }}</flux:heading>
            <flux:subheading>{{ __('Create and review your tracking links.') }}</flux:subheading>
        </div>

        @unless ($usage['reached'])
            <flux:button variant="primary" type="button" wire:click="createTracker">
                {{ __('New tracker') }}
            </flux:button>
        @endunless
    </div>

    @if ($usage['can_upgrade'])
        <flux:callout
            inline
            variant="danger"
            :heading="__('Link tracker usage')"
            :text="$usage['limit'] === null
                ? __('You have created :count link trackers. Your plan has unlimited link trackers.', ['count' => number_format($usage['count'])])
                : __('You have created :count of :limit link trackers.', ['count' => number_format($usage['count']), 'limit' => number_format($usage['limit'])])">
            <x-slot:actions>
                <flux:button variant="primary" size="sm" type="button">
                    {{ __('Upgrade Now') }}
                </flux:button>
            </x-slot:actions>
        </flux:callout>
    @else
        <flux:callout
            inline
            :variant="$usage['reached'] ? 'danger' : 'success'"
            :heading="__('Link tracker usage')"
            :text="$usage['limit'] === null
                ? __('You have created :count link trackers. Your plan has unlimited link trackers.', ['count' => number_format($usage['count'])])
                : __('You have created :count of :limit link trackers.', ['count' => number_format($usage['count']), 'limit' => number_format($usage['limit'])])" />
    @endif

    <flux:modal name="tracker-form" class="max-w-lg md:min-w-lg" @close="closeTrackerModal">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingTrackerId ? __('Edit tracker') : __('Create tracker') }}
                </flux:heading>
                <flux:text>{{ __('Set the destination URL for this tracker.') }}</flux:text>
            </div>

            <flux:input
                wire:model="tracker_name"
                :label="__('Name')"
                type="text"
                autocomplete="off"
                placeholder="{{ __('Optional tracker name') }}" />

            <flux:input
                wire:model="target_url"
                :label="__('Target URL')"
                type="url"
                required
                autocomplete="url"
                placeholder="https://example.com/landing-page" />

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelEdit">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" type="submit">
                    {{ $editingTrackerId ? __('Update tracker') : __('Create tracker') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-tracker" class="max-w-md md:min-w-md" @close="closeDeleteModal">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete tracker') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete ":slug"? This action cannot be undone.', ['slug' => $deletingTrackerSlug]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelDelete">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="danger" type="button" wire:click="deleteTracker">
                    {{ __('Delete tracker') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:table :paginate="$trackers">
        <flux:table.columns>
            <flux:table.column>{{ __('Tracker') }}</flux:table.column>
            <flux:table.column>{{ __('Link') }}</flux:table.column>
            <flux:table.column>{{ __('Performance') }}</flux:table.column>
            <flux:table.column>{{ __('Last Hit') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($trackers as $tracker)
            <flux:table.row :key="$tracker->id">
                <flux:table.cell>
                    @php($trackerUrl = route('linktrackers.redirect', $tracker->tracker_slug))

                    <div class="max-w-md space-y-1">
                        <div class="font-medium">{{ $tracker->tracker_name ?: __('Unnamed tracker') }}</div>
                        <flux:link
                            href="{{ $tracker->target_url }}"
                            target="_blank"
                            rel="noreferrer"
                            class="block break-all text-xs text-zinc-500 dark:text-zinc-400"
                            title="{{ $tracker->target_url }}">
                            {{ $tracker->target_url }}
                        </flux:link>
                    </div>
                </flux:table.cell>

                <flux:table.cell>
                    <div class="flex max-w-md min-w-0 items-center gap-2">
                        <flux:link
                            href="{{ $trackerUrl }}"
                            target="_blank"
                            rel="noreferrer"
                            class="min-w-0 break-all"
                            title="{{ $trackerUrl }}">
                            {{ $trackerUrl }}
                        </flux:link>

                        <flux:tooltip :content="__('Copy tracker URL')">
                            <flux:button
                                variant="ghost"
                                size="xs"
                                icon="clipboard-document"
                                type="button"
                                class="shrink-0"
                                x-on:click="navigator.clipboard.writeText(@js($trackerUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Link tracker URL copied.')) }))"
                                :aria-label="__('Copy tracker URL')" />
                        </flux:tooltip>
                    </div>
                </flux:table.cell>

                <flux:table.cell>
                    <div class="space-y-1 text-sm tabular-nums">
                        <div><span class="font-medium">{{ number_format($tracker->stats_count) }}</span> <span class="text-zinc-500 dark:text-zinc-400">{{ __('hits') }}</span></div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($tracker->unique_hits_count) }} {{ __('unique') }}</div>
                    </div>
                </flux:table.cell>

                <flux:table.cell>
                    <div class="space-y-1 text-sm">
                        @if ($tracker->stats_max_created_at)
                        @php($lastHitAt = \Carbon\Carbon::parse($tracker->stats_max_created_at))
                        <div title="{{ $lastHitAt->format('Y-m-d H:i:s') }}" class="font-medium">
                            {{ $lastHitAt->diffForHumans(short: true) }}
                        </div>
                        @else
                        <div class="font-medium">{{ __('Never') }}</div>
                        @endif
                    </div>
                </flux:table.cell>

                <flux:table.cell align="end">
                    <div class="flex justify-end gap-1">
                        <flux:tooltip :content="__('Stats')">
                            <flux:button :href="route('linktrackers.stats', $tracker->tracker_slug)" variant="ghost" size="sm" icon="chart-bar" wire:navigate :aria-label="__('Stats')" />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Edit')">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" type="button" wire:click="editTracker({{ $tracker->id }})" :aria-label="__('Edit')" />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Delete')">
                            <flux:button variant="ghost" size="sm" icon="trash" type="button" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" wire:click="confirmDeleteTracker({{ $tracker->id }})" :aria-label="__('Delete')" />
                        </flux:tooltip>
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="5" align="center">
                    {{ __('No trackers created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
