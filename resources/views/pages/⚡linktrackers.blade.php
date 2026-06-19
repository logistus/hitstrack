<?php

use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
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

    public string $target_url = '';

    public function createTracker(): void
    {
        $this->resetForm();

        Flux::modal('tracker-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'target_url' => ['required', 'url', 'max:255'],
        ]);

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
        $this->reset('editingTrackerId', 'target_url');
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

    public function with(): array
    {
        return [
            'trackers' => LinkTracker::query()
                ->select('trackers.*')
                ->where('user_id', Auth::id())
                ->withCount('stats')
                ->selectSub(
                    LinkTrackerStat::query()
                        ->selectRaw('COUNT(DISTINCT ip_address)')
                        ->whereColumn('tracker_stats.tracker_id', 'trackers.id'),
                    'unique_hits_count',
                )
                ->withMax('stats', 'created_at')
                ->latest()
                ->paginate(25),
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

        <flux:button variant="primary" type="button" wire:click="createTracker">
            {{ __('New tracker') }}
        </flux:button>
    </div>

    <flux:modal name="tracker-form" class="max-w-lg md:min-w-lg" @close="closeTrackerModal">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingTrackerId ? __('Edit tracker') : __('Create tracker') }}
                </flux:heading>
                <flux:text>{{ __('Set the destination URL for this tracker.') }}</flux:text>
            </div>

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
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column>{{ __('Link tracker URL/Target URL') }}</flux:table.column>
            <flux:table.column>{{ __('Total Hits') }}</flux:table.column>
            <flux:table.column>{{ __('Unique Hits') }}</flux:table.column>
            <flux:table.column>{{ __('Last Hit') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($trackers as $tracker)
            <flux:table.row :key="$tracker->id">
                <flux:table.cell>
                    {{ $tracker->created_at?->format('Y-m-d H:i') }}
                </flux:table.cell>

                <flux:table.cell>
                    @php($trackerUrl = route('linktrackers.redirect', $tracker->tracker_slug))

                    <div class="flex max-w-md items-center gap-2">
                        <flux:link
                            href="{{ $trackerUrl }}"
                            target="_blank"
                            rel="noreferrer"
                            class="block min-w-0 truncate">
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
                    <flux:link
                        href="{{ $tracker->target_url }}"
                        target="_blank"
                        rel="noreferrer"
                        class="block max-w-md truncate">
                        {{ $tracker->target_url }}
                    </flux:link>
                </flux:table.cell>

                <flux:table.cell>
                    {{ number_format($tracker->stats_count) }}
                </flux:table.cell>

                <flux:table.cell>
                    {{ number_format($tracker->unique_hits_count) }}
                </flux:table.cell>

                <flux:table.cell>
                    @if ($tracker->stats_max_created_at)
                    @php($lastHitAt = \Carbon\Carbon::parse($tracker->stats_max_created_at))
                    <span title="{{ $lastHitAt->format('Y-m-d H:i:s') }}">
                        {{ $lastHitAt->diffForHumans(short: true) }}
                    </span>
                    @else
                    {{ __('Never') }}
                    @endif
                </flux:table.cell>

                <flux:table.cell align="end">
                    <div class="flex justify-end gap-3">
                        <flux:link
                            :href="route('linktrackers.stats', $tracker->tracker_slug)"
                            wire:navigate>
                            {{ __('Stats') }}
                        </flux:link>

                        <flux:link
                            wire:click.prevent="editTracker({{ $tracker->id }})"
                            class="cursor-pointer">
                            {{ __('Edit') }}
                        </flux:link>

                        <flux:link
                            wire:click.prevent="confirmDeleteTracker({{ $tracker->id }})"
                            class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            {{ __('Delete') }}
                        </flux:link>
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="7" align="center">
                    {{ __('No trackers created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
