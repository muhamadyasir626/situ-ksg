<x-layouts::auth :title="__('Masuk')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Masuk ke akun Anda')" :description="__('Masukkan email dan kata sandi Anda di bawah ini untuk masuk')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <x-mary-input
                name="email"
                :label="__('Alamat email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <x-mary-password
                    name="password"
                    :label="__('Kata sandi')"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Kata sandi')"
                    right
                />

                @if (Route::has('password.request'))
                    <a class="absolute top-0 text-sm link link-primary end-0" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Lupa kata sandi?') }}
                    </a>
                @endif
            </div>

            <!-- Remember Me -->
            <x-mary-checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <x-mary-button type="submit" class="btn-primary w-full" data-test="login-button">
                    {{ __('Masuk') }}
                </x-mary-button>
            </div>
        </form>
    </div>
</x-layouts::auth>
