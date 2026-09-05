<x-layouts::auth :title="__('Reset kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset kata sandi')" :description="__('Silakan masukkan kata sandi baru Anda di bawah ini')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <x-mary-input
                name="email"
                value="{{ request('email') }}"
                :label="__('Email')"
                type="email"
                required
                autocomplete="email"
            />

            <!-- Password -->
            <x-mary-password
                name="password"
                :label="__('Kata sandi')"
                required
                autocomplete="new-password"
                :placeholder="__('Kata sandi')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                right
            />

            <!-- Confirm Password -->
            <x-mary-password
                name="password_confirmation"
                :label="__('Konfirmasi kata sandi')"
                required
                autocomplete="new-password"
                :placeholder="__('Konfirmasi kata sandi')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                right
            />

            <div class="flex items-center justify-end">
                <x-mary-button type="submit" class="btn-primary w-full" data-test="reset-password-button">
                    {{ __('Reset kata sandi') }}
                </x-mary-button>
            </div>
        </form>
    </div>
</x-layouts::auth>
