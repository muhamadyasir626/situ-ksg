<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Pengaturan Tampilan')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Pengaturan Tampilan') }}</h2>

    <x-pages::settings.layout :heading="__('Tampilan')" :subheading="__('Perbarui preferensi tampilan untuk akun Anda')">
        <div x-data="{ value: window.Appearance.get() }" class="join">
            <button
                type="button"
                class="btn join-item"
                :class="value === 'light' ? 'btn-primary' : 'btn-outline'"
                @click="value = 'light'; window.Appearance.set('light')"
            >
                <x-mary-icon name="o-sun" class="size-4" />
                {{ __('Terang') }}
            </button>
            <button
                type="button"
                class="btn join-item"
                :class="value === 'dark' ? 'btn-primary' : 'btn-outline'"
                @click="value = 'dark'; window.Appearance.set('dark')"
            >
                <x-mary-icon name="o-moon" class="size-4" />
                {{ __('Gelap') }}
            </button>
            <button
                type="button"
                class="btn join-item"
                :class="value === 'system' ? 'btn-primary' : 'btn-outline'"
                @click="value = 'system'; window.Appearance.set('system')"
            >
                <x-mary-icon name="o-computer-desktop" class="size-4" />
                {{ __('Sistem') }}
            </button>
        </div>
    </x-pages::settings.layout>
</section>
