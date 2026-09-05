<?php

use App\Models\Report;
use App\Models\ReportingPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Laporan')] class extends Component {
    public ?int $selectedPeriodId = null;

    public ?int $selectedMonth = null;

    public function mount(): void
    {
        // Fase 21: default ke periode aktif (masih bisa pilih "Semua periode" / tahun lain).
        $this->selectedPeriodId = ReportingPeriod::active()?->id;
        // Fase 22: bulan wajib dipilih sebelum unduh Laporan Bulanan Lengkap.
        $this->selectedMonth = (int) now()->month;
    }

    #[Computed]
    public function periods()
    {
        return ReportingPeriod::orderBy('start_date')->get(['id', 'label']);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function months(): array
    {
        return Report::monthOptions();
    }
};
?>

<div>
    <x-mary-header title="Laporan" subtitle="Unduh ringkasan pemantauan untuk pimpinan dan unit kerja." separator>
        <x-slot:actions>
            <x-mary-select wire:model.live="selectedPeriodId" :options="$this->periods" option-value="id" option-label="label" placeholder="Semua periode" class="max-w-[280px] select-sm" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-mary-card class="border border-base-300">
            <x-mary-icon name="o-chart-bar" class="mb-3 size-8 text-secondary" />
            <h3 class="text-sm font-semibold text-base-content">Ringkasan Kinerja</h3>
            <p class="mb-4 mt-1 text-sm text-base-content/60">Capaian indikator, realisasi anggaran, dan status verifikasi.</p>
            <div class="flex gap-2">
                <x-mary-button label="Excel" icon="o-table-cells" class="btn-outline btn-sm" link="{{ route('laporan.export.performance-excel', ['period_id' => $selectedPeriodId]) }}" no-wire-navigate />
                <x-mary-button label="PDF" icon="o-document" class="btn-outline btn-sm" link="{{ route('laporan.export.performance-pdf', ['period_id' => $selectedPeriodId]) }}" no-wire-navigate />
            </div>
        </x-mary-card>

        <x-mary-card class="border border-base-300">
            <x-mary-icon name="o-exclamation-triangle" class="mb-3 size-8 text-warning" />
            <h3 class="text-sm font-semibold text-base-content">Register Risiko</h3>
            <p class="mb-4 mt-1 text-sm text-base-content/60">Identifikasi Risiko per laporan beserta status tindak lanjut.</p>
            <x-mary-button label="Excel" icon="o-table-cells" class="btn-outline btn-sm" link="{{ route('laporan.export.risk-excel', ['period_id' => $selectedPeriodId]) }}" no-wire-navigate />
        </x-mary-card>

        <x-mary-card class="border border-base-300">
            <x-mary-icon name="o-building-office-2" class="mb-3 size-8 text-secondary" />
            <h3 class="text-sm font-semibold text-base-content">Realisasi per Unit</h3>
            <p class="mb-4 mt-1 text-sm text-base-content/60">Status pelaporan dan verifikasi setiap unit kerja.</p>
            <x-mary-button label="Excel" icon="o-table-cells" class="btn-outline btn-sm" link="{{ route('laporan.export.unit-excel', ['period_id' => $selectedPeriodId]) }}" no-wire-navigate />
        </x-mary-card>

        <x-mary-card class="border border-base-300">
            <x-mary-icon name="o-document-text" class="mb-3 size-8 text-secondary" />
            <h3 class="text-sm font-semibold text-base-content">Laporan Bulanan Lengkap</h3>
            <p class="mb-4 mt-1 text-sm text-base-content/60">Seluruh data input pelaporan satu bulan — target, pagu, capaian, narasi, dan permasalahan.</p>
            <div class="flex gap-2">
                <x-mary-select wire:model.live="selectedMonth" :options="$this->months" option-value="id" option-label="name" class="select-sm" />
                <x-mary-button
                    label="Excel"
                    icon="o-table-cells"
                    class="btn-outline btn-sm"
                    link="{{ route('laporan.export.monthly-excel', ['period_id' => $selectedPeriodId, 'month' => $selectedMonth]) }}"
                    no-wire-navigate
                    :disabled="! $selectedPeriodId"
                />
            </div>
            @unless ($selectedPeriodId)
                <p class="mt-2 text-xs text-error">Pilih periode tahun terlebih dahulu di atas.</p>
            @endunless
        </x-mary-card>
    </div>
</div>
