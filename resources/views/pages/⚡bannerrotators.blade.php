<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\BannerStat;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Banner Rotators')] class extends Component
{
    use WithPagination;

    public ?int $editingRotatorId = null;

    public ?int $deletingRotatorId = null;

    public ?int $managingRotatorId = null;

    public ?int $editingBannerId = null;

    public string $deletingRotatorSlug = '';

    public string $name = '';

    public string $rotation_type = 'round_robin';

    public string $banner_id = '';

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
            'name' => ['nullable', 'string', 'max:255'],
            'rotation_type' => ['required', 'in:round_robin,random,weighted'],
        ]);
        $validated['name'] = filled($validated['name']) ? trim($validated['name']) : null;

        if ($this->editingRotatorId) {
            BannerRotator::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingRotatorId)
                ->update($validated);

            $this->resetRotatorForm();
            Flux::modal('rotator-form')->close();
            Flux::toast(variant: 'success', text: __('Banner rotator updated.'));

            return;
        }

        BannerRotator::create([
            ...$validated,
            'rotator_slug' => $this->generateRotatorSlug(),
            'user_id' => Auth::id(),
        ]);

        $this->resetRotatorForm();
        Flux::modal('rotator-form')->close();
        Flux::toast(variant: 'success', text: __('Banner rotator created.'));
    }

    public function editRotator(int $rotatorId): void
    {
        $rotator = $this->userRotatorsQuery()->findOrFail($rotatorId);

        $this->editingRotatorId = $rotator->id;
        $this->name = (string) ($rotator->name ?? '');
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
        Flux::toast(variant: 'success', text: __('Banner rotator deleted.'));
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

    public function manageBanners(int $rotatorId): void
    {
        $rotator = $this->userRotatorsQuery()->findOrFail($rotatorId);

        $this->managingRotatorId = $rotator->id;
        $this->resetBannerForm();

        Flux::modal('manage-banners')->show();
    }

    public function saveBanner(): void
    {
        $rotator = $this->managedRotator();

        $validated = $this->validate([
            'banner_id' => [$this->editingBannerId ? 'nullable' : 'required', 'integer'],
            'weight' => ['required', 'integer', 'min:1'],
            'order_column' => ['required', 'integer', 'min:0'],
        ]);

        $pivot = [
            'weight' => $validated['weight'],
            'order_column' => $validated['order_column'],
        ];

        if ($this->editingBannerId) {
            $rotator->banners()->updateExistingPivot($this->editingBannerId, $pivot);
            $message = __('Banner rotator item updated.');
        } else {
            $banner = Banner::query()
                ->where('user_id', Auth::id())
                ->findOrFail((int) $validated['banner_id']);

            $rotator->banners()->attach($banner->id, $pivot);
            $message = __('Banner added to rotator.');
        }

        $this->resetBannerForm();
        Flux::toast(variant: 'success', text: $message);
    }

    public function editBanner(int $bannerId): void
    {
        $banner = $this->managedRotator()
            ->banners()
            ->where('banners.id', $bannerId)
            ->firstOrFail();

        $this->editingBannerId = $banner->id;
        $this->banner_id = (string) $banner->id;
        $this->weight = (int) $banner->pivot->weight;
        $this->order_column = (int) $banner->pivot->order_column;

        $this->resetValidation();
    }

    public function removeBanner(int $bannerId): void
    {
        $this->managedRotator()->banners()->detach($bannerId);

        if ($this->editingBannerId === $bannerId) {
            $this->resetBannerForm();
        }

        Flux::toast(variant: 'success', text: __('Banner removed from rotator.'));
    }

    public function cancelBannerForm(): void
    {
        $this->resetBannerForm();
    }

    public function closeManageBannersModal(): void
    {
        $this->reset('managingRotatorId');
        $this->resetBannerForm();
    }

    public function with(): array
    {
        $managedRotator = $this->managingRotatorId
            ? $this->userRotatorsQuery()->with('banners')->find($this->managingRotatorId)
            : null;

        $attachedBannerIds = $managedRotator
            ? $managedRotator->banners->pluck('id')
            : collect();

        $rotators = $this->userRotatorsQuery()
            ->withCount(['stats', 'banners'])
            ->withMax('stats', 'created_at')
            ->latest()
            ->paginate(25);

        $uniqueHitCounts = BannerStat::query()
            ->select('banner_rotator_id')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_hits_count')
            ->whereIn('banner_rotator_id', $rotators->getCollection()->pluck('id'))
            ->groupBy('banner_rotator_id')
            ->pluck('unique_hits_count', 'banner_rotator_id');

        $clickCounts = BannerStat::query()
            ->select('banner_rotator_id')
            ->selectRaw('COUNT(*) as total_clicks_count')
            ->whereIn('banner_rotator_id', $rotators->getCollection()->pluck('id'))
            ->where('event_type', 'click')
            ->groupBy('banner_rotator_id')
            ->pluck('total_clicks_count', 'banner_rotator_id');

        $rotators->getCollection()->each(function (BannerRotator $rotator) use ($clickCounts, $uniqueHitCounts): void {
            $rotator->unique_hits_count = (int) ($uniqueHitCounts[$rotator->id] ?? 0);
            $rotator->total_clicks_count = (int) ($clickCounts[$rotator->id] ?? 0);
        });

        return [
            'rotators' => $rotators,
            'managedRotator' => $managedRotator,
            'availableBanners' => Banner::query()
                ->where('user_id', Auth::id())
                ->when($attachedBannerIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $attachedBannerIds))
                ->latest()
                ->get(),
        ];
    }

    public function rotationTypeDescription(): string
    {
        return match ($this->rotation_type) {
            'random' => __('Random picks one attached banner on each request.'),
            'weighted' => __('Weighted gives banners with higher weight values more traffic.'),
            default => __('Round robin serves attached banners in order, one by one.'),
        };
    }

    private function userRotatorsQuery()
    {
        return BannerRotator::query()->where('user_id', Auth::id());
    }

    private function managedRotator(): BannerRotator
    {
        abort_if(! $this->managingRotatorId, 404);

        return $this->userRotatorsQuery()->findOrFail($this->managingRotatorId);
    }

    private function resetRotatorForm(): void
    {
        $this->reset('editingRotatorId', 'name', 'rotation_type');
        $this->rotation_type = 'round_robin';
        $this->resetValidation();
    }

    private function resetBannerForm(): void
    {
        $this->reset('editingBannerId', 'banner_id', 'weight', 'order_column');
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
        } while (BannerRotator::query()->where('rotator_slug', $slug)->exists());

        return $slug;
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Banner Rotators') }}</flux:heading>
            <flux:heading size="xl">{{ __('Banner Rotators') }}</flux:heading>
            <flux:subheading>{{ __('Rotate traffic across multiple banner trackers.') }}</flux:subheading>
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
                <flux:text>{{ __('Choose how banner requests are distributed.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" autocomplete="off" />

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

    <flux:modal name="manage-banners" class="max-w-4xl md:min-w-4xl" @close="closeManageBannersModal">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Manage banners') }}</flux:heading>
                <flux:text>{{ __('Attach banners and configure their rotation settings.') }}</flux:text>
            </div>

            <form wire:submit="saveBanner" class="grid gap-4 md:grid-cols-[1fr_120px_120px_auto]">
                <flux:select wire:model="banner_id" :label="__('Banner')" :disabled="(bool) $editingBannerId">
                    <flux:select.option value="">{{ __('Choose banner') }}</flux:select.option>
                    @foreach ($availableBanners as $banner)
                    <flux:select.option value="{{ $banner->id }}">{{ $banner->name }}</flux:select.option>
                    @endforeach
                    @if ($editingBannerId)
                    <flux:select.option value="{{ $editingBannerId }}">{{ __('Editing attached banner') }}</flux:select.option>
                    @endif
                </flux:select>

                <flux:input wire:model="weight" :label="__('Weight')" type="number" min="1" />
                <flux:input wire:model="order_column" :label="__('Order')" type="number" min="0" />

                <div class="flex items-end gap-2">
                    <flux:button variant="primary" type="submit">
                        {{ $editingBannerId ? __('Update') : __('Add') }}
                    </flux:button>

                    @if ($editingBannerId)
                    <flux:button variant="filled" type="button" wire:click="cancelBannerForm">
                        {{ __('Cancel') }}
                    </flux:button>
                    @endif
                </div>
            </form>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Banner') }}</flux:table.column>
                    <flux:table.column>{{ __('Weight') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($managedRotator?->banners ?? [] as $banner)
                    <flux:table.row :key="$banner->id">
                        <flux:table.cell>
                            @php
                                $previewWidth = $banner->width ? max(1, (int) round($banner->width / 2)) : 160;
                                $previewHeight = $banner->height ? max(1, (int) round($banner->height / 2)) : null;
                            @endphp
                            <div class="max-w-md space-y-2">
                                <img
                                    src="{{ $banner->image_url }}"
                                    alt="{{ $banner->alt_text ?: $banner->name }}"
                                    class="block rounded object-contain"
                                    style="width: {{ $previewWidth }}px; @if ($previewHeight) height: {{ $previewHeight }}px; @else max-height: 120px; @endif">
                                <span class="block truncate text-sm font-medium">{{ $banner->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $banner->pivot->weight }}</flux:table.cell>
                        <flux:table.cell>{{ $banner->pivot->order_column }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-3">
                                <flux:link wire:click.prevent="editBanner({{ $banner->id }})" class="cursor-pointer">
                                    {{ __('Edit') }}
                                </flux:link>
                                <flux:link wire:click.prevent="removeBanner({{ $banner->id }})" class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                    {{ __('Remove') }}
                                </flux:link>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" align="center">
                            {{ __('No banners attached yet.') }}
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
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Banner rotator URL') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Total Hits') }}</flux:table.column>
            <flux:table.column>{{ __('Total Clicks') }}</flux:table.column>
            <flux:table.column>{{ __('Unique Hits') }}</flux:table.column>
            <flux:table.column>{{ __('Last Hit') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rotators as $rotator)
            @php
                $imageUrl = route('bannerrotators.image', $rotator->rotator_slug);
                $clickUrl = route('bannerrotators.click', $rotator->rotator_slug);
            @endphp
            <flux:table.row :key="$rotator->id">
                <flux:table.cell>{{ $rotator->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                <flux:table.cell>
                    <span class="block max-w-40 truncate">{{ $rotator->name ?: '-' }}</span>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex min-w-0 max-w-md flex-col items-start gap-1">
                        <div class="flex min-w-0 max-w-full items-center gap-2">
                            <flux:link href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="min-w-0 truncate">
                                {{ $imageUrl }}
                            </flux:link>

                            <flux:tooltip :content="__('Copy image rotator URL')">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="clipboard-document"
                                    type="button"
                                    class="shrink-0"
                                    x-on:click="navigator.clipboard.writeText(@js($imageUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Image rotator URL copied.')) }))"
                                    :aria-label="__('Copy image rotator URL')" />
                            </flux:tooltip>
                        </div>

                        <div class="flex min-w-0 max-w-full items-center gap-2">
                            <flux:link href="{{ $clickUrl }}" target="_blank" rel="noreferrer" class="min-w-0 truncate">
                                {{ $clickUrl }}
                            </flux:link>

                            <flux:tooltip :content="__('Copy click rotator URL')">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="clipboard-document"
                                    type="button"
                                    class="shrink-0"
                                    x-on:click="navigator.clipboard.writeText(@js($clickUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Click rotator URL copied.')) }))"
                                    :aria-label="__('Copy click rotator URL')" />
                            </flux:tooltip>
                        </div>
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ str($rotator->rotation_type)->replace('_', ' ')->title() }}</flux:table.cell>
                <flux:table.cell>{{ number_format($rotator->stats_count) }}</flux:table.cell>
                <flux:table.cell>{{ number_format($rotator->total_clicks_count) }}</flux:table.cell>
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
                        <flux:link :href="route('bannerrotators.stats', $rotator->rotator_slug)" wire:navigate>
                            {{ __('Stats') }}
                        </flux:link>
                        <flux:link wire:click.prevent="manageBanners({{ $rotator->id }})" class="cursor-pointer">
                            {{ __('Banners') }} ({{ number_format($rotator->banners_count) }})
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
                <flux:table.cell colspan="9" align="center">
                    {{ __('No banner rotators created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
