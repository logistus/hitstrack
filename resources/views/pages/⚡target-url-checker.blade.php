<?php

use App\Models\LinkTracker;
use App\Support\TargetUrlChecker;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Target URL Checker')] class extends Component
{
    public string $target_url = '';

    public string $tracker_name = '';

    public bool $add_link = false;

    public ?int $tracker_id = null;

    public string $checkedTargetUrl = '';

    public ?array $result = null;

    public function mount(TargetUrlChecker $checker): void
    {
        $this->target_url = (string) request()->query('target_url', '');
        $this->tracker_name = (string) request()->query('tracker_name', '');
        $this->tracker_id = request()->query('tracker_id') !== null ? (int) request()->query('tracker_id') : null;
        $this->add_link = request()->boolean('add_link');

        if (! $this->add_link || $this->target_url === '') {
            $this->redirectRoute('linktrackers', absolute: false, navigate: true);

            return;
        }

        $this->runCheck($checker);
    }

    public function check(TargetUrlChecker $checker): void
    {
        $this->runCheck($checker);
    }

    public function addLink(): void
    {
        $validated = $this->validate([
            'target_url' => ['required', 'url', 'max:255'],
            'tracker_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->linkCanBeAdded()) {
            $this->addError('target_url', __('This URL can only be added after a clean checker result.'));
            Flux::toast(variant: 'warning', text: __('This URL cannot be added because the checker found issues.'));

            return;
        }

        if ($this->tracker_id) {
            LinkTracker::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->tracker_id)
                ->update([
                    'tracker_name' => filled($validated['tracker_name']) ? trim($validated['tracker_name']) : null,
                    'target_url' => $validated['target_url'],
                ]);

            Flux::toast(variant: 'success', text: __('Link tracker updated.'));

            $this->redirectRoute('linktrackers', absolute: false, navigate: true);

            return;
        }

        if ($this->trackerLimitReached()) {
            Flux::toast(variant: 'warning', text: __('Your link tracker limit has been reached.'));

            $this->redirectRoute('linktrackers', absolute: false, navigate: true);

            return;
        }

        LinkTracker::create([
            'user_id' => Auth::id(),
            'tracker_name' => filled($validated['tracker_name']) ? trim($validated['tracker_name']) : null,
            'target_url' => $validated['target_url'],
            'tracker_slug' => $this->generateTrackerSlug(),
        ]);

        Flux::toast(variant: 'success', text: __('Link tracker created.'));

        $this->redirectRoute('linktrackers', absolute: false, navigate: true);
    }

    private function runCheck(TargetUrlChecker $checker): void
    {
        $validated = $this->validate([
            'target_url' => ['required', 'string', 'max:2048'],
        ]);

        $this->result = $checker->check($validated['target_url']);
        $this->checkedTargetUrl = trim($validated['target_url']);
    }

    public function linkCanBeAdded(): bool
    {
        return $this->add_link
            && ($this->result['status'] ?? null) === 'safe'
            && $this->checkedTargetUrl === trim($this->target_url);
    }

    public function actionLabel(): string
    {
        return $this->tracker_id ? __('Update Link') : __('Add Link');
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

    public function headerDescription(string $header): string
    {
        return match ($header) {
            'x-frame-options' => __('Shows whether the page blocks iframe embedding. DENY blocks all frames; SAMEORIGIN only allows the same domain.'),
            'content-security-policy' => __('Shows browser security rules. The frame-ancestors directive controls which sites may embed this page.'),
            'content-type' => __('Shows what kind of content was returned, such as HTML, JSON, PDF, or an image.'),
            'location' => __('Shows the next URL when the response is a redirect.'),
            'referrer-policy' => __('Shows how much referrer information browsers should send when navigating away from this page.'),
            default => __('HTTP response header returned by the target server.'),
        };
    }

    private function trackerLimitReached(): bool
    {
        $limit = Auth::user()?->userType?->max_link_trackers;

        return $limit !== null && LinkTracker::query()->where('user_id', Auth::id())->count() >= $limit;
    }

    private function generateTrackerSlug(): string
    {
        do {
            $slug = Str::random(6);
        } while (LinkTracker::query()->where('tracker_slug', $slug)->exists());

        return $slug;
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

    @if ($result)
        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card class="{{ $this->cardBorderClasses($result['status'] ?? null) }} lg:col-span-2">
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

            <flux:card class="{{ $this->cardBorderClasses($result['security']['status'] ?? null) }}">
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
                                <div class="relative rounded-lg border border-zinc-200 bg-zinc-50 p-3 pr-10 dark:border-zinc-700 dark:bg-zinc-900">
                                    <dt class="font-medium text-zinc-900 dark:text-white">{{ $header }}</dt>
                                    <flux:tooltip>
                                        <button type="button" class="absolute right-3 top-3 flex size-5 items-center justify-center rounded-full border border-zinc-300 text-xs font-semibold text-zinc-500 hover:border-blue-300 hover:text-blue-700 dark:border-zinc-600 dark:text-zinc-400 dark:hover:border-blue-500 dark:hover:text-blue-300" aria-label="{{ __('What does this header show?') }}">
                                            ?
                                        </button>
                                        <flux:tooltip.content class="max-w-72 whitespace-normal text-wrap leading-relaxed">
                                            {{ $this->headerDescription($header) }}
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                    <dd class="mt-1 break-words text-zinc-600 dark:text-zinc-400">{{ $result['headers'][$header] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </flux:card>
        @endif

        @if ($add_link)
            <div class="flex justify-end gap-3">
                @if ($this->linkCanBeAdded())
                    <flux:button variant="primary" type="button" wire:click="addLink">
                        {{ $this->actionLabel() }}
                    </flux:button>
                @else
                    <flux:button variant="filled" :href="route('linktrackers')" wire:navigate>
                        {{ __('Link Trackers') }}
                    </flux:button>
                @endif
            </div>
        @endif
    @endif
</section>
