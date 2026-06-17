<x-layouts::app.header :title="$title ?? null">
    <flux:main>
        <div class="container mx-auto flex justify-end pb-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Server time') }}:
                <time datetime="{{ now()->toIso8601String() }}">
                    {{ now()->format('Y-m-d H:i:s') }}
                </time>
            </flux:text>
        </div>

        {{ $slot }}
    </flux:main>
</x-layouts::app.header>