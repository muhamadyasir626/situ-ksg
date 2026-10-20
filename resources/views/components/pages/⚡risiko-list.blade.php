<?php

use App\Models\ReportingPeriod;
use App\Models\Risk;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Risiko & Kendala')] class extends Component {
    use WithPagination, Toast;

    public string $filterCategory = '';

    public string $filterStatus = '';

    public ?int $filterUnitId = null;

    public ?int $filterPeriodId = null;

    protected ?int $scopeUnitId = null;

    public ?int $editingRiskId = null;

    public string $editStatus = '';

    public ?string $editPic = null;

    public ?string $editDueDate = null;

    public bool $showEditModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Risk::class);

        if (Auth::user()->hasRole('Pelapor Unit')) {
            $this->scopeUnitId = Auth::user()->unit_id;
        }
    }

    #[Computed]
    public function risks()
    {
        return Risk::query()
            ->with(['report.indicator.group', 'report.unit', 'report.reportingPeriod'])
            ->when($this->scopeUnitId, fn ($q) => $q->whereHas('report', fn ($r) => $r->where('unit_id', $this->scopeUnitId)))
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterUnitId, fn ($q) => $q->whereHas('report', fn ($r) => $r->where('unit_id', $this->filterUnitId)))
            ->when($this->filterPeriodId, fn ($q) => $q->whereHas('report', fn ($r) => $r->where('reporting_period_id', $this->filterPeriodId)))
            ->latest('updated_at')
            ->paginate(15);
    }

    #[Computed]
    public function tableHeaders(): array
    {
        return [
            ['key' => 'indikator', 'label' => 'Indikator', 'sortable' => false],
            ['key' => 'kategori', 'label' => 'Kategori', 'sortable' => false],
            ['key' => 'narasi', 'label' => 'Narasi', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'sortable' => false],
            ['key' => 'pic', 'label' => 'PIC', 'sortable' => false],
            ['key' => 'tenggat', 'label' => 'Tenggat', 'sortable' => false],
            ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return collect(Risk::categories())->map(fn (string $c) => ['id' => $c, 'name' => $c])->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return collect(Risk::statuses())->map(fn (string $s) => ['id' => $s, 'name' => $s])->all();
    }

    #[Computed]
    public function unitOptions()
    {
        return Unit::orderBy('name')->get();
    }

    #[Computed]
    public function periodOptions()
    {
        return ReportingPeriod::orderByDesc('start_date')->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Verifikator') || Auth::user()->hasRole('Pimpinan');
    }

    public function openEdit(int $id): void
    {
        $risk = Risk::findOrFail($id);
        $this->authorize('update', $risk);

        $this->editingRiskId = $risk->id;
        $this->editStatus = $risk->status;
        $this->editPic = $risk->pic;
        $this->editDueDate = $risk->due_date?->format('Y-m-d');
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        $risk = Risk::findOrFail($this->editingRiskId);
        $this->authorize('update', $risk);

        $this->validate([
            'editStatus' => ['required', Rule::in(Risk::statuses())],
            'editPic' => ['nullable', 'string', 'max:255'],
            'editDueDate' => ['nullable', 'date'],
        ]);

        $risk->update([
            'status' => $this->editStatus,
            'pic' => $this->editPic ?: null,
            'due_date' => $this->editDueDate ?: null,
            'resolved_at' => $this->editStatus === Risk::STATUS_SELESAI ? ($risk->resolved_at ?? now()) : null,
            'updated_by' => Auth::id(),
        ]);

        $this->showEditModal = false;
        $this->success('Tindak lanjut risiko diperbarui.');
        $this->dispatch('$refresh');
    }
};
?>

<div>
    <x-mary-header title="Risiko & Kendala" subtitle="Pantau risiko, penanggung jawab, tenggat, dan status tindak lanjut." separator />

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-mary-select wire:model.live="filterCategory" :options="$this->categoryOptions" option-value="id" option-label="name" placeholder="Semua kategori" label="Kategori" class="select-sm" />
        <x-mary-select wire:model.live="filterStatus" :options="$this->statusOptions" option-value="id" option-label="name" placeholder="Semua status" label="Status" class="select-sm" />
        <x-mary-select wire:model.live="filterUnitId" :options="$this->unitOptions" option-value="id" option-label="name" placeholder="Semua unit" label="Unit Kerja" class="select-sm" />
        <x-mary-select wire:model.live="filterPeriodId" :options="$this->periodOptions" option-value="id" option-label="label" placeholder="Semua periode" label="Periode" class="select-sm" />
    </div>

    <x-mary-card>
        <x-mary-table :headers="$this->tableHeaders" :rows="$this->risks" with-pagination class="table-sm">
            @scope('cell_indikator', $risk)
                <span class="font-medium">{{ $risk->report->indicator->name }}</span>
                <div class="text-xs text-base-content/60">{{ $risk->report->unit->name }} — {{ $risk->report->reportingPeriod->label }}</div>
            @endscope
            @scope('cell_kategori', $risk)
                <x-mary-badge class="badge-secondary badge-sm">{{ $risk->category }}</x-mary-badge>
            @endscope
            @scope('cell_narasi', $risk)
                <p class="line-clamp-2 max-w-xs text-sm">{{ $risk->narrative }}</p>
            @endscope
            @scope('cell_status', $risk)
                @php
                    $statusClass = match ($risk->status) {
                        'Selesai' => 'badge-success',
                        'Ditindaklanjuti' => 'badge-info',
                        default => 'badge-warning',
                    };
                @endphp
                <x-mary-badge class="{{ $statusClass }} badge-sm">{{ $risk->status }}</x-mary-badge>
            @endscope
            @scope('cell_pic', $risk)
                {{ $risk->pic ?: '—' }}
            @endscope
            @scope('cell_tenggat', $risk)
                {{ $risk->due_date?->format('d M Y') ?? '—' }}
            @endscope
            @scope('cell_aksi', $risk)
                @if ($this->canManage)
                    <x-mary-button label="Kelola" class="btn-outline btn-sm" wire:click="openEdit({{ $risk->id }})" />
                @endif
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Belum ada risiko yang tercatat.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showEditModal" title="Kelola Tindak Lanjut Risiko" box-class="md:w-[480px]">
        <div class="space-y-4">
            <x-mary-select wire:model="editStatus" :options="$this->statusOptions" option-value="id" option-label="name" label="Status" />
            <x-mary-input wire:model="editPic" label="Penanggung Jawab (PIC)" placeholder="Nama penanggung jawab tindak lanjut" />
            <x-mary-input wire:model="editDueDate" type="date" label="Tenggat" />
        </div>

        <div class="flex justify-end gap-2 pt-4">
            <x-mary-button label="Batal" class="btn-ghost btn-sm" wire:click="$set('showEditModal', false)" />
            <x-mary-button label="Simpan" class="btn-primary btn-sm" wire:click="saveEdit" />
        </div>
    </x-mary-modal>
</div>
