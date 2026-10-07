<?php

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportingPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Input Pelaporan')] class extends Component
{
    use Toast, WithFileUploads;

    public ?int $selectedGroupId = null;

    public ?int $selectedIndicatorId = null;

    public ?int $reportingPeriodId = null;

    public ?int $selectedMonth = null;

    public string $targetAchievement = '';

    public ?string $budgetRealization = null;

    public ?string $achievementStrategy = null;

    public ?string $progressNarrative = null;

    public ?string $problemNarrative = null;

    public ?string $riskExternal = null;

    public ?string $riskStrategic = null;

    public ?string $riskFraud = null;

    public ?string $riskPolicy = null;

    public ?string $processNote = null;

    public ?string $followUp = null;

    public ?string $realizationAchievement = null;

    public $attachments = [];

    public ?int $editingReportId = null;

    public ?string $editingGroupCode = null;

    public function mount(?int $report = null): void
    {
        $this->authorize('create', Report::class);

        $this->reportingPeriodId = ReportingPeriod::active()?->id;
        $this->selectedMonth = (int) now()->month;

        if ($report) {
            $r = Report::findOrFail($report);
            $this->authorize('update', $r);

            $this->editingReportId = $r->id;
            $this->selectedIndicatorId = $r->indicator_id;
            $this->selectedGroupId = $r->indicator->indicator_group_id;
            $this->editingGroupCode = $r->indicator->group->code;
            $this->reportingPeriodId = $r->reporting_period_id;
            $this->selectedMonth = $r->month ?? (int) now()->month;
            $this->targetAchievement = $r->target_achievement ?? '';
            $this->budgetRealization = $r->budget_realization !== null ? (string) $r->budget_realization : null;
            $this->achievementStrategy = $r->achievement_strategy !== null ? (string) $r->achievement_strategy : null;
            $this->progressNarrative = $r->progress_narrative;
            $this->problemNarrative = $r->problem_narrative;
            $this->riskExternal = $r->risk_external_narrative;
            $this->riskStrategic = $r->risk_strategic_narrative;
            $this->riskFraud = $r->risk_fraud_narrative;
            $this->riskPolicy = $r->risk_policy_narrative;
            $this->processNote = $r->process_note;
            $this->followUp = $r->follow_up;
            $this->realizationAchievement = $r->realization_achievement;
        }
    }

    #[Computed]
    public function groups()
    {
        return IndicatorGroup::orderBy('sort_order')->get();
    }

    #[Computed]
    public function groupOptions()
    {
        return $this->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->code.' — '.$g->name]);
    }

    #[Computed]
    public function availableIndicators()
    {
        if (! $this->selectedGroupId) {
            return collect();
        }

        $query = Indicator::where('indicator_group_id', $this->selectedGroupId)
            ->where('is_active', true)
            ->orderBy('sort_order');

        if (! Auth::user()->hasRole('Admin') && Auth::user()->unit_id) {
            $query->whereHas('units', fn ($q) => $q->where('units.id', Auth::user()->unit_id));
        }

        return $query->get();
    }

    #[Computed]
    public function selectedIndicator()
    {
        return $this->selectedIndicatorId ? Indicator::with('group')->find($this->selectedIndicatorId) : null;
    }

    #[Computed]
    public function isRO(): bool
    {
        return $this->selectedIndicator?->group?->code === 'RO';
    }

    #[Computed]
    public function isIKU(): bool
    {
        return $this->selectedIndicator?->group?->code === 'IKU';
    }

    #[Computed]
    public function isIKP(): bool
    {
        return $this->selectedIndicator?->group?->code === 'IKP';
    }

    #[Computed]
    public function isIKK(): bool
    {
        return $this->selectedIndicator?->group?->code === 'IKK';
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function monthOptions(): array
    {
        return Report::monthOptions();
    }

    /**
     * Tahun pelaporan selalu mengikuti tahun periode aktif (Fase 22) — tidak ada
     * pemilih tahun terpisah di form, hanya label.
     */
    #[Computed]
    public function activeYear(): ?int
    {
        return ReportingPeriod::active()?->year;
    }

    /**
     * Triwulan kini diturunkan dari bulan yang dipilih pengguna (Fase 22), bukan
     * dari tanggal submit (Fase 21) — laporan sekarang per bulan, bukan per triwulan.
     */
    #[Computed]
    public function selectedQuarter(): int
    {
        return (int) ceil(($this->selectedMonth ?: (int) now()->month) / 3);
    }

    /**
     * Pagu anggaran RO ditetapkan per-periode di Manajemen Indikator (Fase 21),
     * bukan diinput di sini. Form hanya menampilkannya sebagai label.
     */
    #[Computed]
    public function roPaguLabel(): ?string
    {
        if (! $this->isRO) {
            return null;
        }

        $pagu = $this->selectedIndicator?->paguFor($this->reportingPeriodId);

        return $pagu !== null
            ? 'Rp '.number_format((float) $pagu, 2, ',', '.')
            : null;
    }

    public function updatedSelectedGroupId(): void
    {
        $this->selectedIndicatorId = null;
    }

    public function saveDraft(): void
    {
        $this->validate($this->draftRules());

        $this->saveReport('Draft');
        $this->success('Draft disimpan.');
        $this->redirect(route('realisasi'), navigate: true);
    }

    public function submit(): void
    {
        $rules = $this->submitRules();
        $this->validate($rules);

        if ($this->isIKU) {
            $this->validate([
                'riskExternal' => ['required', 'string'],
                'riskStrategic' => ['required', 'string'],
                'riskFraud' => ['required', 'string'],
                'riskPolicy' => ['required', 'string'],
            ], [
                'required' => 'Identifikasi Risiko wajib diisi untuk kelompok IKU.',
            ]);
        }

        $this->saveReport('Diajukan', true);
        $this->success('Laporan diajukan untuk verifikasi.');
        $this->redirect(route('realisasi'), navigate: true);
    }

    protected function draftRules(): array
    {
        return [
            'selectedIndicatorId' => [
                'required',
                'exists:indicators,id',
                $this->authorizedIndicatorRule(),
                Rule::unique('reports', 'indicator_id')
                    ->where(fn ($query) => $query
                        ->where('unit_id', Auth::user()->unit_id)
                        ->where('reporting_period_id', $this->reportingPeriodId)
                        ->where('month', $this->selectedMonth))
                    ->ignore($this->editingReportId),
            ],
            'reportingPeriodId' => ['required', 'exists:reporting_periods,id'],
            'selectedMonth' => ['required', 'integer', 'min:1', 'max:12'],
            'progressNarrative' => ['nullable', 'string', 'max:5000'],
            'achievementStrategy' => ['nullable', 'string', 'max:5000'],
            'problemNarrative' => ['nullable', 'string', 'max:5000'],
            'riskExternal' => ['nullable', 'string', 'max:5000'],
            'riskStrategic' => ['nullable', 'string', 'max:5000'],
            'riskFraud' => ['nullable', 'string', 'max:5000'],
            'riskPolicy' => ['nullable', 'string', 'max:5000'],
            'processNote' => ['nullable', 'string', 'max:5000'],
            'followUp' => ['nullable', 'string', 'max:5000'],
            'realizationAchievement' => ['nullable', 'string', 'max:5000'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ];
    }

    protected function submitRules(): array
    {
        $rules = $this->draftRules();
        $rules['targetAchievement'] = ['required', 'string'];
        if ($this->isRO) {
            $rules['budgetRealization'] = ['required', 'numeric', 'min:0'];
            $rules['progressNarrative'] = ['required', 'string', 'max:5000'];
            $rules['problemNarrative'] = ['required', 'string', 'max:5000'];
        }
        if ($this->isIKU) {
            $rules['processNote'] = ['required', 'string'];
            $rules['achievementStrategy'] = ['required', 'string', 'max:5000'];
            $rules['problemNarrative'] = ['required', 'string', 'max:5000'];
        }
        if ($this->isIKP) {
            $rules['processNote'] = ['required', 'string'];
            $rules['followUp'] = ['required', 'string'];
            $rules['problemNarrative'] = ['required', 'string', 'max:5000'];
        }
        if ($this->isIKK) {
            $rules['realizationAchievement'] = ['required', 'string'];
            $rules['progressNarrative'] = ['required', 'string', 'max:5000'];
            $rules['problemNarrative'] = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * Menolak indikator yang tidak ditugaskan ke unit pengguna (§3.2). Ditegakkan sebagai
     * rule validasi (bukan addError manual) agar tidak tertimpa reset error bag Livewire
     * saat validate() dipanggil ulang.
     */
    protected function authorizedIndicatorRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $indicator = Indicator::find($value);

            if ($indicator && Auth::user()->cannot('createForIndicator', [Report::class, $indicator])) {
                $fail('Indikator ini tidak ditugaskan ke unit Anda.');
            }
        };
    }

    protected function messages(): array
    {
        return [
            'selectedIndicatorId.unique' => 'Sudah ada laporan untuk indikator dan periode ini pada unit Anda.',
            'attachments.*.mimes' => 'Format berkas harus PDF, DOC, DOCX, XLS, XLSX, JPG, atau PNG.',
            'attachments.*.max' => 'Ukuran berkas maksimal 5 MB.',
        ];
    }

    protected function saveReport(string $status, bool $submit = false): void
    {
        $data = [
            'indicator_id' => $this->selectedIndicatorId,
            'unit_id' => Auth::user()->unit_id,
            'reporting_period_id' => $this->reportingPeriodId,
            'month' => $this->selectedMonth,
            'quarter' => $this->selectedQuarter,
            'target_achievement' => $this->targetAchievement ?: null,
            'realization_achievement' => $this->isIKK && $this->realizationAchievement !== null && $this->realizationAchievement !== '' ? $this->realizationAchievement : null,
            'budget_realization' => $this->isRO && $this->budgetRealization !== null && $this->budgetRealization !== '' ? $this->budgetRealization : null,
            'progress_narrative' => ($this->isRO || $this->isIKK) && $this->progressNarrative !== null && $this->progressNarrative !== '' ? $this->progressNarrative : null,
            'achievement_strategy' => $this->isIKU && $this->achievementStrategy !== null && $this->achievementStrategy !== '' ? $this->achievementStrategy : null,
            'problem_narrative' => ($this->isRO || $this->isIKU || $this->isIKP || $this->isIKK) && $this->problemNarrative !== null && $this->problemNarrative !== '' ? $this->problemNarrative : null,
            'risk_external_narrative' => null,
            'risk_strategic_narrative' => null,
            'risk_fraud_narrative' => null,
            'risk_policy_narrative' => null,
            'process_note' => ($this->isIKU || $this->isIKP) && $this->processNote !== null && $this->processNote !== '' ? $this->processNote : null,
            'follow_up' => $this->isIKP && $this->followUp !== null && $this->followUp !== '' ? $this->followUp : null,
            'status' => $status,
            'updated_by' => Auth::id(),
        ];

        if ($submit) {
            $data['submitted_at'] = now();
        }

        if ($this->editingReportId) {
            $report = Report::findOrFail($this->editingReportId);

            if ($report->status === 'Dikembalikan') {
                $report->revisions()->create([
                    'payload' => $report->only(array_keys($data)),
                    'revised_by' => Auth::id(),
                ]);
            }

            $report->update($data);
        } else {
            $data['created_by'] = Auth::id();
            $report = Report::create($data);
            $this->editingReportId = $report->id;
        }

        $report->syncRisks();
        $this->handleAttachments($report);
    }

    protected function handleAttachments(Report $report): void
    {
        if (! $this->attachments) {
            return;
        }

        $files = is_array($this->attachments) ? $this->attachments : [$this->attachments];

        foreach ($files as $file) {
            $path = $file->store('attachments', 'local');
            $report->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }
};
?>

<div>
    <x-mary-header :title="$editingReportId ? 'Edit Pelaporan' : 'Input Pelaporan Capaian'" subtitle="Uraian sesuai kelompok indikator: IKU (Keterangan Proses, Capaian, Upaya/Strategi, Permasalahan), IKP (Keterangan Proses, Capaian, Permasalahan, Tindak Lanjut), IKK (Realisasi Capaian, Narasi Proses, Permasalahan), RO (Realisasi Anggaran, Narasi Progres, Kendala, Capaian Target)." separator />

    <x-mary-card>
    <form class="space-y-5" wire:submit.prevent>
        {{-- Two-stage indicator selection (§13.2) --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-mary-select
                wire:model.live="selectedGroupId"
                label="Kelompok Indikator"
                placeholder="Pilih kelompok…"
                :options="$this->groupOptions"
                option-value="id"
                option-label="name"
                :disabled="(bool) $editingReportId"
            />

            <x-mary-select
                wire:model.live="selectedIndicatorId"
                label="Indikator"
                placeholder="Pilih indikator…"
                :options="$this->availableIndicators"
                option-value="id"
                option-label="name"
                :disabled="(bool) ($editingReportId || ! $this->selectedGroupId)"
            />
        </div>

        {{-- Laporan per bulan (Fase 22): tahun mengikuti periode aktif, tidak dipilih manual --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-mary-select
                wire:model.live="selectedMonth"
                label="Bulan Pelaporan"
                :options="$this->monthOptions"
                option-value="id"
                option-label="name"
            />

            <x-mary-input label="Tahun" :value="(string) ($this->activeYear ?? '—')" readonly hint="Mengikuti periode anggaran aktif." />
        </div>

        @if ($this->selectedIndicator)
            <x-mary-alert icon="o-information-circle" class="alert-info">
                Target: <strong>{{ rtrim(rtrim(number_format($this->selectedIndicator->target, 2, ',', '.'), '0'), ',') }} {{ $this->selectedIndicator->unit }}</strong>
            </x-mary-alert>

            {{-- RO: Capaian Target, Realisasi Anggaran, Narasi Progres, Kendala --}}
            @if ($this->isRO)
                <x-mary-input wire:model="targetAchievement" label="Capaian Target Bulan {{ $this->monthOptions[$this->selectedMonth - 1]['name'] ?? '' }}" placeholder="Contoh: 3 Spesies / 60%" hint="Kontribusi capaian bulan ini saja — triwulan dihitung sistem dari jumlah bulan-bulan di dalamnya (Q{{ $this->selectedQuarter }})." />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-mary-input wire:model="budgetRealization" label="Realisasi Anggaran Bulan Ini (Rp)" type="number" step="0.01" min="0" placeholder="0.00" />
                    <x-mary-input label="Pagu Anggaran RO (Rp)" :value="$this->roPaguLabel ?? '—'" readonly hint="Ditetapkan per periode di Manajemen Indikator." />
                </div>

                <x-mary-textarea wire:model="progressNarrative" label="Narasi Progres" rows="3" placeholder="Kegiatan dan progres yang telah dilakukan…" />
                <x-mary-textarea wire:model="problemNarrative" label="Kendala" rows="2" placeholder="Kendala yang dihadapi; kosongkan bila tidak ada." />
            @endif

            {{-- IKU: Capaian, Keterangan Proses, Upaya/Strategi Pencapaian, Permasalahan --}}
            @if ($this->isIKU)
                <x-mary-input wire:model="targetAchievement" label="Capaian Bulan {{ $this->monthOptions[$this->selectedMonth - 1]['name'] ?? '' }}" placeholder="Contoh: 3 Spesies / 60%" hint="Kontribusi capaian bulan ini saja." />
                <x-mary-textarea wire:model="processNote" label="Keterangan Proses" rows="2" placeholder="Keterangan proses pelaksanaan…" />
                <x-mary-textarea wire:model="achievementStrategy" label="Upaya/Strategi Pencapaian" rows="3" placeholder="Uraikan upaya/strategi yang dilakukan dalam mencapai target" />
                <x-mary-textarea wire:model="problemNarrative" label="Permasalahan" rows="2" placeholder="Permasalahan yang dihadapi; kosongkan bila tidak ada." />
            @endif

            {{-- IKP: Capaian, Keterangan Proses, Permasalahan, Tindak Lanjut --}}
            @if ($this->isIKP)
                <x-mary-input wire:model="targetAchievement" label="Capaian Bulan {{ $this->monthOptions[$this->selectedMonth - 1]['name'] ?? '' }}" placeholder="Contoh: 3 Spesies / 60%" hint="Kontribusi capaian bulan ini saja." />
                <x-mary-textarea wire:model="processNote" label="Keterangan Proses" rows="2" placeholder="Keterangan proses pelaksanaan…" />
                <x-mary-textarea wire:model="problemNarrative" label="Permasalahan" rows="2" placeholder="Permasalahan yang dihadapi; kosongkan bila tidak ada." />
                <x-mary-textarea wire:model="followUp" label="Tindak Lanjut" rows="2" placeholder="Tindak lanjut yang diperlukan…" />
            @endif

            {{-- IKK: Realisasi Capaian, Narasi Proses, Permasalahan --}}
            @if ($this->isIKK)
                <x-mary-input wire:model="targetAchievement" label="Capaian Bulan {{ $this->monthOptions[$this->selectedMonth - 1]['name'] ?? '' }}" placeholder="Contoh: 3 Spesies / 60%" hint="Kontribusi capaian bulan ini saja." />
                <x-mary-textarea wire:model="realizationAchievement" label="Realisasi Capaian" rows="2" placeholder="Capaian aktual yang direalisasikan…" />
                <x-mary-textarea wire:model="progressNarrative" label="Narasi Proses" rows="3" placeholder="Kegiatan dan proses yang telah dilakukan…" />
                <x-mary-textarea wire:model="problemNarrative" label="Permasalahan" rows="2" placeholder="Permasalahan yang dihadapi; kosongkan bila tidak ada." />
            @endif

            {{-- Upload bukti dukung (§11) --}}
            <x-mary-file wire:model="attachments" label="Bukti Dukung" multiple hint="Format: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG. Maks 5 MB per file." />

            {{-- Actions --}}
            <div class="flex justify-end gap-3 pt-2">
                <x-mary-button link="{{ route('realisasi') }}" label="Batal" class="btn-ghost" />
                <x-mary-button type="button" wire:click="saveDraft" label="Simpan sebagai Draft" class="btn-outline" spinner="saveDraft" />
                <x-mary-button type="button" wire:click="submit" label="Ajukan" class="btn-primary" spinner="submit" />
            </div>
        @elseif ($this->selectedGroupId)
            <x-mary-alert icon="o-information-circle" class="alert-info">Pilih indikator dari kelompok yang dipilih.</x-mary-alert>
        @endif
    </form>
    </x-mary-card>
</div>
