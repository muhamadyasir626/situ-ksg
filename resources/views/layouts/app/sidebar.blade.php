@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-app-bg text-ink dark:bg-zinc-900 dark:text-zinc-100">
        <div class="drawer lg:drawer-open">
            <input id="app-sidebar-drawer" type="checkbox" class="drawer-toggle" />

            <div class="drawer-content flex min-h-screen flex-col">
                <!-- Mobile top bar -->
                <div class="flex items-center justify-between border-b border-line bg-white px-4 py-2 dark:border-zinc-700 dark:bg-zinc-900 lg:hidden">
                    <label for="app-sidebar-drawer" class="btn btn-square btn-ghost btn-sm" aria-label="{{ __('Buka menu') }}">
                        <x-mary-icon name="o-bars-2" class="size-5" />
                    </label>

                    <x-desktop-user-menu />
                </div>

                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>

            <div class="drawer-side z-30">
                <label for="app-sidebar-drawer" aria-label="{{ __('Tutup menu') }}" class="drawer-overlay"></label>

                <aside class="flex h-full w-72 flex-col border-e border-white/10 bg-navy text-zinc-200">
                    <a href="{{ route('dashboard') }}" wire:navigate class="px-4 py-3" aria-label="SITU-KSG - Dashboard">
                        <x-app-logo-icon class="h-auto w-full rounded-md" />
                    </a>

                    <div class="flex-1 overflow-y-auto px-2 pb-4">
                        <x-mary-menu activate-by-route active-bg-color="bg-white/10" class="text-zinc-300 [&_a]:text-inherit [&_a:hover]:!bg-white/5 [&_a:hover]:!text-white [&_.mary-active-menu]:!text-white">
                            <x-mary-menu-item :title="__('Dashboard')" icon="o-home" route="dashboard" />
                            <x-mary-menu-item :title="__('Indikator')" icon="o-viewfinder-circle" route="indikator" />
                            <x-mary-menu-item :title="__('Realisasi')" icon="o-arrow-trending-up" route="realisasi" />
                            @can('viewAny', \App\Models\VerificationNote::class)
                                <x-mary-menu-item :title="__('Verifikasi')" icon="o-check-circle" route="verifikasi" />
                            @endcan
                            <x-mary-menu-item :title="__('Risiko & Kendala')" icon="o-exclamation-triangle" route="risiko" />
                            <x-mary-menu-item :title="__('Laporan')" icon="o-document-text" route="laporan" />
                            @if (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Auditor'))
                                <x-mary-menu-item :title="__('Audit Trail')" icon="o-clipboard-document-list" route="audit-trail" />
                            @endif
                        </x-mary-menu>

                        @can('viewAny', \App\Models\Unit::class)
                            <x-mary-menu
                                activate-by-route
                                active-bg-color="bg-white/10"
                                separator
                                :title="__('Administrasi')"
                                class="text-zinc-300 [&_.menu-title]:!text-zinc-400 [&_hr]:border-white/10 [&_a]:text-inherit [&_a:hover]:!bg-white/5 [&_a:hover]:!text-white [&_.mary-active-menu]:!text-white"
                            >
                                <x-mary-menu-item :title="__('Pengguna')" icon="o-users" route="admin.users" />
                                <x-mary-menu-item :title="__('Unit Kerja')" icon="o-building-office-2" route="admin.units" />
                                <x-mary-menu-item :title="__('Periode')" icon="o-calendar" route="admin.periods" />
                                <x-mary-menu-item :title="__('Indikator')" icon="o-viewfinder-circle" route="admin.indicators" />
                                <x-mary-menu-item :title="__('Penugasan')" icon="o-clipboard-document-check" route="admin.assignments" />
                            </x-mary-menu>
                        @endcan
                    </div>

                    <div class="border-t border-white/10 p-2">
                        <x-desktop-user-menu class="[&_button]:text-zinc-200 [&_button:hover]:bg-white/10" />
                    </div>
                </aside>
            </div>
        </div>

        <x-loading-screen />
        <x-mary-toast />
    </body>
</html>
