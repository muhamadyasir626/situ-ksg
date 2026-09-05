<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <x-mary-menu aria-label="{{ __('Pengaturan') }}">
            <x-mary-menu-item :title="__('Profil')" route="profile.edit" :active="request()->routeIs('profile.edit')" />
            <x-mary-menu-item :title="__('Keamanan')" route="security.edit" :active="request()->routeIs('security.edit')" />
            <x-mary-menu-item :title="__('Tampilan')" route="appearance.edit" :active="request()->routeIs('appearance.edit')" />
        </x-mary-menu>
    </div>

    <div class="divider md:hidden"></div>

    <div class="flex-1 self-stretch max-md:pt-6">
        <h3 class="text-lg font-semibold">{{ $heading ?? '' }}</h3>
        <p class="text-sm text-base-content/70">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
