<?php

use App\Models\LinkRotator;
use App\Models\LinkTracker;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rotators')] class extends Component
{
    use WithPagination;

    public ?int $editingRotatorId = null;

    public ?int $deletingRotatorId = null;

    public ?int $managingRotatorId = null;

    public ?int $editingTrackerId = null;

    public string $deletingRotatorSlug = '';

    public array $selectedRotatorIds = [];

    public string $rotator_name = '';

    public string $rotation_type = 'round_robin';

    public string $tracker_id = '';

    public int $weight = 1;

    public int $order_column = 0;

    public function createRotator(): void
    {
        if ($this->rotatorLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your link rotator limit has been reached.'));

            return;
        }

        $this->resetRotatorForm();

        Flux::modal('rotator-form')->show();
    }

    public function saveRotator(): void
    {
        $validated = $this->validate([
            'rotator_name' => ['nullable', 'string', 'max:255'],
            'rotation_type' => ['required', 'in:round_robin,random,weighted'],
        ]);

        $validated['rotator_name'] = filled($validated['rotator_name'])
            ? trim($validated['rotator_name'])
            : null;

        if ($this->editingRotatorId) {
            LinkRotator::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingRotatorId)
                ->update($validated);

            $this->resetRotatorForm();
            Flux::modal('rotator-form')->close();
            Flux::toast(variant: 'success', text: __('Link rotator updated.'));

            return;
        }

        if ($this->rotatorLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your link rotator limit has been reached.'));

            return;
        }

        LinkRotator::create([
            ...$validated,
            'rotator_slug' => $this->generateRotatorSlug(),
            'user_id' => Auth::id(),
        ]);

        $this->resetRotatorForm();
        Flux::modal('rotator-form')->close();
        Flux::toast(variant: 'success', text: __('Link rotator created.'));
    }

    public function editRotator(int $rotatorId): void
    {
        $rotator = $this->userRotatorsQuery()->findOrFail($rotatorId);

        $this->editingRotatorId = $rotator->id;
        $this->rotator_name = $rotator->rotator_name ?? '';
        $this->rotation_type = $rotator->rotation_type;

        $this->resetValidation();
        Flux::modal('rotator-form')->show();
    }

    public function cancelRotatorForm(): void
    {
        $this->resetRotatorForm();
        Flux::modal('rotator-form')->close();
    }

    public function closeRotatorModal(): void
    {
        $this->resetRotatorForm();
    }

    public function confirmDeleteRotator(int $rotatorId): void
    {
        $rotator = $this->userRotatorsQuery()->findOrFail($rotatorId);

        $this->deletingRotatorId = $rotator->id;
        $this->deletingRotatorSlug = $rotator->rotator_slug;

        Flux::modal('delete-rotator')->show();
    }

    public function deleteRotator(): void
    {
        if (! $this->deletingRotatorId) {
            return;
        }

        $this->userRotatorsQuery()
            ->findOrFail($this->deletingRotatorId)
            ->delete();

        $this->resetDeleteState();
        Flux::modal('delete-rotator')->close();
        Flux::toast(variant: 'success', text: __('Link rotator deleted.'));
    }

    public function cancelDelete(): void
    {
        $this->resetDeleteState();
        Flux::modal('delete-rotator')->close();
    }

    public function closeDeleteModal(): void
    {
        $this->resetDeleteState();
    }

    public function confirmDeleteSelected(array $rotatorIds): void
    {
        $this->selectedRotatorIds = array_values(array_unique(array_map('intval', $rotatorIds)));

        if ($this->selectedRotatorIds !== []) {
            Flux::modal('delete-selected-rotators')->show();
        }
    }

    public function deleteSelected(): void
    {
        $rotators = $this->userRotatorsQuery()->whereKey($this->selectedRotatorIds)->get();
        $rotators->each->delete();
        $deletedCount = $rotators->count();

        $this->selectedRotatorIds = [];
        $this->dispatch('bulk-selection-cleared');
        Flux::modal('delete-selected-rotators')->close();
        Flux::toast(variant: 'success', text: trans_choice(':count link rotator deleted.|:count link rotators deleted.', $deletedCount, ['count' => $deletedCount]));
    }

    public function cancelDeleteSelected(): void
    {
        Flux::modal('delete-selected-rotators')->close();
    }

    public function manageTrackers(int $rotatorId): void
    {
        $rotator = $this->userRotatorsQuery()->findOrFail($rotatorId);

        $this->managingRotatorId = $rotator->id;
        $this->resetTrackerForm();

        Flux::modal('manage-trackers')->show();
    }

    public function saveTracker(): void
    {
        $rotator = $this->managedRotator();
        $isWeighted = $rotator->rotation_type === 'weighted';
        $isRoundRobin = $rotator->rotation_type === 'round_robin';

        $validated = $this->validate([
            'tracker_id' => [$this->editingTrackerId ? 'nullable' : 'required', 'integer'],
            'weight' => [$isWeighted ? 'required' : 'nullable', 'integer', 'min:1'],
            'order_column' => [$isRoundRobin ? 'required' : 'nullable', 'integer', 'min:0'],
        ]);

        $pivot = [
            'weight' => $isWeighted ? (int) $validated['weight'] : 1,
            'order_column' => $isRoundRobin ? (int) $validated['order_column'] : 0,
        ];

        if ($this->editingTrackerId) {
            $rotator->trackers()->updateExistingPivot($this->editingTrackerId, $pivot);
            $message = __('Link rotator tracker updated.');
        } else {
            $tracker = LinkTracker::query()
                ->where('user_id', Auth::id())
                ->findOrFail((int) $validated['tracker_id']);

            $rotator->trackers()->attach($tracker->id, $pivot);
            $message = __('Link tracker added to rotator.');
        }

        $this->resetTrackerForm();
        Flux::toast(variant: 'success', text: $message);
    }

    public function editTracker(int $trackerId): void
    {
        $tracker = $this->managedRotator()
            ->trackers()
            ->where('trackers.id', $trackerId)
            ->firstOrFail();

        $this->editingTrackerId = $tracker->id;
        $this->tracker_id = (string) $tracker->id;
        $this->weight = (int) $tracker->pivot->weight;
        $this->order_column = (int) $tracker->pivot->order_column;

        $this->resetValidation();
    }

    public function removeTracker(int $trackerId): void
    {
        $this->managedRotator()->trackers()->detach($trackerId);

        if ($this->editingTrackerId === $trackerId) {
            $this->resetTrackerForm();
        }

        Flux::toast(variant: 'success', text: __('Link tracker removed from rotator.'));
    }

    public function cancelTrackerForm(): void
    {
        $this->resetTrackerForm();
    }

    public function closeManageTrackersModal(): void
    {
        $this->reset('managingRotatorId');
        $this->resetTrackerForm();
    }

    public function with(): array
    {
        $rotatorCount = $this->rotatorCount();
        $rotatorLimit = $this->rotatorLimit();
        $visibleRotatorIds = $this->visibleRotatorIds($rotatorLimit);

        $managedRotator = $this->managingRotatorId
            ? $this->userRotatorsQuery()
                ->with([
                    'trackers' => fn ($query) => $query
                        ->orderBy('rotator_tracker.created_at', 'desc')
                        ->orderBy('rotator_tracker.id', 'desc'),
                ])
                ->find($this->managingRotatorId)
            : null;

        $attachedTrackerIds = $managedRotator
            ? $managedRotator->trackers->pluck('id')
            : collect();

        $rotators = $this->userRotatorsQuery()
            ->select('rotators.*')
            ->selectRaw(
                "(
                    COALESCE((
                        SELECT SUM(total_hits)
                        FROM daily_link_referrer_stats
                        WHERE source_type = ?
                            AND source_id = rotators.id
                            AND stat_date < ?
                    ), 0)
                    +
                    COALESCE((
                        SELECT COUNT(*)
                        FROM rotator_stats
                        WHERE rotator_stats.rotator_id = rotators.id
                            AND rotator_stats.created_at >= ?
                    ), 0)
                ) as stats_count",
                ['rotator', today()->toDateString(), today()],
            )
            ->selectRaw(
                "(
                    COALESCE((
                        SELECT SUM(daily_unique_hits)
                        FROM daily_link_referrer_stats
                        WHERE source_type = ?
                            AND source_id = rotators.id
                            AND stat_date < ?
                    ), 0)
                    +
                    COALESCE((
                        SELECT COUNT(DISTINCT ip_address)
                        FROM rotator_stats
                        WHERE rotator_stats.rotator_id = rotators.id
                            AND rotator_stats.created_at >= ?
                    ), 0)
                ) as unique_hits_count",
                ['rotator', today()->toDateString(), today()],
            )
            ->withCount('trackers')
            ->withMax('stats', 'created_at')
            ->when($visibleRotatorIds !== null, fn ($query) => $query->whereIn('id', $visibleRotatorIds))
            ->latest()
            ->simplePaginate(25);

        return [
            'rotators' => $rotators,
            'managedRotator' => $managedRotator,
            'availableTrackers' => LinkTracker::query()
                ->where('user_id', Auth::id())
                ->when($attachedTrackerIds->isNotEmpty(), fn($query) => $query->whereNotIn('id', $attachedTrackerIds))
                ->latest()
                ->get(),
            'usage' => [
                'count' => $rotatorCount,
                'limit' => $rotatorLimit,
                'remaining' => $rotatorLimit === null ? null : max(0, $rotatorLimit - $rotatorCount),
                'reached' => $rotatorLimit !== null && $rotatorCount >= $rotatorLimit,
                'can_upgrade' => $rotatorLimit !== null
                    && $rotatorCount >= $rotatorLimit
                    && strtolower(trim((string) Auth::user()?->userType?->label)) === 'free',
            ],
        ];
    }

    public function rotationTypeDescription(): string
    {
        return match ($this->rotation_type) {
            'random' => __('Random picks one attached tracker on each visit.'),
            'weighted' => __('Weighted sends more visits to trackers with higher weight values.'),
            default => __('Round robin sends visits to attached trackers in order, one by one.'),
        };
    }

    private function userRotatorsQuery()
    {
        return LinkRotator::query()->where('user_id', Auth::id());
    }

    private function managedRotator(): LinkRotator
    {
        abort_if(! $this->managingRotatorId, 404);

        return $this->userRotatorsQuery()->findOrFail($this->managingRotatorId);
    }

    private function resetRotatorForm(): void
    {
        $this->reset('editingRotatorId', 'rotator_name', 'rotation_type');
        $this->rotation_type = 'round_robin';
        $this->resetValidation();
    }

    private function resetTrackerForm(): void
    {
        $this->reset('editingTrackerId', 'tracker_id', 'weight', 'order_column');
        $this->weight = 1;
        $this->order_column = $this->nextTrackerOrderColumn();
        $this->resetValidation();
    }

    private function nextTrackerOrderColumn(): int
    {
        if (! $this->managingRotatorId) {
            return 0;
        }

        $rotator = $this->userRotatorsQuery()->find($this->managingRotatorId);

        if (! $rotator || $rotator->rotation_type !== 'round_robin') {
            return 0;
        }

        return ((int) $rotator->trackers()->max('rotator_tracker.order_column')) + 1;
    }

    private function resetDeleteState(): void
    {
        $this->reset('deletingRotatorId', 'deletingRotatorSlug');
    }

    private function generateRotatorSlug(): string
    {
        do {
            $slug = Str::random(6);
        } while (LinkRotator::query()->where('rotator_slug', $slug)->exists());

        return $slug;
    }

    private function rotatorLimit(): ?int
    {
        return Auth::user()?->userType?->max_link_rotators;
    }

    private function rotatorCount(): int
    {
        return $this->userRotatorsQuery()->count();
    }

    private function rotatorLimitReached(): bool
    {
        $limit = $this->rotatorLimit();

        return $limit !== null && $this->rotatorCount() >= $limit;
    }

    private function visibleRotatorIds(?int $limit): ?array
    {
        if ($limit === null) {
            return null;
        }

        if ($limit <= 0) {
            return [];
        }

        return $this->userRotatorsQuery()
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
            <flux:heading class="sr-only">{{ __('Link Rotators') }}</flux:heading>
            <flux:heading size="xl">{{ __('Rotators') }}</flux:heading>
            <flux:subheading>{{ __('Create rotating links from your trackers.') }}</flux:subheading>
        </div>

        @unless ($usage['reached'])
            <flux:button variant="primary" type="button" wire:click="createRotator">
                {{ __('New rotator') }}
            </flux:button>
        @endunless
    </div>

    @if ($usage['can_upgrade'])
        <flux:callout
            inline
            variant="danger"
            :heading="__('Link rotator usage')"
            :text="$usage['limit'] === null
                ? __('You have created :count link rotators. Your plan has unlimited link rotators.', ['count' => number_format($usage['count'])])
                : __('You have created :count of :limit link rotators.', ['count' => number_format($usage['count']), 'limit' => number_format($usage['limit'])])">
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
            :heading="__('Link rotator usage')"
            :text="$usage['limit'] === null
                ? __('You have created :count link rotators. Your plan has unlimited link rotators.', ['count' => number_format($usage['count'])])
                : __('You have created :count of :limit link rotators.', ['count' => number_format($usage['count']), 'limit' => number_format($usage['limit'])])" />
    @endif

    <flux:modal name="rotator-form" class="max-w-lg md:min-w-lg" @close="closeRotatorModal">
        <form wire:submit="saveRotator" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingRotatorId ? __('Edit rotator') : __('Create rotator') }}
                </flux:heading>
                <flux:text>{{ __('Choose how traffic is distributed across attached trackers.') }}</flux:text>
            </div>

            <flux:input
                wire:model="rotator_name"
                :label="__('Name')"
                type="text"
                autocomplete="off"
                placeholder="{{ __('Optional rotator name') }}" />

            <flux:select wire:model.live="rotation_type" :label="__('Rotation type')">
                <flux:select.option value="round_robin">{{ __('Round robin') }}</flux:select.option>
                <flux:select.option value="random">{{ __('Random') }}</flux:select.option>
                <flux:select.option value="weighted">{{ __('Weighted') }}</flux:select.option>
            </flux:select>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $this->rotationTypeDescription() }}
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelRotatorForm">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" type="submit">
                    {{ $editingRotatorId ? __('Update rotator') : __('Create rotator') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-rotator" class="max-w-md md:min-w-md" @close="closeDeleteModal">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete rotator') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete ":slug"? This action cannot be undone.', ['slug' => $deletingRotatorSlug]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelDelete">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="danger" type="button" wire:click="deleteRotator">
                    {{ __('Delete rotator') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="delete-selected-rotators" class="max-w-md md:min-w-md">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete selected rotators') }}</flux:heading>
                <flux:text>{{ trans_choice('Are you sure you want to delete :count selected rotator? This action cannot be undone.|Are you sure you want to delete :count selected rotators? This action cannot be undone.', count($selectedRotatorIds), ['count' => count($selectedRotatorIds)]) }}</flux:text>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelDeleteSelected">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="button" wire:click="deleteSelected">{{ __('Delete selected') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="manage-trackers" class="max-w-4xl md:min-w-4xl" @close="closeManageTrackersModal">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Manage trackers') }}</flux:heading>
                <flux:text>{{ __('Attach trackers and configure their rotation settings.') }}</flux:text>
            </div>

            @php
                $managedRotationType = $managedRotator?->rotation_type ?? 'round_robin';
                $trackerFormColumns = in_array($managedRotationType, ['round_robin', 'weighted'], true)
                    ? 'md:grid-cols-[1fr_120px_auto]'
                    : 'md:grid-cols-[1fr_auto]';
                $trackerTableColspan = 2
                    + (int) ($managedRotationType === 'weighted')
                    + (int) ($managedRotationType === 'round_robin');
            @endphp

            <form wire:submit="saveTracker" class="grid gap-4 {{ $trackerFormColumns }}">
                <flux:select wire:model="tracker_id" :label="__('Link tracker')" :disabled="(bool) $editingTrackerId">
                    <flux:select.option value="">{{ __('Choose tracker') }}</flux:select.option>
                    @foreach ($availableTrackers as $tracker)
                    <flux:select.option value="{{ $tracker->id }}">{{ $tracker->target_url }}</flux:select.option>
                    @endforeach
                    @if ($editingTrackerId)
                    <flux:select.option value="{{ $editingTrackerId }}">{{ __('Editing attached tracker') }}</flux:select.option>
                    @endif
                </flux:select>

                @if ($managedRotationType === 'weighted')
                <div class="space-y-2">
                    <div class="flex h-5 items-center gap-1 text-sm font-medium text-zinc-800 dark:text-white">
                        <span>{{ __('Weight') }}</span>
                        <flux:tooltip :content="__('Used by weighted rotation. Higher values receive a larger share of visits.')">
                            <flux:button
                                variant="ghost"
                                size="xs"
                                icon="information-circle"
                                type="button"
                                class="-my-1 text-zinc-400 hover:text-zinc-700 dark:text-zinc-500 dark:hover:text-zinc-200" />
                        </flux:tooltip>
                    </div>

                    <flux:input wire:model="weight" type="number" min="1" />
                </div>
                @endif

                @if ($managedRotationType === 'round_robin')
                <div class="space-y-2">
                    <div class="flex h-5 items-center gap-1 text-sm font-medium text-zinc-800 dark:text-white">
                        <span>{{ __('Order') }}</span>
                        <flux:tooltip :content="__('Used by round robin. Lower values run first; equal values fall back to the default record order.')">
                            <flux:button
                                variant="ghost"
                                size="xs"
                                icon="information-circle"
                                type="button"
                                class="-my-1 text-zinc-400 hover:text-zinc-700 dark:text-zinc-500 dark:hover:text-zinc-200" />
                        </flux:tooltip>
                    </div>

                    <flux:input wire:model="order_column" type="number" min="0" />
                </div>
                @endif

                <div class="flex items-end gap-2">
                    <flux:button variant="primary" type="submit">
                        {{ $editingTrackerId ? __('Update') : __('Add') }}
                    </flux:button>

                    @if ($editingTrackerId)
                    <flux:button variant="filled" type="button" wire:click="cancelTrackerForm">
                        {{ __('Cancel') }}
                    </flux:button>
                    @endif
                </div>
            </form>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Link tracker') }}</flux:table.column>
                    @if ($managedRotationType === 'weighted')
                    <flux:table.column>{{ __('Weight') }}</flux:table.column>
                    @endif
                    @if ($managedRotationType === 'round_robin')
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    @endif
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($managedRotator?->trackers ?? [] as $tracker)
                    <flux:table.row :key="$tracker->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('linktrackers.redirect', $tracker->tracker_slug) }}" target="_blank" rel="noreferrer" class="block max-w-md truncate">
                                {{ $tracker->target_url }}
                            </flux:link>
                        </flux:table.cell>
                        @if ($managedRotationType === 'weighted')
                        <flux:table.cell>{{ $tracker->pivot->weight }}</flux:table.cell>
                        @endif
                        @if ($managedRotationType === 'round_robin')
                        <flux:table.cell>{{ $tracker->pivot->order_column }}</flux:table.cell>
                        @endif
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-3">
                                <flux:link wire:click.prevent="editTracker({{ $tracker->id }})" class="cursor-pointer">
                                    {{ __('Edit') }}
                                </flux:link>
                                <flux:link wire:click.prevent="removeTracker({{ $tracker->id }})" class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                    {{ __('Remove') }}
                                </flux:link>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $trackerTableColspan }}" align="center">
                            {{ __('No trackers attached yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:modal>

    @php
        $pageRotatorIds = $rotators->pluck('id')->map(fn ($id) => (string) $id)->all();
    @endphp
    <div x-data="{ selected: [] }" x-on:bulk-selection-cleared.window="selected = []" class="space-y-4">
        <div x-show="selected.length > 0" x-cloak>
            <flux:button variant="danger" type="button" icon="trash" x-on:click="$wire.confirmDeleteSelected(selected)">
                {{ __('Delete') }} (<span x-text="selected.length"></span>) {{ __('Rotator(s)') }}
            </flux:button>
        </div>

    <flux:table :paginate="$rotators">
        <flux:table.columns>
            <flux:table.column>
                <input type="checkbox" class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800" x-on:change="selected = $event.target.checked ? [...new Set([...selected, ...@js($pageRotatorIds)])] : selected.filter(id => !@js($pageRotatorIds).includes(id))" x-bind:checked="@js($pageRotatorIds).length > 0 && @js($pageRotatorIds).every(id => selected.includes(id))" aria-label="{{ __('Select or deselect all rotators on this page') }}">
            </flux:table.column>
            <flux:table.column>{{ __('Rotator') }}</flux:table.column>
            <flux:table.column>{{ __('Trackers') }}</flux:table.column>
            <flux:table.column>{{ __('Performance') }}</flux:table.column>
            <flux:table.column>{{ __('Last Hit') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rotators as $rotator)
            <flux:table.row :key="$rotator->id">
                <flux:table.cell>
                    <input type="checkbox" value="{{ $rotator->id }}" x-model="selected" class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800" aria-label="{{ __('Select rotator :name', ['name' => $rotator->rotator_name ?: $rotator->rotator_slug]) }}">
                </flux:table.cell>
                <flux:table.cell>
                    @php($rotatorUrl = route('linkrotators.redirect', $rotator->rotator_slug))

                    <div class="max-w-md space-y-1">
                        <div class="font-medium">{{ $rotator->rotator_name ?: __('Unnamed rotator') }}</div>
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:link href="{{ $rotatorUrl }}" target="_blank" rel="noreferrer" class="min-w-0 break-all font-medium" title="{{ $rotatorUrl }}">
                                {{ $rotatorUrl }}
                            </flux:link>

                            <flux:tooltip :content="__('Copy rotator URL')">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="clipboard-document"
                                    type="button"
                                    class="shrink-0"
                                    x-on:click="navigator.clipboard.writeText(@js($rotatorUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Link rotator URL copied.')) }))"
                                    :aria-label="__('Copy rotator URL')" />
                            </flux:tooltip>
                        </div>
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex items-center gap-1 text-sm">
                        <span>{{ number_format($rotator->trackers_count) }}</span>
                        <flux:link wire:click.prevent="manageTrackers({{ $rotator->id }})" class="cursor-pointer">
                            ({{ __('Edit') }})
                        </flux:link>
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="space-y-1 text-sm tabular-nums">
                        <div><span class="font-medium">{{ number_format($rotator->stats_count) }}</span> <span class="text-zinc-500 dark:text-zinc-400">{{ __('hits') }}</span></div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($rotator->unique_hits_count) }} {{ __('unique') }}</div>
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="space-y-1 text-sm">
                        @if ($rotator->stats_max_created_at)
                        @php($lastHitAt = \Carbon\Carbon::parse($rotator->stats_max_created_at))
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
                            <flux:button :href="route('linkrotators.stats', $rotator->rotator_slug)" variant="ghost" size="sm" icon="chart-bar" wire:navigate :aria-label="__('Stats')" />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Edit')">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" type="button" wire:click="editRotator({{ $rotator->id }})" :aria-label="__('Edit')" />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Delete')">
                            <flux:button variant="ghost" size="sm" icon="trash" type="button" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" wire:click="confirmDeleteRotator({{ $rotator->id }})" :aria-label="__('Delete')" />
                        </flux:tooltip>
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="6" align="center">
                    {{ __('No rotators created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </div>
</section>
