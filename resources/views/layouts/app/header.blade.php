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
    class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:header class="border-b border-zinc-200 bg-zinc-50 !px-0 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="container mx-auto flex min-h-14 items-center">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('trackers') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item :href="route('home')" wire:navigate>
                    {{ __('Home') }}
                </flux:navbar.item>
                <flux:navbar.item :href="route('trackers')" :current="request()->routeIs('trackers')" wire:navigate>
                    {{ __('Link Trackers') }}
                </flux:navbar.item>
                <flux:navbar.item :href="route('rotators')" :current="request()->routeIs('rotators')" wire:navigate>
                    {{ __('Link Rotators') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-desktop-user-menu />

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
                class="ms-3 flex items-center gap-2">
                <flux:icon.sun class="size-4 text-zinc-500 dark:text-zinc-400" />
                <button
                    type="button"
                    x-on:click="updateTheme()"
                    x-bind:aria-checked="dark.toString()"
                    x-bind:data-checked="dark ? '' : null"
                    role="switch"
                    aria-label="{{ __('Toggle dark mode') }}"
                    class="group relative inline-flex h-5 w-8 min-w-8 items-center rounded-full bg-zinc-800/15 outline-offset-2 transition data-checked:bg-(--color-accent) dark:border-0 dark:bg-(--color-accent)">
                    <span
                        class="size-3.5 translate-x-[0.1875rem] rounded-full bg-white transition group-data-checked:translate-x-[0.9375rem] group-data-checked:bg-(--color-accent-foreground) dark:translate-x-[0.9375rem] dark:bg-(--color-accent-foreground)"></span>
                </button>
                <flux:icon.moon class="size-4 text-zinc-500 dark:text-zinc-400" />
            </div>
            @endpersist
        </div>
    </flux:header>

    <!-- Mobile Menu -->
    <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('trackers') }}" wire:navigate />
            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group>
                <flux:sidebar.item :href="route('home')" wire:navigate>
                    {{ __('Home')  }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('trackers')" :current="request()->routeIs('trackers')" wire:navigate>
                    {{ __('Link Trackers')  }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('rotators')" :current="request()->routeIs('rotators')" wire:navigate>
                    {{ __('Link Rotators')  }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />
    </flux:sidebar>

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