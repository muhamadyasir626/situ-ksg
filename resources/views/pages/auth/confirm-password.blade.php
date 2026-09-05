<x-layouts::auth :title="__('Konfirmasi kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Konfirmasi kata sandi')"
            :description="__('Ini adalah area aman dari aplikasi. Silakan konfirmasi kata sandi Anda sebelum melanjutkan.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-mary-password
                name="password"
                :label="__('Kata sandi')"
                required
                autocomplete="current-password"
                :placeholder="__('Kata sandi')"
                right
            />

            <x-mary-button type="submit" class="btn-primary w-full" data-test="confirm-password-button">
                {{ __('Konfirmasi') }}
            </x-mary-button>
        </form>
    </div>
</x-layouts::auth>
