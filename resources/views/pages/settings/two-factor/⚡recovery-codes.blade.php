<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="py-6 space-y-6 border shadow-sm rounded-xl border-base-300"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="px-6 space-y-2">
        <div class="flex items-center gap-2">
            <x-mary-icon name="o-lock-closed" class="size-4" />
            <h3 class="text-lg font-semibold">{{ __('Kode pemulihan 2FA') }}</h3>
        </div>
        <p class="text-sm text-base-content/70">
            {{ __('Kode pemulihan memungkinkan Anda mendapatkan kembali akses jika kehilangan perangkat 2FA. Simpan di pengelola kata sandi yang aman.') }}
        </p>
    </div>

    <div class="px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-mary-button
                x-show="!showRecoveryCodes"
                class="btn-primary"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            >
                <x-mary-icon name="o-eye" class="size-4" />
                {{ __('Lihat kode pemulihan') }}
            </x-mary-button>

            <x-mary-button
                x-show="showRecoveryCodes"
                class="btn-primary"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            >
                <x-mary-icon name="o-eye-slash" class="size-4" />
                {{ __('Sembunyikan kode pemulihan') }}
            </x-mary-button>

            @if (filled($recoveryCodes))
                <x-mary-button
                    x-show="showRecoveryCodes"
                    class="btn-ghost"
                    wire:click="regenerateRecoveryCodes"
                >
                    <x-mary-icon name="o-arrow-path" class="size-4" />
                    {{ __('Buat ulang kode') }}
                </x-mary-button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <x-mary-alert class="alert-error" icon="o-x-circle" :title="$message" />
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-base-200"
                        role="list"
                        aria-label="{{ __('Kode pemulihan') }}"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-base-content/70">
                        {{ __('Setiap kode pemulihan hanya dapat digunakan sekali untuk mengakses akun Anda dan akan dihapus setelah digunakan. Bila memerlukan lebih banyak, klik Buat ulang kode di atas.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
