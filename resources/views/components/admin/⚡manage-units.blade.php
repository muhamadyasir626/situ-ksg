<?php

use App\Models\Unit;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Manajemen Unit Kerja')] class extends Component {
    use WithPagination, Toast;

    public string $name = '';
    public string $code = '';
    public bool $is_active = true;

    public ?int $editingId = null;

    public bool $showModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Unit::class);
    }

    #[Computed]
    public function units()
    {
        return Unit::latest()->paginate(15);
    }

    public function create(): void
    {
        $this->authorize('create', Unit::class);

        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->authorize('update', $unit);

        $this->name = $unit->name;
        $this->code = $unit->code;
        $this->is_active = $unit->is_active;
        $this->editingId = $unit->id;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:units,code,' . ($this->editingId ?? 'NULL') . ',id'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $unit = Unit::findOrFail($this->editingId);
            $this->authorize('update', $unit);
            $unit->update($validated);
            $this->success('Unit kerja diperbarui.');
        } else {
            $this->authorize('create', Unit::class);
            Unit::create($validated);
            $this->success('Unit kerja ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('$refresh');
    }

    public function toggleActive(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->authorize('toggleActive', $unit);

        $unit->update(['is_active' => ! $unit->is_active]);

        $this->success($unit->is_active ? 'Unit diaktifkan.' : 'Unit dinonaktifkan.');
        $this->dispatch('$refresh');
    }

    public function delete(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->authorize('delete', $unit);

        $unit->delete();

        $this->success('Unit kerja dihapus.');
        $this->dispatch('$refresh');
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->code = '';
        $this->is_active = true;
        $this->resetValidation();
    }
};
?>

<div>
    <x-mary-header title="Manajemen Unit Kerja" subtitle="Kelola unit kerja pusat yang menggunakan SITU-KSG." separator>
        <x-slot:actions>
            <x-mary-button icon="o-plus" label="Tambah Unit" class="btn-primary btn-sm" wire:click="create" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card>
        <x-mary-table :headers="[['key' => 'name', 'label' => 'Nama'], ['key' => 'code', 'label' => 'Kode'], ['key' => 'status', 'label' => 'Status', 'sortable' => false], ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false]]" :rows="$this->units" with-pagination class="table-sm">
            @scope('cell_status', $unit)
                <x-mary-badge class="{{ $unit->is_active ? 'badge-success' : 'badge-warning' }} badge-sm">
                    {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                </x-mary-badge>
            @endscope
            @scope('cell_aksi', $unit)
                <div class="flex gap-1">
                    <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" wire:click="edit({{ $unit->id }})" />
                    <x-mary-button icon="o-trash" class="btn-ghost btn-sm" wire:click="delete({{ $unit->id }})" wire:confirm="Hapus unit {{ $unit->name }}?" />
                </div>
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Belum ada unit kerja.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showModal" box-class="md:w-[480px]">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-base-content">{{ $editingId ? 'Edit Unit' : 'Tambah Unit' }}</h3>
            <p class="mt-1 text-sm text-base-content/60">Isi data unit kerja di bawah ini.</p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <x-mary-input wire:model="name" label="Nama Unit Kerja" placeholder="Contoh: Direktorat KSG" required />
            <x-mary-input wire:model="code" label="Kode Unit Kerja" placeholder="Contoh: DKSG" required />
            <x-mary-toggle wire:model="is_active" label="Aktif" />

            <div class="flex justify-end gap-2 pt-2">
                <x-mary-button type="button" label="Batal" class="btn-ghost btn-sm" wire:click="$set('showModal', false)" />
                <x-mary-button type="submit" label="Simpan" class="btn-primary btn-sm" />
            </div>
        </form>
    </x-mary-modal>
</div>
