<x-layouts::auth :title="__('Autentikasi dua faktor')">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                focusOtp() {
                    this.$nextTick(() => this.$refs.otp?.focus());
                },
                init() {
                    if (! this.showRecoveryInput) {
                        this.focusOtp();
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.focusOtp();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Kode autentikasi')"
                    :description="__('Masukkan kode autentikasi dari aplikasi autentikator Anda.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Kode pemulihan')"
                    :description="__('Konfirmasi akses ke akun Anda dengan memasukkan salah satu kode pemulihan darurat.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="flex items-center justify-center my-5">
                            <x-mary-input
                                x-ref="otp"
                                x-model="code"
                                name="code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                aria-label="{{ __('Kode OTP') }}"
                                class="mx-auto max-w-40 text-center text-2xl tracking-[0.5em]"
                            />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <x-mary-input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                            />
                        </div>

                        @error('recovery_code')
                            <p class="text-sm text-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <x-mary-button
                        type="submit"
                        class="btn-primary w-full"
                    >
                        {{ __('Lanjutkan') }}
                    </x-mary-button>
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                    <span class="opacity-50">{{ __('atau Anda bisa') }}</span>
                    <div class="inline font-medium underline cursor-pointer opacity-80">
                        <span x-show="!showRecoveryInput" @click="toggleInput()">{{ __('login menggunakan kode pemulihan') }}</span>
                        <span x-show="showRecoveryInput" @click="toggleInput()">{{ __('login menggunakan kode autentikasi') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
