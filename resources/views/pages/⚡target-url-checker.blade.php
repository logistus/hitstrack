<?php

use App\Support\TargetUrlChecker;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Target URL Checker')] class extends Component
{
    public string $target_url = '';

    public ?array $result = null;

    public function check(TargetUrlChecker $checker): void
    {
        $validated = $this->validate([
            'target_url' => ['required', 'string', 'max:2048'],
        ]);

        $this->result = $checker->check($validated['target_url']);
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

    public function cardBorderClasses(?string $status): string
    {
        return match ($status) {
            'safe' => 'border-emerald-200 dark:border-emerald-900/60',
            'warning' => 'border-amber-200 dark:border-amber-900/60',
            'danger' => 'border-red-200 dark:border-red-900/60',
            default => 'border-zinc-200 dark:border-zinc-700',
        };
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl">{{ __('Target URL Checker') }}</flux:heading>
        <flux:subheading>
            {{ __('Check a target URL for iframe blocking headers, frame-breaker scripts, redirects, and obvious suspicious HTML/JavaScript patterns before creating a link tracker.') }}
        </flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="check" class="space-y-4">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <flux:input
                    wire:model="target_url"
                    :label="__('Target URL')"
                    type="url"
                    placeholder="https://example.com/landing-page"
                    required />

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Check URL') }}</span>
                    <span wire:loading>{{ __('Checking...') }}</span>
                </flux:button>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Note: malware detection here is heuristic only. For strong reputation checks, connect a service such as Google Safe Browsing or VirusTotal later.') }}
            </flux:text>
        </form>
    </flux:card>

    @if ($result)
        <div class="grid gap-4 lg:grid-cols-3">
            <flux:card class="{{ $this->cardBorderClasses($result['status'] ?? null) }} lg:col-span-3">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border px-2.5 py-1 text-xs font-medium {{ $this->badgeClasses($result['status'] ?? null) }}">
                                {{ str($result['status'] ?? 'unknown')->title() }}
                            </span>
                            @if ($result['http_status'])
                                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                    HTTP {{ $result['http_status'] }}
                                </span>
                            @endif
                        </div>

                        <flux:heading size="lg">{{ $result['summary'] }}</flux:heading>

                        <div class="space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            <div class="truncate">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ __('Checked') }}:</span>
                                {{ $result['url'] }}
                            </div>
                            <div class="truncate">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ __('Final URL') }}:</span>
                                {{ $result['final_url'] }}
                            </div>
                            @if ($result['content_type'])
                                <div class="truncate">
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ __('Content Type') }}:</span>
                                    {{ $result['content_type'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </flux:card>

            <flux:card class="{{ $this->cardBorderClasses($result['frame']['status'] ?? null) }}">
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading>{{ __('Frame / Iframe') }}</flux:heading>
                        <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium {{ $this->badgeClasses($result['frame']['status'] ?? null) }}">
                            {{ $result['frame']['label'] }}
                        </span>
                    </div>

                    <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                        @foreach ($result['frame']['findings'] as $finding)
                            <li class="flex gap-2">
                                <span class="mt-1 size-1.5 shrink-0 rounded-full bg-current"></span>
                                <span>{{ $finding }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </flux:card>

            <flux:card class="{{ $this->cardBorderClasses($result['security']['status'] ?? null) }} lg:col-span-2">
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading>{{ __('Suspicious Code Scan') }}</flux:heading>
                        <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium {{ $this->badgeClasses($result['security']['status'] ?? null) }}">
                            {{ $result['security']['label'] }}
                        </span>
                    </div>

                    <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                        @foreach ($result['security']['findings'] as $finding)
                            <li class="flex gap-2">
                                <span class="mt-1 size-1.5 shrink-0 rounded-full bg-current"></span>
                                <span>{{ $finding }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </flux:card>
        </div>

        @if (! empty($result['redirects']))
            <flux:card>
                <div class="space-y-4">
                    <flux:heading>{{ __('Redirect Chain') }}</flux:heading>

                    <div class="space-y-3 text-sm">
                        @foreach ($result['redirects'] as $redirect)
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="font-medium text-zinc-900 dark:text-white">HTTP {{ $redirect['status'] }}</div>
                                <div class="truncate text-zinc-500 dark:text-zinc-400">{{ $redirect['from'] }}</div>
                                <div class="truncate text-zinc-700 dark:text-zinc-300">→ {{ $redirect['to'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </flux:card>
        @endif

        @if (! empty($result['headers']))
            <flux:card>
                <div class="space-y-4">
                    <flux:heading>{{ __('Relevant Headers') }}</flux:heading>

                    <dl class="grid gap-3 text-sm md:grid-cols-2">
                        @foreach (['x-frame-options', 'content-security-policy', 'content-type', 'location', 'referrer-policy'] as $header)
                            @if (! empty($result['headers'][$header]))
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                                    <dt class="font-medium text-zinc-900 dark:text-white">{{ $header }}</dt>
                                    <dd class="mt-1 break-words text-zinc-600 dark:text-zinc-400">{{ $result['headers'][$header] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </flux:card>
        @endif
    @endif
</section>
