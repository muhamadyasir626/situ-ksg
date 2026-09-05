<x-layouts::auth :title="__('Lupa kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Lupa kata sandi')" :description="__('Masukkan email Anda untuk menerima tautan reset kata sandi')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <x-mary-input
                name="email"
                :label="__('Alamat email')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <x-mary-button type="submit" class="btn-primary w-full" data-test="email-password-reset-link-button">
                {{ __('Kirim tautan reset kata sandi') }}
            </x-mary-button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/70">
            <span>{{ __('Atau, kembali ke') }}</span>
            <a class="link link-primary" href="{{ route('login') }}" wire:navigate>{{ __('halaman masuk') }}</a>
        </div>
    </div>
</x-layouts::auth>
