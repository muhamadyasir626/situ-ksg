<?php

use App\Models\Report;
use App\Models\VerificationNote;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Verifikasi Pelaporan')] class extends Component {
    use WithPagination, Toast;

    public ?int $detailReportId = null;

    public bool $showDetailModal = false;

    public ?int $returningReportId = null;

    public string $returnNote = '';

    public bool $showReturnModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', VerificationNote::class);
    }

    #[Computed]
    public function reports()
    {
        return Report::with(['indicator.group', 'unit', 'reportingPeriod'])
            ->where('status', 'Diajukan')
            ->orderBy('submitted_at')
            ->paginate(15);
    }

    #[Computed]
    public function tableHeaders(): array
    {
        return [
            ['key' => 'indikator', 'label' => 'Indikator', 'sortable' => false],
            ['key' => 'unit', 'label' => 'Unit', 'sortable' => false],
            ['key' => 'periode', 'label' => 'Periode', 'sortable' => false],
            ['key' => 'diajukan', 'label' => 'Diajukan', 'sortable' => false],
            ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false],
        ];
    }

    #[Computed]
    public function detailReport(): ?Report
    {
        if (! $this->detailReportId) {
            return null;
        }

        return Report::with(['indicator.group', 'unit', 'reportingPeriod', 'attachments'])
            ->find($this->detailReportId);
    }

    public function showDetail(int $id): void
    {
        $this->detailReportId = $id;
        $this->showDetailModal = true;
    }

    public function approve(int $id): void
    {
        $this->authorize('create', VerificationNote::class);

        $report = Report::where('status', 'Diajukan')->findOrFail($id);

        $report->update([
            'status' => 'Terverifikasi',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
        ]);

        $report->verificationNotes()->create([
            'verifier_id' => Auth::id(),
            'action' => 'Disetujui',
        ]);

        $this->showDetailModal = false;
        $this->success('Laporan disetujui.');
        $this->dispatch('$refresh');
    }

    public function openReturnModal(int $id): void
    {
        $this->authorize('create', VerificationNote::class);

        $this->returningReportId = $id;
        $this->returnNote = '';
        $this->resetValidation();
        $this->showDetailModal = false;
        $this->showReturnModal = true;
    }

    public function returnReport(): void
    {
        $this->authorize('create', VerificationNote::class);

        $this->validate([
            'returnNote' => ['required', 'string', 'min:3'],
        ], [
            'returnNote.required' => 'Catatan pengembalian wajib diisi.',
            'returnNote.min' => 'Catatan pengembalian terlalu singkat.',
        ]);

        $report = Report::where('status', 'Diajukan')->findOrFail($this->returningReportId);

        $report->update(['status' => 'Dikembalikan']);

        $report->verificationNotes()->create([
            'verifier_id' => Auth::id(),
            'action' => 'Dikembalikan',
            'note' => $this->returnNote,
        ]);

        $this->showReturnModal = false;
        $this->success('Laporan dikembalikan ke pelapor.');
        $this->dispatch('$refresh');
    }
};
?>

<div>
    <x-mary-header title="Verifikasi Pelaporan" subtitle="Laporan berstatus Diajukan yang menunggu tinjauan." separator />

    <x-mary-card>
        <x-mary-table :headers="$this->tableHeaders" :rows="$this->reports" with-pagination class="table-sm">
            @scope('cell_indikator', $report)
                <span class="font-medium">{{ $report->indicator->name }}</span>
                <x-mary-badge class="badge-secondary badge-sm ml-1">{{ $report->indicator->group->code }}</x-mary-badge>
            @endscope
            @scope('cell_unit', $report)
                {{ $report->unit->name }}
            @endscope
            @scope('cell_periode', $report)
                {{ $report->reportingPeriod->label }}
            @endscope
            @scope('cell_diajukan', $report)
                {{ $report->submitted_at?->format('d M Y H:i') ?? '—' }}
            @endscope
            @scope('cell_aksi', $report)
                <x-mary-button label="Tinjau" class="btn-outline btn-sm" wire:click="showDetail({{ $report->id }})" />
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Tidak ada laporan yang menunggu verifikasi.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showDetailModal" box-class="md:w-[640px]">
        @if ($this->detailReport)
            @php($report = $this->detailReport)
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-base-content">{{ $report->indicator->name }}</h3>
                <div class="mt-1 flex flex-wrap items-center gap-1 text-sm text-base-content/60">
                    {{ $report->unit->name }} — {{ $report->reportingPeriod->label }}
                    <x-mary-badge class="badge-secondary badge-sm">{{ $report->indicator->group->code }}</x-mary-badge>
                </div>
            </div>

            <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-base-content/60">{{ __('Capaian Target') }}</span>
                        <p class="text-sm text-base-content">{{ $report->target_achievement ?? '—' }}</p>
                    </div>
                    @if ($report->indicator->group->code === 'RO')
                        <div>
                            <span class="text-xs text-base-content/60">{{ __('Realisasi Anggaran') }}</span>
                            <p class="text-sm text-base-content">Rp {{ number_format((float) $report->budget_realization, 2, ',', '.') }}</p>
                        </div>
                    @endif
                </div>

                <div>
                    <span class="text-xs text-base-content/60">{{ __('Narasi Progres') }}</span>
                    <p class="text-sm text-base-content">{{ $report->progress_narrative ?? '—' }}</p>
                </div>

                <div>
                    <span class="text-xs text-base-content/60">{{ __('Permasalahan') }}</span>
                    <p class="text-sm text-base-content">{{ $report->problem_narrative ?: '—' }}</p>
                </div>

                <x-mary-card class="border border-base-300 bg-base-200">
                    <h4 class="mb-3 text-sm font-semibold text-base-content">Identifikasi Risiko</h4>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <span class="text-xs text-base-content/60">{{ __('Eksternal / Kemitraan') }}</span>
                            <p class="text-sm text-base-content">{{ $report->risk_external_narrative ?: '—' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-base-content/60">{{ __('Strategis') }}</span>
                            <p class="text-sm text-base-content">{{ $report->risk_strategic_narrative ?: '—' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-base-content/60">{{ __('Kecurangan') }}</span>
                            <p class="text-sm text-base-content">{{ $report->risk_fraud_narrative ?: '—' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-base-content/60">{{ __('Kebijakan Internal/Eksternal') }}</span>
                            <p class="text-sm text-base-content">{{ $report->risk_policy_narrative ?: '—' }}</p>
                        </div>
                    </div>
                </x-mary-card>

                <div>
                    <span class="text-xs text-base-content/60">{{ __('Bukti Dukung') }}</span>
                    @forelse ($report->attachments as $attachment)
                        <p class="text-sm text-base-content">{{ $attachment->original_name }} ({{ number_format($attachment->size / 1024, 0) }} KB)</p>
                    @empty
                        <p class="text-sm text-base-content/60">Tidak ada berkas terlampir.</p>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <x-mary-button label="Tutup" class="btn-ghost btn-sm" wire:click="$set('showDetailModal', false)" />
                <x-mary-button label="Kembalikan" class="btn-error btn-sm" wire:click="openReturnModal({{ $report->id }})" />
                <x-mary-button label="Setujui" class="btn-primary btn-sm" wire:click="approve({{ $report->id }})" wire:confirm="Setujui laporan ini?" />
            </div>
        @endif
    </x-mary-modal>

    <x-mary-modal wire:model="showReturnModal" box-class="md:w-[480px]">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-base-content">{{ __('Kembalikan Laporan') }}</h3>
            <p class="mt-1 text-sm text-base-content/60">Catatan wajib diisi agar Pelapor tahu apa yang perlu diperbaiki.</p>
        </div>

        <x-mary-textarea wire:model="returnNote" label="{{ __('Catatan Pengembalian') }}" rows="4" placeholder="Jelaskan apa yang perlu diperbaiki…" />

        <div class="flex justify-end gap-2 pt-4">
            <x-mary-button label="Batal" class="btn-ghost btn-sm" wire:click="$set('showReturnModal', false)" />
            <x-mary-button label="Kembalikan Laporan" class="btn-error btn-sm" wire:click="returnReport" />
        </div>
    </x-mary-modal>
</div>
