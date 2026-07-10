<?php

use App\Models\LinkTracker;
use App\Support\TargetUrlChecker;
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

    public array $selectedTrackerIds = [];

    public string $tracker_name = '';

    public string $target_url = '';

    public ?array $targetUrlCheckResult = null;

    public string $checkedTargetUrl = '';

    public function createTracker(): void
    {
        if ($this->trackerLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your link tracker limit has been reached.'));

            return;
        }

        $this->resetForm();

        Flux::modal('tracker-form')->show();
    }

    public function updatedTargetUrl(): void
    {
        $this->resetTargetUrlCheck();
    }

    public function checkTargetUrl(TargetUrlChecker $checker): void
    {
        $validated = $this->validate([
            'target_url' => ['required', 'url', 'max:255'],
        ]);

        $this->targetUrlCheckResult = $checker->check($validated['target_url']);
        $this->checkedTargetUrl = trim($validated['target_url']);

        if (($this->targetUrlCheckResult['status'] ?? null) === 'safe') {
            Flux::toast(variant: 'success', text: __('Target URL approved. You can now save this tracker.'));

            return;
        }

        Flux::toast(variant: 'warning', text: __('Target URL check found issues. Please review before saving.'));
    }

    public function save(): void
    {
        $validated = $this->validate([
            'tracker_name' => ['nullable', 'string', 'max:255'],
            'target_url' => ['required', 'url', 'max:255'],
        ]);

        if (! $this->targetUrlApproved($validated['target_url'])) {
            $this->addError('target_url', __('Run the Target URL check first. The tracker can only be saved when the URL check passes without issues.'));
            Flux::toast(variant: 'warning', text: __('Please approve the Target URL before saving.'));

            return;
        }

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

    public function confirmDeleteSelected(array $trackerIds): void
    {
        $this->selectedTrackerIds = array_values(array_unique(array_map('intval', $trackerIds)));

        if ($this->selectedTrackerIds === []) {
            return;
        }

        Flux::modal('delete-selected-trackers')->show();
    }

    public function deleteSelected(): void
    {
        $trackers = LinkTracker::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->selectedTrackerIds)
            ->get();

        $trackers->each->delete();
        $deletedCount = $trackers->count();

        $this->selectedTrackerIds = [];
        $this->dispatch('bulk-selection-cleared');
        Flux::modal('delete-selected-trackers')->close();
        Flux::toast(variant: 'success', text: trans_choice(':count link tracker deleted.|:count link trackers deleted.', $deletedCount, ['count' => $deletedCount]));
    }

    public function cancelDeleteSelected(): void
    {
        Flux::modal('delete-selected-trackers')->close();
    }

    private function resetForm(): void
    {
        $this->reset('editingTrackerId', 'tracker_name', 'target_url');
        $this->resetTargetUrlCheck();
        $this->resetValidation();
    }

    private function resetTargetUrlCheck(): void
    {
        $this->targetUrlCheckResult = null;
        $this->checkedTargetUrl = '';
    }

    private function targetUrlApproved(string $targetUrl): bool
    {
        return ($this->targetUrlCheckResult['status'] ?? null) === 'safe'
            && $this->checkedTargetUrl === trim($targetUrl);
    }

    public function badgeClasses(?string $status): string
    {
        return match ($status) {
            'safe' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300',
            'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300',
            default => 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300',
        };
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
        $visibleTrackerIds = $this->visibleTrackerIds($trackerLimit);

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
                ->when($visibleTrackerIds !== null, fn ($query) => $query->whereIn('id', $visibleTrackerIds))
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

    private function visibleTrackerIds(?int $limit): ?array
    {
        if ($limit === null) {
            return null;
        }

        if ($limit <= 0) {
            return [];
        }

        return LinkTracker::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit($limit)
            ->pluck('id')
            ->all();
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

            <div class="space-y-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Target URL must be checked and approved before saving this tracker.') }}
                    </flux:text>

                    <flux:button variant="filled" size="sm" type="button" wire:click="checkTargetUrl" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="checkTargetUrl">{{ __('Check URL') }}</span>
                        <span wire:loading wire:target="checkTargetUrl">{{ __('Checking...') }}</span>
                    </flux:button>
                </div>

                @if ($targetUrlCheckResult)
                    <div class="space-y-2 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border px-2.5 py-1 text-xs font-medium {{ $this->badgeClasses($targetUrlCheckResult['status'] ?? null) }}">
                                {{ str($targetUrlCheckResult['status'] ?? 'unknown')->title() }}
                            </span>
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $targetUrlCheckResult['summary'] ?? '' }}</span>
                        </div>

                        @if (($targetUrlCheckResult['status'] ?? null) !== 'safe')
                            <ul class="space-y-1 text-zinc-600 dark:text-zinc-400">
                                @foreach (array_slice(array_merge($targetUrlCheckResult['frame']['findings'] ?? [], $targetUrlCheckResult['security']['findings'] ?? [], $targetUrlCheckResult['reputation']['findings'] ?? []), 0, 5) as $finding)
                                    <li>• {{ $finding }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @else
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('You can also open the full checker page from the Link menu for redirect and header details.') }}
                    </flux:text>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelEdit">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" type="submit" :disabled="! $this->targetUrlApproved($target_url)">
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

    <flux:modal name="delete-selected-trackers" class="max-w-md md:min-w-md">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete selected trackers') }}</flux:heading>
                <flux:text>
                    {{ trans_choice('Are you sure you want to delete :count selected tracker? This action cannot be undone.|Are you sure you want to delete :count selected trackers? This action cannot be undone.', count($selectedTrackerIds), ['count' => count($selectedTrackerIds)]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelDeleteSelected">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="button" wire:click="deleteSelected">{{ __('Delete selected') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    @php
        $pageTrackerIds = $trackers->pluck('id')->map(fn ($id) => (string) $id)->all();
    @endphp
    <div x-data="{ selected: [] }" x-on:bulk-selection-cleared.window="selected = []" class="space-y-4">
        <div style="visibility: hidden" x-bind:style="selected.length > 0 ? 'visibility: visible' : 'visibility: hidden'">
            <flux:button variant="danger" type="button" icon="trash" x-on:click="$wire.confirmDeleteSelected(selected)">
                {{ __('Delete') }} (<span x-text="selected.length"></span>) {{ __('Tracker(s)') }}
            </flux:button>
        </div>

    <flux:table :paginate="$trackers">
        <flux:table.columns>
            <flux:table.column>
                <input
                    type="checkbox"
                    class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800"
                    x-on:change="selected = $event.target.checked ? [...new Set([...selected, ...@js($pageTrackerIds)])] : selected.filter(id => !@js($pageTrackerIds).includes(id))"
                    x-bind:checked="@js($pageTrackerIds).length > 0 && @js($pageTrackerIds).every(id => selected.includes(id))"
                    aria-label="{{ __('Select or deselect all trackers on this page') }}">
            </flux:table.column>
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
                    <input
                        type="checkbox"
                        value="{{ $tracker->id }}"
                        x-model="selected"
                        class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800"
                        aria-label="{{ __('Select tracker :name', ['name' => $tracker->tracker_name ?: $tracker->tracker_slug]) }}">
                </flux:table.cell>
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
                <flux:table.cell colspan="6" align="center">
                    {{ __('No trackers created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </div>
</section>
