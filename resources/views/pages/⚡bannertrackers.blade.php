<?php

use App\Models\Banner;
use App\Models\BannerStat;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Banner Trackers')] class extends Component
{
    use WithPagination;

    public ?int $editingBannerId = null;

    public ?int $deletingBannerId = null;

    public string $deletingBannerName = '';

    public string $name = '';

    public string $target_url = '';

    public string $image_url = '';

    public string $alt_text = '';

    public ?int $width = null;

    public ?int $height = null;

    public function createBanner(): void
    {
        $this->resetForm();

        Flux::modal('banner-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'target_url' => ['required', 'url', 'max:255'],
            'image_url' => ['required', 'url', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($this->editingBannerId) {
            Banner::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingBannerId)
                ->update($validated);

            $this->resetForm();
            Flux::modal('banner-form')->close();
            Flux::toast(variant: 'success', text: __('Banner tracker updated.'));

            return;
        }

        Banner::create([
            ...$validated,
            'banner_slug' => $this->generateBannerSlug(),
            'user_id' => Auth::id(),
        ]);

        $this->resetForm();
        Flux::modal('banner-form')->close();
        Flux::toast(variant: 'success', text: __('Banner tracker created.'));
    }

    public function editBanner(int $bannerId): void
    {
        $banner = Banner::query()
            ->where('user_id', Auth::id())
            ->findOrFail($bannerId);

        $this->editingBannerId = $banner->id;
        $this->name = $banner->name;
        $this->target_url = $banner->target_url;
        $this->image_url = $banner->image_url;
        $this->alt_text = (string) $banner->alt_text;
        $this->width = $banner->width;
        $this->height = $banner->height;

        $this->resetValidation();
        Flux::modal('banner-form')->show();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        Flux::modal('banner-form')->close();
    }

    public function closeBannerModal(): void
    {
        $this->resetForm();
    }

    public function confirmDeleteBanner(int $bannerId): void
    {
        $banner = Banner::query()
            ->where('user_id', Auth::id())
            ->findOrFail($bannerId);

        $this->deletingBannerId = $banner->id;
        $this->deletingBannerName = $banner->name;

        Flux::modal('delete-banner')->show();
    }

    public function deleteBanner(): void
    {
        if (! $this->deletingBannerId) {
            return;
        }

        Banner::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->deletingBannerId)
            ->delete();

        if ($this->editingBannerId === $this->deletingBannerId) {
            $this->resetForm();
        }

        $this->resetDeleteState();
        Flux::modal('delete-banner')->close();
        Flux::toast(variant: 'success', text: __('Banner tracker deleted.'));
    }

    public function cancelDelete(): void
    {
        $this->resetDeleteState();
        Flux::modal('delete-banner')->close();
    }

    public function closeDeleteModal(): void
    {
        $this->resetDeleteState();
    }

    public function with(): array
    {
        $banners = Banner::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(25);

        $eventCounts = BannerStat::query()
            ->select('banner_id', 'event_type')
            ->selectRaw('COUNT(*) as total')
            ->whereIn('banner_id', $banners->getCollection()->pluck('id'))
            ->groupBy('banner_id', 'event_type')
            ->get()
            ->groupBy('banner_id');

        $banners->getCollection()->each(function (Banner $banner) use ($eventCounts): void {
            $counts = $eventCounts[$banner->id] ?? collect();
            $banner->impressions_count = (int) ($counts->firstWhere('event_type', 'impression')?->total ?? 0);
            $banner->clicks_count = (int) ($counts->firstWhere('event_type', 'click')?->total ?? 0);
        });

        return ['banners' => $banners];
    }

    private function resetForm(): void
    {
        $this->reset('editingBannerId', 'name', 'target_url', 'image_url', 'alt_text', 'width', 'height');
        $this->resetValidation();
    }

    private function resetDeleteState(): void
    {
        $this->reset('deletingBannerId', 'deletingBannerName');
    }

    private function generateBannerSlug(): string
    {
        do {
            $slug = Str::random(6);
        } while (Banner::query()->where('banner_slug', $slug)->exists());

        return $slug;
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
            <flux:heading class="sr-only">{{ __('Banner Trackers') }}</flux:heading>
            <flux:heading size="xl">{{ __('Banner Trackers') }}</flux:heading>
            <flux:subheading>{{ __('Create banners with impression and click tracking.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" type="button" wire:click="createBanner">
            {{ __('New banner') }}
        </flux:button>
    </div>

    <flux:modal name="banner-form" class="max-w-2xl md:min-w-2xl" @close="closeBannerModal">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingBannerId ? __('Edit banner') : __('Create banner') }}
                </flux:heading>
                <flux:text>{{ __('Set the banner image and click destination.') }}</flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="alt_text" :label="__('Alt text')" />
            </div>

            <flux:input wire:model="target_url" :label="__('Target URL')" type="url" required placeholder="https://example.com/landing-page" />
            <flux:input wire:model="image_url" :label="__('Image URL')" type="url" required placeholder="https://example.com/banner.jpg" />

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="width" :label="__('Width')" type="number" min="1" />
                <flux:input wire:model="height" :label="__('Height')" type="number" min="1" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelEdit">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" type="submit">
                    {{ $editingBannerId ? __('Update banner') : __('Create banner') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-banner" class="max-w-md md:min-w-md" @close="closeDeleteModal">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete banner') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $deletingBannerName]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="filled" type="button" wire:click="cancelDelete">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="danger" type="button" wire:click="deleteBanner">
                    {{ __('Delete banner') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:table :paginate="$banners">
        <flux:table.columns>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Banner/Target') }}</flux:table.column>
            <flux:table.column>{{ __('Image Tracker/Target Tracker') }}</flux:table.column>
            <flux:table.column>{{ __('Impressions') }}</flux:table.column>
            <flux:table.column>{{ __('Clicks') }}</flux:table.column>
            <flux:table.column>{{ __('CTR') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($banners as $banner)
            @php
            $imageUrl = route('bannertrackers.image', $banner->banner_slug);
            $clickUrl = route('bannertrackers.click', $banner->banner_slug);
            $ctr = $banner->impressions_count > 0 ? ($banner->clicks_count / $banner->impressions_count) * 100 : 0;
            @endphp
            <flux:table.row :key="$banner->id">
                <flux:table.cell>{{ $banner->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                <flux:table.cell>{{ $banner->name }}</flux:table.cell>
                <flux:table.cell>
                    <img src="{{ $banner->image_url }}" alt="{{ $banner->alt_text ?: $banner->name }}" class="rounded object-cover mb-2" width="{{ $banner->width }}" height="{{ $banner->height }}">
                    <flux:link href="{{ $banner->target_url }}" target="_blank" rel="noreferrer" class="block truncate">
                        {{ $banner->target_url }}
                    </flux:link>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex min-w-0 max-w-md flex-col items-start gap-1">
                        <div class="flex min-w-0 max-w-full items-center gap-2">
                            <flux:link href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="min-w-0 truncate">
                                {{ $imageUrl }}
                            </flux:link>

                            <flux:tooltip :content="__('Copy image tracker URL')">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="clipboard-document"
                                    type="button"
                                    class="shrink-0"
                                    x-on:click="navigator.clipboard.writeText(@js($imageUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Image tracker URL copied.')) }))"
                                    :aria-label="__('Copy image tracker URL')" />
                            </flux:tooltip>
                        </div>

                        <div class="flex min-w-0 max-w-full items-center gap-2">
                            <flux:link href="{{ $clickUrl }}" target="_blank" rel="noreferrer" class="min-w-0 truncate">
                                {{ $clickUrl }}
                            </flux:link>

                            <flux:tooltip :content="__('Copy click tracker URL')">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="clipboard-document"
                                    type="button"
                                    class="shrink-0"
                                    x-on:click="navigator.clipboard.writeText(@js($clickUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Click tracker URL copied.')) }))"
                                    :aria-label="__('Copy click tracker URL')" />
                            </flux:tooltip>
                        </div>
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ number_format($banner->impressions_count) }}</flux:table.cell>
                <flux:table.cell>{{ number_format($banner->clicks_count) }}</flux:table.cell>
                <flux:table.cell>{{ number_format($ctr, 2) }}%</flux:table.cell>
                <flux:table.cell align=" end">
                    <div class="flex justify-end gap-3">
                        <flux:link
                            :href="route('bannertrackers.stats', $banner->banner_slug)"
                            wire:navigate>
                            {{ __('Stats') }}
                        </flux:link>

                        <flux:link wire:click.prevent="editBanner({{ $banner->id }})" class="cursor-pointer">
                            {{ __('Edit') }}
                        </flux:link>
                        <flux:link wire:click.prevent="confirmDeleteBanner({{ $banner->id }})" class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            {{ __('Delete') }}
                        </flux:link>
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="6" align="center">
                    {{ __('No banners created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>