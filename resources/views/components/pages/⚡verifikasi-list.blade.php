<?php

use App\Models\Report;
use App\Models\VerificationNote;
use App\Services\ReportEmails;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Verifikasi Laporan')] class extends Component {
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

        return Report::with(['indicator.group', 'indicator.periodSettings', 'unit', 'reportingPeriod', 'attachments', 'verificationNotes.verifier'])
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

        DB::transaction(function () use ($id): void {
            $this->approveReport($id);
        });
    }

    protected function approveReport(int $id): void
    {

        $report = $this->pendingReport($id);
        if (! $report) {
            return;
        }

        $report->update([
            'status' => 'Terverifikasi',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
        ]);

        $report->verificationNotes()->create([
            'verifier_id' => Auth::id(),
            'action' => 'Disetujui',
        ]);

        app(ReportEmails::class)->statusChanged($report);

        $this->showDetailModal = false;
        $this->success('Laporan disetujui.');
        $this->dispatch('$refresh');
    }

    /**
     * Laporan yang masih menunggu verifikasi. Bila sudah diproses (mis. oleh verifikator
     * lain) tampilkan pesan, bukan halaman 404.
     */
    protected function pendingReport(?int $id): ?Report
    {
        $report = Report::where('status', 'Diajukan')->lockForUpdate()->find($id);

        if (! $report) {
            $this->showDetailModal = false;
            $this->error('Laporan tidak lagi berstatus Diajukan; mungkin sudah diproses.');
            unset($this->reports);
        }

        return $report;
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

        DB::transaction(function (): void {
            $this->persistReturn();
        });
    }

    protected function persistReturn(): void
    {
        $report = $this->pendingReport($this->returningReportId);
        if (! $report) {
            $this->showReturnModal = false;

            return;
        }

        $report->update(['status' => 'Dikembalikan']);

        $report->verificationNotes()->create([
            'verifier_id' => Auth::id(),
            'action' => 'Dikembalikan',
            'note' => $this->returnNote,
        ]);

        app(ReportEmails::class)->statusChanged($report, $this->returnNote);

        $this->showReturnModal = false;
        $this->success('Laporan dikembalikan ke pelapor.');
        $this->dispatch('$refresh');
    }
};
?>

<div>
    <x-mary-header title="Verifikasi Laporan" subtitle="Tinjau laporan berstatus Diajukan sebelum disetujui atau dikembalikan." separator />

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
            @php
                $report = $this->detailReport;
                $code = $report->indicator->group->code;
                $pagu = $report->indicator->paguFor($report->reporting_period_id);
                // Isian per kelompok mengikuti PRD §2.3 (sama dengan form Input Pelaporan).
                $fields = array_filter([
                    'Bulan' => \App\Models\Report::monthLabel($report->month),
                    'Target' => rtrim(rtrim(number_format((float) $report->indicator->resolvedTarget($report->reporting_period_id), 2, ',', '.'), '0'), ',').' '.$report->indicator->resolvedUnit($report->reporting_period_id),
                    'Capaian Target' => $report->target_achievement,
                    'Realisasi Capaian' => $code === 'IKK' ? $report->realization_achievement : null,
                    'Realisasi Anggaran' => $code === 'RO' ? 'Rp '.number_format((float) $report->budget_realization, 2, ',', '.') : null,
                    'Pagu RO' => $code === 'RO' && $pagu !== null ? 'Rp '.number_format((float) $pagu, 2, ',', '.') : null,
                    'Keterangan Proses' => in_array($code, ['IKU', 'IKP'], true) ? $report->process_note : null,
                    'Upaya/Strategi Pencapaian' => $code === 'IKU' ? $report->achievement_strategy : null,
                    ($code === 'IKK' ? 'Narasi Proses' : 'Narasi Progres') => in_array($code, ['IKK', 'RO'], true) ? $report->progress_narrative : null,
                    'Permasalahan/Kendala' => $report->problem_narrative,
                    'Tindak Lanjut' => $code === 'IKP' ? $report->follow_up : null,
                ], fn ($value) => $value !== null && $value !== '');
            @endphp
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-base-content">{{ $report->indicator->name }}</h3>
                <div class="mt-1 flex flex-wrap items-center gap-1 text-sm text-base-content/60">
                    {{ $report->unit->name }} — {{ $report->reportingPeriod->label }}
                    <x-mary-badge class="badge-secondary badge-sm">{{ $report->indicator->group->code }}</x-mary-badge>
                </div>
            </div>

            <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($fields as $label => $value)
                        <div>
                            <span class="text-xs text-base-content/60">{{ $label }}</span>
                            <p class="whitespace-pre-line text-sm text-base-content">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                <div>
                    <span class="text-xs text-base-content/60">{{ __('Berkas Bukti Dukung') }}</span>
                    @forelse ($report->attachments as $attachment)
                        <p class="text-sm text-base-content">
                            <a href="{{ route('realisasi.attachments.preview', $attachment) }}" target="_blank" class="link">{{ $attachment->original_name }}</a>
                            ({{ number_format($attachment->size / 1024, 0) }} KB)
                            · <a href="{{ route('realisasi.attachments.download', $attachment) }}" class="link">Unduh</a>
                        </p>
                    @empty
                        <p class="text-sm text-base-content/60">Tidak ada berkas terlampir.</p>
                    @endforelse
                </div>

                @if ($report->verificationNotes->isNotEmpty())
                    <div>
                        <span class="text-xs text-base-content/60">{{ __('Riwayat Verifikasi') }}</span>
                        @foreach ($report->verificationNotes as $note)
                            <p class="text-sm text-base-content">
                                {{ $note->created_at?->format('d-m-Y H:i') }} — {{ $note->action }} oleh {{ $note->verifier?->name ?? '—' }}@if ($note->note): {{ $note->note }}@endif
                            </p>
                        @endforeach
                    </div>
                @endif
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
