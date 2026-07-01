<flux:dropdown position="bottom" align="start">
    <button
        type="button"
        class="group flex w-full items-center rounded-lg p-1 text-start hover:bg-zinc-800/5 dark:hover:bg-white/10"
        data-test="sidebar-menu-button"
        data-flux-sidebar-profile>
        <div class="shrink-0">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
                :src="auth()->user()->avatarUrl()"
                size="sm" />
        </div>

        <div class="in-data-flux-sidebar-collapsed-desktop:hidden mx-2 grid min-w-0 flex-1 leading-tight">
            <span class="truncate text-sm font-medium text-zinc-500 group-hover:text-zinc-800 dark:text-white/80 dark:group-hover:text-white">
                {{ auth()->user()->name }}
            </span>
            <span class="truncate text-xs text-zinc-400 dark:text-white/50">
                {{ auth()->user()->userType?->label ?? __('Free') }} {{ __('User') }}
            </span>
        </div>

        <div class="in-data-flux-sidebar-collapsed-desktop:hidden ms-auto flex size-8 shrink-0 items-center justify-center">
            <flux:icon.chevron-down class="size-4 text-zinc-400 group-hover:text-zinc-800 dark:text-white/80 dark:group-hover:text-white" />
        </div>
    </button>

    <flux:menu>
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button">
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
