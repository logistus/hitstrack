<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    x-data
    x-init="
        window.setAppAppearance(window.localStorage.getItem('flux.appearance') || 'light');

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(() => {
                window.setAppAppearance(window.localStorage.getItem('flux.appearance') || 'light');
            });
        });
    "
    class="min-h-screen bg-white dark:bg-zinc-950">
    <flux:sidebar collapsible="mobile" sticky class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/95">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group>
                <flux:sidebar.item :href="route('dashboard')" icon="home" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard')  }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Link')">
                <flux:sidebar.item :href="route('linktrackers')" icon="chart-bar" :current="request()->routeIs('linktrackers*')" wire:navigate>
                    {{ __('Link Trackers')  }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('linkrotators')" icon="arrows-right-left" :current="request()->routeIs('linkrotators*')" wire:navigate>
                    {{ __('Link Rotators')  }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('referrers')" icon="globe-alt" :current="request()->routeIs('referrers')" wire:navigate>
                    {{ __('All Referrers') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Banner')">
                <flux:sidebar.item :href="route('bannertrackers')" icon="photo" :current="request()->routeIs('bannertrackers*')" wire:navigate>
                    {{ __('Banner Trackers')  }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('bannerrotators')" icon="rectangle-stack" :current="request()->routeIs('bannerrotators*')" wire:navigate>
                    {{ __('Banner Rotators')  }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('banner-referrers')" icon="globe-alt" :current="request()->routeIs('banner-referrers')" wire:navigate>
                    {{ __('All Referrers') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        @persist('appearance-switch')
        <div
            x-data="{
                dark: window.localStorage.getItem('flux.appearance') === 'dark',

                init() {
                    this.sync();

                    document.addEventListener('livewire:navigated', () => {
                        this.sync();
                    });
                },

                sync() {
                    this.dark = window.localStorage.getItem('flux.appearance') === 'dark';
                },

                updateTheme() {
                    this.dark = ! this.dark;
                    window.setAppAppearance(this.dark ? 'dark' : 'light');
                },
            }"
            class="flex items-center justify-between rounded-lg px-2 py-2">
            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <flux:icon.sun class="size-4" />
                <span>{{ __('Appearance') }}</span>
            </div>
            <button
                type="button"
                x-on:click="updateTheme()"
                x-bind:aria-checked="dark.toString()"
                x-bind:data-checked="dark ? '' : null"
                role="switch"
                aria-label="{{ __('Toggle dark mode') }}"
                class="group relative inline-flex h-5 w-8 min-w-8 items-center rounded-full bg-zinc-800/15 outline-offset-2 transition data-checked:bg-(--color-accent) dark:border-0 dark:bg-(--color-accent)">
                <span class="size-3.5 translate-x-[0.1875rem] rounded-full bg-white transition group-data-checked:translate-x-[0.9375rem] group-data-checked:bg-(--color-accent-foreground) dark:translate-x-[0.9375rem] dark:bg-(--color-accent-foreground)"></span>
            </button>
        </div>
        @endpersist

        <x-desktop-user-menu />
    </flux:sidebar>

    <flux:header class="border-b border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900/95">
        <flux:sidebar.toggle icon="bars-2" inset="left" />
        <flux:spacer />
        <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
    </flux:header>

    {{ $slot }}

    <x-app-footer flux />

    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
