<?php

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\IndicatorPeriodSetting;
use App\Models\ReportingPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Manajemen Indikator')] class extends Component {
    use WithPagination, Toast;

    public string $name = '';
    public string $target = '';
    public string $unit = '';
    public ?string $pagu = '';
    public ?int $groupId = null;
    public bool $is_active = true;

    public ?int $editingId = null;
    public bool $showModal = false;

    public string $filterGroup = '';
    public ?int $filterPeriod = null;

    public function mount(): void
    {
        $this->authorize('create', Indicator::class);
        $this->filterPeriod = ReportingPeriod::active()?->id;
    }

    #[Computed]
    public function groups()
    {
        return IndicatorGroup::orderBy('sort_order')->get();
    }

    #[Computed]
    public function periods()
    {
        return ReportingPeriod::orderByDesc('year')->get(['id', 'label']);
    }

    /**
     * Pagu anggaran hanya berlaku kelompok RO (§14.3). Field pagu di modal
     * hanya ditampilkan bila kelompok yang dipilih adalah RO.
     */
    #[Computed]
    public function isROGroup(): bool
    {
        return $this->groupId
            ? IndicatorGroup::find($this->groupId)?->code === 'RO'
            : false;
    }

    #[Computed]
    public function indicators()
    {
        return Indicator::with(['group', 'periodSettings' => function ($q) {
            $q->when($this->filterPeriod, fn ($q2) => $q2->where('reporting_period_id', $this->filterPeriod));
        }])
            ->when($this->filterGroup, fn ($q) => $q->where('indicator_group_id', $this->filterGroup))
            ->orderBy('sort_order')
            ->paginate(15);
    }

    public function create(): void
    {
        $this->authorize('create', Indicator::class);
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $indicator = Indicator::findOrFail($id);
        $this->authorize('update', $indicator);

        $this->name = $indicator->name;
        $this->groupId = $indicator->indicator_group_id;
        $this->is_active = $indicator->is_active;

        // Target/satuan/pagu dibaca dari setting periode terpilih (fallback ke dasar).
        $this->target = (string) ($indicator->settingFor($this->filterPeriod)?->target ?? $indicator->target);
        $this->unit = (string) ($indicator->settingFor($this->filterPeriod)?->unit ?? $indicator->unit);
        $this->pagu = $indicator->paguFor($this->filterPeriod) !== null ? (string) $indicator->paguFor($this->filterPeriod) : '';

        $this->editingId = $indicator->id;
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:500'],
            'target' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:100'],
            'groupId' => ['required', 'exists:indicator_groups,id'],
            'is_active' => ['boolean'],
            'pagu' => ['nullable', 'numeric', 'min:0'],
        ];

        $validated = $this->validate($rules);

        $baseData = [
            'name' => $validated['name'],
            'indicator_group_id' => $validated['groupId'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            $indicator = Indicator::findOrFail($this->editingId);
            $this->authorize('update', $indicator);
            // Kolom target/unit dasar TIDAK ikut ditulis di sini (Fase 21 bugfix): nilai itu
            // adalah fallback resolvedTarget()/resolvedUnit() untuk periode manapun yang belum
            // punya IndicatorPeriodSetting sendiri — menimpanya di setiap simpan membuat edit
            // pada satu periode "bocor" ke periode lain yang belum pernah disentuh.
            $indicator->update($baseData);
            $this->success('Indikator diperbarui.');
        } else {
            $this->authorize('create', Indicator::class);
            $maxSort = Indicator::where('indicator_group_id', $validated['groupId'])->max('sort_order') ?? 0;
            // Target/unit dasar hanya diisi saat indikator baru dibuat — jadi nilai awal/fallback
            // untuk periode yang belum punya setting sendiri.
            $baseData['target'] = $validated['target'];
            $baseData['unit'] = $validated['unit'];
            $baseData['sort_order'] = $maxSort + 1;
            $indicator = Indicator::create($baseData);
            $this->success('Indikator ditambahkan.');
        }

        // Simpan target/satuan/pagu per-periode (Fase 21). Pagu hanya untuk RO.
        if ($this->filterPeriod) {
            IndicatorPeriodSetting::updateOrCreate(
                ['indicator_id' => $indicator->id, 'reporting_period_id' => $this->filterPeriod],
                [
                    'target' => $validated['target'],
                    'unit' => $validated['unit'],
                    'pagu_amount' => $this->isROGroup ? ($validated['pagu'] !== '' ? $validated['pagu'] : null) : null,
                ],
            );
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('$refresh');
    }

    public function toggleActive(int $id): void
    {
        $indicator = Indicator::findOrFail($id);
        $this->authorize('update', $indicator);
        $indicator->update(['is_active' => ! $indicator->is_active]);
        $this->success($indicator->is_active ? 'Indikator diaktifkan.' : 'Indikator dinonaktifkan.');
        $this->dispatch('$refresh');
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->target = '';
        $this->unit = '';
        $this->pagu = '';
        $this->groupId = null;
        $this->is_active = true;
        $this->resetValidation();
    }
};
?>

<div>
    <x-mary-header title="Manajemen Indikator" subtitle="Kelola indikator kinerja serta target, satuan, dan pagu per tahun anggaran." separator>
        <x-slot:actions>
            <x-mary-select wire:model.live="filterPeriod" :options="$this->periods" option-value="id" option-label="label" placeholder="Pilih periode" class="max-w-[220px] select-sm" />
            <x-mary-select wire:model.live="filterGroup" :options="$this->groups" option-value="id" option-label="code" placeholder="Semua kelompok" class="max-w-[200px] select-sm" />
            <x-mary-button icon="o-plus" label="Tambah Indikator" class="btn-primary btn-sm" wire:click="create" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card>
        <x-mary-table :headers="[['key' => 'name', 'label' => 'Indikator'], ['key' => 'kelompok', 'label' => 'Kelompok', 'sortable' => false], ['key' => 'target', 'label' => 'Target', 'sortable' => false], ['key' => 'unit', 'label' => 'Satuan', 'sortable' => false], ['key' => 'pagu', 'label' => 'Pagu', 'sortable' => false], ['key' => 'status', 'label' => 'Status', 'sortable' => false], ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false]]" :rows="$this->indicators" with-pagination class="table-sm">
            @scope('cell_name', $indicator)
                <span class="font-medium">{{ $indicator->name }}</span>
            @endscope
            @scope('cell_kelompok', $indicator)
                <x-mary-badge class="badge-secondary badge-sm">{{ $indicator->group->code }}</x-mary-badge>
            @endscope
            @scope('cell_target', $indicator)
                {{ rtrim(rtrim(number_format((float) $indicator->resolvedTarget($this->filterPeriod), 2, ',', '.'), '0'), ',') }}
            @endscope
            @scope('cell_unit', $indicator)
                {{ $indicator->resolvedUnit($this->filterPeriod) }}
            @endscope
            @scope('cell_pagu', $indicator)
                @php $pagu = $indicator->paguFor($this->filterPeriod); @endphp
                @if ($indicator->group->code === 'RO' && $pagu !== null)
                    <span class="whitespace-nowrap">Rp {{ number_format((float) $pagu, 2, ',', '.') }}</span>
                @else
                    <span class="text-base-content/40">—</span>
                @endif
            @endscope
            @scope('cell_status', $indicator)
                <x-mary-badge class="{{ $indicator->is_active ? 'badge-success' : 'badge-warning' }} badge-sm">
                    {{ $indicator->is_active ? 'Aktif' : 'Nonaktif' }}
                </x-mary-badge>
            @endscope
            @scope('cell_aksi', $indicator)
                <div class="flex gap-1">
                    <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" wire:click="edit({{ $indicator->id }})" />
                    <x-mary-button icon="{{ $indicator->is_active ? 'o-eye-slash' : 'o-eye' }}" class="btn-ghost btn-sm" wire:click="toggleActive({{ $indicator->id }})" />
                </div>
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Belum ada indikator.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showModal" box-class="md:w-[520px]">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-base-content">{{ $editingId ? 'Edit Indikator' : 'Tambah Indikator' }}</h3>
            <p class="mt-1 text-sm text-base-content/60">
                Target, satuan{{ $this->isROGroup ? ', dan pagu' : '' }} disimpan per periode aktif terpilih.
            </p>
        </div>
        <form wire:submit="save" class="space-y-4">
            <x-mary-input wire:model="name" label="Nama Indikator" required />
            <div class="grid grid-cols-2 gap-4">
                <x-mary-select wire:model.live="groupId" :options="$this->groups" option-value="id" option-label="code" placeholder="Pilih kelompok…" label="Kelompok" required />
                <x-mary-input wire:model="target" label="Target" type="number" step="0.01" min="0" required />
            </div>
            <x-mary-input wire:model="unit" label="Satuan" placeholder="Contoh: Spesies, Dokumen, Persen" required />
            @if ($this->isROGroup)
                <x-mary-input wire:model="pagu" label="Pagu RO (Rp)" type="number" step="0.01" min="0" placeholder="0" hint="Khusus kelompok RO dan ditetapkan per tahun anggaran." />
            @endif
            <x-mary-toggle wire:model="is_active" label="Aktif" />
            <div class="flex justify-end gap-2 pt-2">
                <x-mary-button type="button" label="Batal" class="btn-ghost btn-sm" wire:click="$set('showModal', false)" />
                <x-mary-button type="submit" label="Simpan" class="btn-primary btn-sm" />
            </div>
        </form>
    </x-mary-modal>
</div>
