@props(['flux' => false])

@php
$classes = 'border-t border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900';
@endphp

@if ($flux)
<flux:footer class="{{ $classes }} !p-0">
    <div class="container mx-auto flex min-h-14 flex-col justify-center gap-3 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between dark:text-zinc-400">
        <p>&copy; {{ now()->year }} {{ config('app.name', 'HitsTrack') }}. {{ __('All rights reserved.') }}</p>

        <nav class="flex gap-4">
            <flux:link href="{{ route('home') }}" wire:navigate>
                {{ __('Home') }}
            </flux:link>
            <flux:link href="https://datacrove.com/" wire:navigate>
                {{ __('Datacrove') }}
            </flux:link>
        </nav>
    </div>
</flux:footer>
@else
<footer class="{{ $classes }}">
    <div class="container mx-auto flex min-h-14 flex-col justify-center gap-3 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between dark:text-zinc-400">
        <p>&copy; {{ now()->year }} {{ config('app.name', 'HitsTrack') }}. {{ __('All rights reserved.') }}</p>

        <nav class="flex gap-4">
            <flux:link href="https://datacrove.com/" wire:navigate>
                {{ __('Datacrove') }}
            </flux:link>
        </nav>
    </div>
</footer>
@endif