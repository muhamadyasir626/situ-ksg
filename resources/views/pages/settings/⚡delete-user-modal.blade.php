<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    public bool $showDeleteModal = false;

    #[On('open-delete-user-modal')]
    public function openModal(): void
    {
        $this->showDeleteModal = true;
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-mary-modal wire:model="showDeleteModal" :title="__('Apakah Anda yakin ingin menghapus akun Anda?')">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <p class="text-sm text-base-content/70">
            {{ __('Setelah akun Anda dihapus, seluruh sumber daya dan datanya akan dihapus secara permanen. Silakan masukkan kata sandi Anda untuk mengonfirmasi bahwa Anda ingin menghapus akun ini secara permanen.') }}
        </p>

        <x-mary-password wire:model="password" :label="__('Kata sandi')" right />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <x-mary-button type="button" class="btn-ghost" @click="$wire.showDeleteModal = false">{{ __('Batal') }}</x-mary-button>

            <x-mary-button type="submit" class="btn-error" data-test="confirm-delete-user-button">
                {{ __('Hapus akun') }}
            </x-mary-button>
        </div>
    </form>
</x-mary-modal>
