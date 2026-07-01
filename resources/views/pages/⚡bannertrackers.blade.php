<?php

use App\Models\Banner;
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
        if ($this->bannerLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your banner tracker limit has been reached.'));

            return;
        }

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

        if ($this->bannerLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your banner tracker limit has been reached.'));

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
        $bannerCount = $this->bannerCount();
        $bannerLimit = $this->bannerLimit();

        $banners = Banner::query()
            ->select('banners.*')
            ->selectRaw(
                "(
                    COALESCE((
                        SELECT SUM(impressions)
                        FROM daily_banner_referrer_stats
                        WHERE source_type = ?
                            AND source_id = banners.id
                            AND stat_date < ?
                    ), 0)
                    +
                    COALESCE((
                        SELECT COUNT(*)
                        FROM banner_stats
                        WHERE banner_stats.banner_id = banners.id
                            AND banner_stats.event_type = 'impression'
                            AND banner_stats.created_at >= ?
                    ), 0)
                ) as impressions_count",
                ['banner', today()->toDateString(), today()],
            )
            ->selectRaw(
                "(
                    COALESCE((
                        SELECT SUM(clicks)
                        FROM daily_banner_referrer_stats
                        WHERE source_type = ?
                            AND source_id = banners.id
                            AND stat_date < ?
                    ), 0)
                    +
                    COALESCE((
                        SELECT COUNT(*)
                        FROM banner_stats
                        WHERE banner_stats.banner_id = banners.id
                            AND banner_stats.event_type = 'click'
                            AND banner_stats.created_at >= ?
                    ), 0)
                ) as clicks_count",
                ['banner', today()->toDateString(), today()],
            )
            ->where('user_id', Auth::id())
            ->latest()
            ->simplePaginate(25);

        return [
            'banners' => $banners,
            'usage' => [
                'count' => $bannerCount,
                'limit' => $bannerLimit,
                'remaining' => $bannerLimit === null ? null : max(0, $bannerLimit - $bannerCount),
                'reached' => $bannerLimit !== null && $bannerCount >= $bannerLimit,
            ],
        ];
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

    private function bannerLimit(): ?int
    {
        return Auth::user()?->userType?->max_banner_trackers;
    }

    private function bannerCount(): int
    {
        return Banner::query()->where('user_id', Auth::id())->count();
    }

    private function bannerLimitReached(): bool
    {
        $limit = $this->bannerLimit();

        return $limit !== null && $this->bannerCount() >= $limit;
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

        @unless ($usage['reached'])
            <flux:button variant="primary" type="button" wire:click="createBanner">
                {{ __('New banner') }}
            </flux:button>
        @endunless
    </div>

    <flux:callout
        :variant="$usage['reached'] ? 'warning' : null"
        :heading="__('Banner tracker usage')"
        :text="$usage['limit'] === null
            ? __('You have created :count banner trackers. Your plan has unlimited banner trackers.', ['count' => number_format($usage['count'])])
            : __('You have created :count of :limit banner trackers. :remaining remaining.', ['count' => number_format($usage['count']), 'limit' => number_format($usage['limit']), 'remaining' => number_format($usage['remaining'])])" />

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
            <flux:table.column>{{ __('Banner') }}</flux:table.column>
            <flux:table.column>{{ __('Links') }}</flux:table.column>
            <flux:table.column>{{ __('Performance') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($banners as $banner)
            @php
            $imageUrl = route('bannertrackers.image', $banner->banner_slug);
            $clickUrl = route('bannertrackers.click', $banner->banner_slug);
            $ctr = $banner->impressions_count > 0 ? ($banner->clicks_count / $banner->impressions_count) * 100 : 0;
            $previewWidth = $banner->width ? max(1, (int) round($banner->width / 2)) : 160;
            $previewHeight = $banner->height ? max(1, (int) round($banner->height / 2)) : 80;
            @endphp
            <flux:table.row :key="$banner->id">
                <flux:table.cell>
                    <div class="max-w-lg space-y-2">
                        <div class="truncate font-medium">{{ $banner->name }}</div>
                        <img
                            src="{{ $banner->image_url }}"
                            alt="{{ $banner->alt_text ?: $banner->name }}"
                            class="block rounded bg-zinc-100 object-contain dark:bg-zinc-800"
                            width="{{ $previewWidth }}"
                            height="{{ $previewHeight }}">
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="max-w-md space-y-2 text-sm">
                        <div class="flex min-w-0 gap-2">
                            <span class="shrink-0 font-medium">{{ __('Image') }}:</span>
                            <flux:link href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="min-w-0 truncate" title="{{ $imageUrl }}">
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
                        <div class="flex min-w-0 gap-2">
                            <span class="shrink-0 font-medium">{{ __('Target') }}:</span>
                            <flux:link href="{{ $clickUrl }}" target="_blank" rel="noreferrer" class="min-w-0 truncate" title="{{ $clickUrl }}">
                                {{ $clickUrl }}
                            </flux:link>
                            <flux:tooltip :content="__('Copy target tracker URL')">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="clipboard-document"
                                    type="button"
                                    class="shrink-0"
                                    x-on:click="navigator.clipboard.writeText(@js($clickUrl)).then(() => window.Flux?.toast({ variant: 'success', text: @js(__('Target tracker URL copied.')) }))"
                                    :aria-label="__('Copy target tracker URL')" />
                            </flux:tooltip>
                        </div>
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="space-y-1 text-sm tabular-nums">
                        <div><span class="font-medium">{{ number_format($banner->impressions_count) }}</span> <span class="text-zinc-500 dark:text-zinc-400">{{ __('impressions') }}</span></div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($banner->clicks_count) }} {{ __('clicks') }} · {{ number_format($ctr, 2) }}% CTR</div>
                    </div>
                </flux:table.cell>
                <flux:table.cell align="end">
                    <div class="flex justify-end gap-1">
                        <flux:tooltip :content="__('Stats')">
                            <flux:button
                                :href="route('bannertrackers.stats', $banner->banner_slug)"
                                variant="ghost"
                                size="sm"
                                icon="chart-bar"
                                wire:navigate
                                :aria-label="__('Stats')" />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Edit')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil-square"
                                type="button"
                                wire:click="editBanner({{ $banner->id }})"
                                :aria-label="__('Edit')" />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Delete')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                type="button"
                                class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                wire:click="confirmDeleteBanner({{ $banner->id }})"
                                :aria-label="__('Delete')" />
                        </flux:tooltip>
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="4" align="center">
                    {{ __('No banners created yet.') }}
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
