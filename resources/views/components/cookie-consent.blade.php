<div
    id="cookie-consent"
    class="fixed inset-x-4 bottom-4 z-50 hidden rounded-md border border-zinc-200 bg-white p-4 text-zinc-900 shadow-xl shadow-black/20 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100 sm:left-auto sm:max-w-md">
    <div class="space-y-4">
        <div class="space-y-1">
            <p class="text-sm font-semibold">{{ __('Cookie preferences') }}</p>
            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                {{ __('We use essential storage for preferences and optional analytics cookies to understand site usage.') }}
            </p>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button
                type="button"
                data-cookie-consent-reject
                class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/10">
                {{ __('Reject') }}
            </button>
            <button
                type="button"
                data-cookie-consent-accept
                class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200">
                {{ __('Accept all') }}
            </button>
        </div>
    </div>
</div>

<script>
    (() => {
        const key = 'hitstrack.cookieConsent';
        const dialog = document.getElementById('cookie-consent');

        if (!dialog || window.localStorage.getItem(key)) {
            return;
        }

        const close = (value) => {
            window.localStorage.setItem(key, value);
            dialog.classList.add('hidden');

            if (value === 'accepted') {
                window.loadHitstrackAnalytics?.();
            }
        };

        dialog.classList.remove('hidden');
        dialog.querySelector('[data-cookie-consent-accept]')?.addEventListener('click', () => close('accepted'));
        dialog.querySelector('[data-cookie-consent-reject]')?.addEventListener('click', () => close('rejected'));
    })();
</script>
