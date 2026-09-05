<?php

use App\Models\ReportingPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Manajemen Periode')] class extends Component {
    use WithPagination, Toast;

    public string $year = '';
    public string $label = '';
    public string $start_date = '';
    public string $end_date = '';
    public bool $is_active = true;

    public ?int $editingId = null;
    public bool $showModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ReportingPeriod::class);
    }

    #[Computed]
    public function periods()
    {
        return ReportingPeriod::latest('year')->paginate(15);
    }

    public function create(): void
    {
        $this->authorize('create', ReportingPeriod::class);
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $period = ReportingPeriod::findOrFail($id);
        $this->authorize('update', $period);

        $this->year = $period->year;
        $this->label = $period->label;
        $this->start_date = $period->start_date->format('Y-m-d');
        $this->end_date = $period->end_date->format('Y-m-d');
        $this->is_active = $period->is_active;
        $this->editingId = $period->id;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'label' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $period = ReportingPeriod::findOrFail($this->editingId);
            $this->authorize('update', $period);
            $period->update($validated);
            $this->success('Periode diperbarui.');
        } else {
            $this->authorize('create', ReportingPeriod::class);
            ReportingPeriod::create($validated);
            $this->success('Periode ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('$refresh');
    }

    public function delete(int $id): void
    {
        $period = ReportingPeriod::findOrFail($id);
        $this->authorize('delete', $period);
        $period->delete();
        $this->success('Periode dihapus.');
        $this->dispatch('$refresh');
    }

    private function resetForm(): void
    {
        $this->year = '';
        $this->label = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->is_active = true;
        $this->resetValidation();
    }
};
?>

<div>
    <x-mary-header title="Manajemen Periode Pelaporan" subtitle="Kelola tahun anggaran dan periode pelaporan." separator>
        <x-slot:actions>
            <x-mary-button icon="o-plus" label="Tambah Periode" class="btn-primary btn-sm" wire:click="create" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card>
        <x-mary-table :headers="[['key' => 'year', 'label' => 'Tahun'], ['key' => 'label', 'label' => 'Label'], ['key' => 'mulai', 'label' => 'Mulai', 'sortable' => false], ['key' => 'selesai', 'label' => 'Selesai', 'sortable' => false], ['key' => 'status', 'label' => 'Status', 'sortable' => false], ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false]]" :rows="$this->periods" with-pagination class="table-sm">
            @scope('cell_mulai', $period)
                {{ $period->start_date->format('d M Y') }}
            @endscope
            @scope('cell_selesai', $period)
                {{ $period->end_date->format('d M Y') }}
            @endscope
            @scope('cell_status', $period)
                <x-mary-badge class="{{ $period->is_active ? 'badge-success' : 'badge-warning' }} badge-sm">
                    {{ $period->is_active ? 'Aktif' : 'Nonaktif' }}
                </x-mary-badge>
            @endscope
            @scope('cell_aksi', $period)
                <div class="flex gap-1">
                    <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" wire:click="edit({{ $period->id }})" />
                    <x-mary-button icon="o-trash" class="btn-ghost btn-sm" wire:click="delete({{ $period->id }})" wire:confirm="Hapus periode ini?" />
                </div>
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Belum ada periode pelaporan.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showModal" box-class="md:w-[480px]">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-base-content">{{ $editingId ? 'Edit Periode' : 'Tambah Periode' }}</h3>
            <p class="mt-1 text-sm text-base-content/60">Isi data periode pelaporan.</p>
        </div>
        <form wire:submit="save" class="space-y-4">
            <x-mary-input wire:model="year" label="Tahun" type="number" required />
            <x-mary-input wire:model="label" label="Label" placeholder="Contoh: Juli 2026" required />
            <div class="grid grid-cols-2 gap-4">
                <x-mary-input wire:model="start_date" label="Tanggal Mulai" type="date" required />
                <x-mary-input wire:model="end_date" label="Tanggal Selesai" type="date" required />
            </div>
            <x-mary-toggle wire:model="is_active" label="Aktif" />
            <div class="flex justify-end gap-2 pt-2">
                <x-mary-button type="button" label="Batal" class="btn-ghost btn-sm" wire:click="$set('showModal', false)" />
                <x-mary-button type="submit" label="Simpan" class="btn-primary btn-sm" />
            </div>
        </form>
    </x-mary-modal>
</div>
