<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Pengaturan Keamanan')] class extends Component {
    use PasswordValidationRules, Toast;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->success(__('Kata sandi diperbarui.'));
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Pengaturan Keamanan') }}</h2>

    <x-pages::settings.layout :heading="__('Perbarui kata sandi')" :subheading="__('Pastikan akun Anda menggunakan kata sandi acak yang panjang agar tetap aman')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-mary-password
                wire:model="current_password"
                :label="__('Kata sandi saat ini')"
                required
                autocomplete="current-password"
                right
            />
            <x-mary-password
                wire:model="password"
                :label="__('Kata sandi baru')"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                right
            />
            <x-mary-password
                wire:model="password_confirmation"
                :label="__('Konfirmasi kata sandi')"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                right
            />

            <div class="flex items-center gap-4">
                <x-mary-button type="submit" class="btn-primary" data-test="update-password-button">
                    {{ __('Simpan') }}
                </x-mary-button>
            </div>
        </form>

        @if ($canManageTwoFactor)
            <section class="mt-12">
                <h3 class="text-lg font-semibold">{{ __('Autentikasi dua faktor') }}</h3>
                <p class="text-sm text-base-content/70">{{ __('Kelola pengaturan autentikasi dua faktor Anda') }}</p>

                <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <p class="text-sm">
                                {{ __('Anda akan diminta memasukkan PIN acak yang aman saat login, yang dapat diambil dari aplikasi pendukung TOTP di ponsel Anda.') }}
                            </p>

                            <div class="flex justify-start">
                                <x-mary-button
                                    class="btn-error"
                                    wire:click="disable"
                                >
                                    {{ __('Nonaktifkan 2FA') }}
                                </x-mary-button>
                            </div>

                            <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="space-y-4">
                            <p class="text-sm text-base-content/70">
                                {{ __('Saat Anda mengaktifkan autentikasi dua faktor, Anda akan diminta memasukkan PIN aman saat login. PIN ini dapat diambil dari aplikasi pendukung TOTP di ponsel Anda.') }}
                            </p>

                            <x-mary-button
                                class="btn-primary"
                                wire:click="$dispatch('start-two-factor-setup')"
                            >
                                {{ __('Aktifkan 2FA') }}
                            </x-mary-button>

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </x-pages::settings.layout>
</section>
