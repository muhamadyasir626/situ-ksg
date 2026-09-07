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

new #[Layout('layouts.app')] #[Title('Input Pelaporan')] class extends Component {
    use WithFileUploads, Toast;

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
            $this->achievementStrategy = $r->achievement_strategy !== null ? (string) $r->achievement_strategy : nul;
            $this->progressNarrative = $r->progress_narrative;
            $this->problemNarrative = $r->problem_narrative;
            $this->riskExternal = $r->risk_external_narrative;
            $this->riskStrategic = $r->risk_strategic_narrative;
            $this->riskFraud = $r->risk_fraud_narrative;
            $this->riskPolicy = $r->risk_policy_narrative;
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
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ];
    }

    protected function submitRules(): array
    {
        $rules = $this->draftRules();
        $rules['targetAchievement'] = ['required', 'string'];
        if ($this->isRO) {
            $rules['budgetRealization'] = ['required', 'numeric', 'min:0'];
        }
        $rules['progressNarrative'] = ['required', 'string', 'max:5000'];

        return $rules;
    }

    /**
     * Menolak indikator yang tidak ditugaskan ke unit pengguna (§3.2). Ditegakkan sebagai
     * rule validasi (bukan addError manual) agar tidak tertimpa reset error bag Livewire
     * saat validate() dipanggil ulang.
     */
    protected function authorizedIndicatorRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
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
            'budget_realization' => $this->isRO && $this->budgetRealization !== null && $this->budgetRealization !== '' ? $this->budgetRealization : null,
            'progress_narrative' => $this->progressNarrative,
            'achievement_strategy' => $this->achievementStrategy ?: null,
            'problem_narrative' => $this->problemNarrative,
            'risk_external_narrative' => $this->riskExternal,
            'risk_strategic_narrative' => $this->riskStrategic,
            'risk_fraud_narrative' => $this->riskFraud,
            'risk_policy_narrative' => $this->riskPolicy,
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
    <x-mary-header :title="$editingReportId ? 'Edit Pelaporan' : 'Input Pelaporan Capaian'" subtitle="Kolom F–N: capaian, anggaran, progres, kendala, dan Identifikasi Risiko." separator />

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
                <div>
                    Target: <strong>{{ rtrim(rtrim(number_format($this->selectedIndicator->target, 2, ',', '.'), '0'), ',') }} {{ $this->selectedIndicator->unit }}</strong>
                    @if ($this->isIKU)
                        — Semua Identifikasi Risiko wajib diisi.
                    @endif
                </div>
            </x-mary-alert>

            {{-- Kolom F–I (§4) --}}
            <x-mary-input wire:model="targetAchievement" label="Capaian Bulan {{ $this->monthOptions[$this->selectedMonth - 1]['name'] ?? '' }}" placeholder="Contoh: 3 Spesies / 60%" hint="Kontribusi capaian bulan ini saja — triwulan dihitung sistem dari jumlah bulan-bulan di dalamnya (Q{{ $this->selectedQuarter }})." />

            @if ($this->isRO)
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-mary-input wire:model="budgetRealization" label="Realisasi Anggaran Bulan Ini (Rp)" type="number" step="0.01" min="0" placeholder="0.00" />

                    <div>
                        <x-mary-input label="Pagu Anggaran RO (Rp)" :value="$this->roPaguLabel ?? '—'" readonly hint="Ditetapkan per periode di Manajemen Indikator." />
                    </div>
                </div>
            @endif

            <x-mary-textarea wire:model="progressNarrative" label="Narasi Progres" rows="3" placeholder="Kegiatan dan progres yang telah dilakukan…" />
            @if ($this->isIKU)
            <x-mary-textarea wire:model="achievementStrategy" label="Upaya/Strategi Pencapaian" rows="3" placeholder="Uraikan upaya/strategi yang dilakukan dalam mencapai target" />
            @endif

            <x-mary-textarea wire:model="problemNarrative" label="Permasalahan" rows="2" placeholder="Kendala yang dihadapi; kosongkan bila tidak ada." />

            {{-- Identifikasi Risiko — hanya kelompok IKU (§14.4) --}}
            @if ($this->isIKP)
                <div class="rounded-xl border border-base-300 bg-base-100 p-4">
                    <h3 class="mb-1 font-semibold">Identifikasi Risiko</h3>
                    <p class="mb-3 text-xs text-error">Semua kategori wajib diisi untuk kelompok IKU.</p>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <x-mary-textarea wire:model="riskExternal" label="Eksternal / Kemitraan" rows="2" />
                        <x-mary-textarea wire:model="riskStrategic" label="Strategis" rows="2" />
                        <x-mary-textarea wire:model="riskFraud" label="Kecurangan" rows="2" />
                        <x-mary-textarea wire:model="riskPolicy" label="Kebijakan Internal/Eksternal" rows="2" />
                    </div>
                </div>
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
