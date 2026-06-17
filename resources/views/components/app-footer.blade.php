@props(['flux' => false])

@php
    $classes = 'border-t border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900';
@endphp

@if ($flux)
    <flux:footer container class="{{ $classes }}">
        <div class="flex flex-col gap-3 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between dark:text-zinc-400">
            <p>&copy; {{ now()->year }} {{ config('app.name', 'HitsTrack') }}. {{ __('All rights reserved.') }}</p>

            <nav class="flex gap-4">
                <flux:link href="{{ route('home') }}" wire:navigate>
                    {{ __('Home') }}
                </flux:link>
            </nav>
        </div>
    </flux:footer>
@else
    <footer class="{{ $classes }}">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-6 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between lg:px-8 dark:text-zinc-400">
            <p>&copy; {{ now()->year }} {{ config('app.name', 'HitsTrack') }}. {{ __('All rights reserved.') }}</p>

            <nav class="flex gap-4">
                <flux:link href="{{ route('home') }}" wire:navigate>
                    {{ __('Home') }}
                </flux:link>
            </nav>
        </div>
    </footer>
@endif
