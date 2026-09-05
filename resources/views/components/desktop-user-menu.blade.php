<x-mary-dropdown {{ $attributes }}>
    <x-slot:trigger>
        <button type="button" class="flex w-full items-center gap-2 rounded-md p-2 text-start hover:bg-white/10" data-test="sidebar-menu-button">
            <x-mary-avatar :placeholder="auth()->user()->initials()" />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                <span class="truncate text-xs opacity-70">{{ auth()->user()->email }}</span>
            </div>
            <x-mary-icon name="o-chevron-up-down" class="size-4 opacity-70" />
        </button>
    </x-slot:trigger>

    <div class="text-base-content">
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <x-mary-avatar :placeholder="auth()->user()->initials()" />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                <span class="truncate text-xs opacity-70">{{ auth()->user()->email }}</span>
            </div>
        </div>

        <x-mary-menu-separator />

        <x-mary-menu-item :title="__('Pengaturan')" icon="o-cog" route="profile.edit" />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <li>
                <button type="submit" class="my-0.5 w-full cursor-pointer whitespace-nowrap px-4 py-1.5 hover:text-inherit" data-test="logout-button">
                    <span class="block py-0.5">
                        <x-mary-icon name="o-arrow-right-start-on-rectangle" class="mb-0.5" />
                    </span>
                    <span class="truncate whitespace-nowrap">{{ __('Keluar') }}</span>
                </button>
            </li>
        </form>
    </div>
</x-mary-dropdown>
