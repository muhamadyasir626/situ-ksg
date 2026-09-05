<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="text-lg font-semibold">{{ __('Hapus akun') }}</h3>
        <p class="text-sm text-base-content/70">{{ __('Hapus akun Anda beserta seluruh sumber dayanya') }}</p>
    </div>

    <x-mary-button class="btn-error" wire:click="$dispatch('open-delete-user-modal')" data-test="delete-user-button">
        {{ __('Hapus akun') }}
    </x-mary-button>

    <livewire:pages::settings.delete-user-modal />
</section>
