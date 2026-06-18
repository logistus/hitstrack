<?php

use App\Models\Rotator;
use App\Models\RotatorStat;
use App\Models\Tracker;
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

    public string $rotation_type = 'round_robin';

    public string $tracker_id = '';

    public int $weight = 1;

    public int $order_column = 0;

    public function createRotator(): void
    {
        $this->resetRotatorForm();

        Flux::modal('rotator-form')->show();
    }

    public function saveRotator(): void
    {
        $validated = $this->validate([
            'rotation_type' => ['required', 'in:round_robin,random,weighted'],
        ]);

        if ($this->editingRotatorId) {
            Rotator::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingRotatorId)
                ->update($validated);

            $this->resetRotatorForm();
            Flux::modal('rotator-form')->close();
            Flux::toast(variant: 'success', text: __('Rotator updated.'));

            return;
        }

        Rotator::create([
            ...$validated,
            'rotator_slug' => $this->generateRotatorSlug(),
            'user_id' => Auth::id(),
        ]);

        $this->resetRotatorForm();
        Flux::modal('rotator-form')->close();
        Flux::toast(variant: 'success', text: __('Rotator created.'));
    }

    public function editRotator(int $rotatorId): void
    {
        $rotator = $this->userRotatorsQuery()->findOrFail($rotatorId);

        $this->editingRotatorId = $rotator->id;
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
        Flux::toast(variant: 'success', text: __('Rotator deleted.'));
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

        $validated = $this->validate([
            'tracker_id' => [$this->editingTrackerId ? 'nullable' : 'required', 'integer'],
            'weight' => ['required', 'integer', 'min:1'],
            'order_column' => ['required', 'integer', 'min:0'],
        ]);

        $pivot = [
            'weight' => $validated['weight'],
            'order_column' => $validated['order_column'],
        ];

        if ($this->editingTrackerId) {
            $rotator->trackers()->updateExistingPivot($this->editingTrackerId, $pivot);
            $message = __('Rotator tracker updated.');
        } else {
            $tracker = Tracker::query()
                ->where('user_id', Auth::id())
                ->findOrFail((int) $validated['tracker_id']);

            $rotator->trackers()->attach($tracker->id, $pivot);
            $message = __('Tracker added to rotator.');
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

        Flux::toast(variant: 'success', text: __('Tracker removed from rotator.'));
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
        $managedRotator = $this->managingRotatorId
            ? $this->userRotatorsQuery()->with('trackers')->find($this->managingRotatorId)
            : null;

        $attachedTrackerIds = $managedRotator
            ? $managedRotator->trackers->pluck('id')
            : collect();

        $rotators = $this->userRotatorsQuery()
            ->withCount(['stats', 'trackers'])
            ->withMax('stats', 'created_at')
            ->latest()
            ->paginate(25);

        $uniqueHitCounts = RotatorStat::query()
            ->select('rotator_id')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits_count')
            ->whereIn('rotator_id', $rotators->getCollection()->pluck('id'))
            ->groupBy('rotator_id')
            ->pluck('unique_hits_count', 'rotator_id');

        $rotators->getCollection()->each(function (Rotator $rotator) use ($uniqueHitCounts): void {
            $rotator->unique_hits_count = (int) ($uniqueHitCounts[$rotator->id] ?? 0);
        });

        return [
            'rotators' => $rotators,
            'managedRotator' => $managedRotator,
            'availableTrackers' => Tracker::query()
                ->where('user_id', Auth::id())
                ->when($attachedTrackerIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $attachedTrackerIds))
                ->latest()
                ->get(),
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
        return Rotator::query()->where('user_id', Auth::id());
    }

    private function managedRotator(): Rotator
    {
        abort_if(! $this->managingRotatorId, 404);

        return $this->userRotatorsQuery()->findOrFail($this->managingRotatorId);
    }

    private function resetRotatorForm(): void
    {
        $this->reset('editingRotatorId', 'rotation_type');
        $this->rotation_type = 'round_robin';
        $this->resetValidation();
    }

    private function resetTrackerForm(): void
    {
        $this->reset('editingTrackerId', 'tracker_id', 'weight', 'order_column');
        $this->weight = 1;
        $this->order_column = 0;
        $this->resetValidation();
    }

    private function resetDeleteState(): void
    {
        $this->reset('deletingRotatorId', 'deletingRotatorSlug');
    }

    private function generateRotatorSlug(): string
    {
        do {
            $slug = Str::random(6);
        } while (Rotator::query()->where('rotator_slug', $slug)->exists());

        return $slug;
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Rotators') }}</flux:heading>
            <flux:heading size="xl">{{ __('Rotators') }}</flux:heading>
            <flux:subheading>{{ __('Create rotating links from your trackers.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" type="button" wire:click="createRotator">
            {{ __('New rotator') }}
        </flux:button>
    </div>

    <flux:modal name="rotator-form" class="max-w-lg md:min-w-lg" @close="closeRotatorModal">
        <form wire:submit="saveRotator" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingRotatorId ? __('Edit rotator') : __('Create rotator') }}
                </flux:heading>
                <flux:text>{{ __('Choose how traffic is distributed across attached trackers.') }}</flux:text>
            </div>

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

    <flux:modal name="manage-trackers" class="max-w-4xl md:min-w-4xl" @close="closeManageTrackersModal">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Manage trackers') }}</flux:heading>
                <flux:text>{{ __('Attach trackers and configure their rotation settings.') }}</flux:text>
            </div>

            <form wire:submit="saveTracker" class="grid gap-4 md:grid-cols-[1fr_120px_120px_auto]">
                <flux:select wire:model="tracker_id" :label="__('Tracker')" :disabled="(bool) $editingTrackerId">
                    <flux:select.option value="">{{ __('Choose tracker') }}</flux:select.option>
                    @foreach ($availableTrackers as $tracker)
                    <flux:select.option value="{{ $tracker->id }}">{{ $tracker->target_url }}</flux:select.option>
                    @endforeach
                    @if ($editingTrackerId)
                    <flux:select.option value="{{ $editingTrackerId }}">{{ __('Editing attached tracker') }}</flux:select.option>
                    @endif
                </flux:select>

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
                    <flux:table.column>{{ __('Tracker') }}</flux:table.column>
                    <flux:table.column>{{ __('Weight') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($managedRotator?->trackers ?? [] as $tracker)
                    <flux:table.row :key="$tracker->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('trackers.redirect', $tracker->tracker_slug) }}" target="_blank" rel="noreferrer" class="block max-w-md truncate">
                                {{ $tracker->target_url }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $tracker->pivot->weight }}</flux:table.cell>
                        <flux:table.cell>{{ $tracker->pivot->order_column }}</flux:table.cell>
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
                        <flux:table.cell colspan="4" align="center">
                            {{ __('No trackers attached yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:modal>

    <flux:table :paginate="$rotators">
        <flux:table.columns>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column>{{ __('Rotator URL') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Total Hits') }}</flux:table.column>
            <flux:table.column>{{ __('Unique Hits') }}</flux:table.column>
            <flux:table.column>{{ __('Last Hit') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rotators as $rotator)
            <flux:table.row :key="$rotator->id">
                <flux:table.cell>{{ $rotator->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                <flux:table.cell>
                    @php($rotatorUrl = route('rotators.redirect', $rotator->rotator_slug))

                    <div class="flex max-w-md items-center gap-2">
                        <flux:link href="{{ $rotatorUrl }}" target="_blank" rel="noreferrer" class="block min-w-0 truncate">
                            {{ $rotatorUrl }}
                        </flux:link>

                        <flux:tooltip :content="__('Copy rotator URL')">
                            <flux:button
                                variant="ghost"
                                size="xs"
                                icon="clipboard-document"
                                type="button"
                                class="shrink-0"
                                x-on:click="navigator.clipboard.writeText(@js($rotatorUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Rotator URL copied.')) }))"
                                :aria-label="__('Copy rotator URL')" />
                        </flux:tooltip>
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ str($rotator->rotation_type)->replace('_', ' ')->title() }}</flux:table.cell>
                <flux:table.cell>{{ number_format($rotator->stats_count) }}</flux:table.cell>
                <flux:table.cell>{{ number_format($rotator->unique_hits_count) }}</flux:table.cell>
                <flux:table.cell>
                    @if ($rotator->stats_max_created_at)
                    @php($lastHitAt = \Carbon\Carbon::parse($rotator->stats_max_created_at))
                    <span title="{{ $lastHitAt->format('Y-m-d H:i:s') }}">
                        {{ $lastHitAt->diffForHumans(short: true) }}
                    </span>
                    @else
                    {{ __('Never') }}
                    @endif
                </flux:table.cell>
                <flux:table.cell align="end">
                    <div class="flex justify-end gap-3">
                        <flux:link :href="route('rotators.stats', $rotator->rotator_slug)" wire:navigate>
                            {{ __('Stats') }}
                        </flux:link>
                        <flux:link wire:click.prevent="manageTrackers({{ $rotator->id }})" class="cursor-pointer">
                            {{ __('Trackers') }} ({{ number_format($rotator->trackers_count) }})
                        </flux:link>
                        <flux:link wire:click.prevent="editRotator({{ $rotator->id }})" class="cursor-pointer">
                            {{ __('Edit') }}
                        </flux:link>
                        <flux:link wire:click.prevent="confirmDeleteRotator({{ $rotator->id }})" class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            {{ __('Delete') }}
                        </flux:link>
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="7" align="center">
                    {{ __('No rotators created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
