<?php

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\IndicatorPeriodSetting;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Risk;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component {
    private const float ATTENTION_THRESHOLD = 50.0;

    public ?int $filterPeriod = null;

    protected ?int $scopeUnitId = null;

    public function mount(): void
    {
        if (Auth::user()->hasRole('Pelapor Unit')) {
            $this->scopeUnitId = Auth::user()->unit_id;
        }

        // Fase 21: default periode aktif; seluruh metrik & chart mengikuti periode ini.
        $this->filterPeriod = ReportingPeriod::active()?->id;
    }

    public function updatedFilterPeriod(): void
    {
        $this->dispatch('monthly-chart-updated', $this->monthlyChartData);
    }

    #[Computed]
    public function periods()
    {
        return ReportingPeriod::orderByDesc('year')->get(['id', 'label']);
    }

    #[Computed]
    public function groups()
    {
        return IndicatorGroup::orderBy('sort_order')->get();
    }

    #[Computed]
    public function activeIndicatorCount(): int
    {
        return Indicator::where('is_active', true)
            ->when($this->scopeUnitId, fn ($q) => $q->whereHas('units', fn ($u) => $u->where('units.id', $this->scopeUnitId)))
            ->count();
    }

    #[Computed]
    public function averageAchievementPercent(): ?float
    {
        $percentages = $this->verifiedReportsQuery()
            ->with('indicator')
            ->get()
            ->map(fn (Report $r) => $this->achievementPercent($r))
            ->filter(fn (?float $p) => $p !== null);

        return $percentages->isEmpty() ? null : round($percentages->avg(), 1);
    }

    #[Computed]
    public function budgetRealizationPercent(): ?float
    {
        $reports = $this->verifiedReportsQuery()
            ->whereHas('indicator.group', fn ($query) => $query->where('code', 'RO'))
            ->get();

        if ($reports->isEmpty()) {
            return null;
        }

        $totalRealization = (float) $reports->sum('budget_realization');

        // Fase 21: pagu bersumber dari setting per-periode (bukan indicator_budgets).
        $totalCeiling = (float) IndicatorPeriodSetting::query()
            ->whereIn('indicator_id', $reports->pluck('indicator_id'))
            ->where('reporting_period_id', $this->filterPeriod)
            ->sum('pagu_amount');

        return $totalCeiling > 0 ? round(($totalRealization / $totalCeiling) * 100, 1) : null;
    }

    /**
     * Risiko yang belum berstatus "Selesai" dianggap "terbuka" — masih butuh tindak lanjut (§14.4).
     */
    #[Computed]
    public function openRiskCount(): int
    {
        return $this->openRisksQuery()->count();
    }

    #[Computed]
    public function statusCounts(): array
    {
        $counts = Report::query()
            ->when($this->filterPeriod, fn ($q) => $q->where('reporting_period_id', $this->filterPeriod))
            ->when($this->scopeUnitId, fn ($q) => $q->where('unit_id', $this->scopeUnitId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'Draft' => (int) ($counts['Draft'] ?? 0),
            'Diajukan' => (int) ($counts['Diajukan'] ?? 0),
            'Dikembalikan' => (int) ($counts['Dikembalikan'] ?? 0),
            'Terverifikasi' => (int) ($counts['Terverifikasi'] ?? 0),
        ];
    }

    #[Computed]
    public function groupAchievements(): array
    {
        $reports = $this->verifiedReportsQuery()->with('indicator.group')->get();

        return $this->groups->map(function (IndicatorGroup $group) use ($reports) {
            $groupReports = $reports->filter(fn (Report $r) => $r->indicator->indicator_group_id === $group->id);
            $percentages = $groupReports->map(fn (Report $r) => $this->achievementPercent($r))->filter(fn (?float $p) => $p !== null);

            return [
                'code' => $group->code,
                'name' => $group->name,
                'percent' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
                'count' => $groupReports->count(),
            ];
        })->all();
    }

    #[Computed]
    public function priorityRisks()
    {
        return $this->openRisksQuery()
            ->when($this->filterPeriod, fn ($q) => $q->whereHas('report', fn ($r) => $r->where('reporting_period_id', $this->filterPeriod)))
            ->with(['report.indicator.group', 'report.unit'])
            ->latest('updated_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function attentionIndicators()
    {
        if (! $this->filterPeriod) {
            return collect();
        }

        $indicators = Indicator::where('is_active', true)
            ->with(['group', 'units' => function ($query) {
                if ($this->scopeUnitId) {
                    $query->where('units.id', $this->scopeUnitId);
                }
            }])
            ->get();

        $reports = Report::where('reporting_period_id', $this->filterPeriod)
            ->when($this->scopeUnitId, fn ($q) => $q->where('unit_id', $this->scopeUnitId))
            ->get()
            ->keyBy(fn (Report $r) => $r->indicator_id.'-'.$r->unit_id);

        return $indicators
            ->flatMap(fn (Indicator $indicator) => $indicator->units->map(fn (Unit $unit) => [$indicator, $unit]))
            ->map(function (array $pair) use ($reports) {
                [$indicator, $unit] = $pair;
                $report = $reports->get($indicator->id.'-'.$unit->id);

                if (! $report) {
                    return ['indicator' => $indicator, 'unit' => $unit, 'reason' => 'Belum melapor'];
                }

                if ($report->status === 'Terverifikasi') {
                    $percent = $this->achievementPercent($report);
                    if ($percent !== null && $percent < self::ATTENTION_THRESHOLD) {
                        return ['indicator' => $indicator, 'unit' => $unit, 'reason' => "Capaian rendah ({$percent}%)"];
                    }
                }

                return null;
            })
            ->filter()
            ->values()
            ->take(10);
    }

    /**
     * Rata-rata capaian per bulan (Jan–Des) untuk tiap kelompok indikator pada
     * periode terpilih (Fase 22) — bar chart berkelompok menggantikan chart
     * "Capaian per Triwulan (Q1–Q4)" lama, yang kini redundan karena triwulan
     * hanya agregasi (jumlah) dari bulan-bulan di dalamnya.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float|null>}>}
     */
    #[Computed]
    public function monthlyChartData(): array
    {
        $reports = $this->verifiedReportsQuery()
            ->with('indicator.group')
            ->whereNotNull('month')
            ->get();

        $datasets = $this->groups->map(function (IndicatorGroup $group) use ($reports) {
            $byMonth = array_fill(1, 12, []);

            foreach ($reports as $r) {
                if ($r->indicator->indicator_group_id !== $group->id) {
                    continue;
                }

                $percent = $this->achievementPercent($r);
                if ($percent !== null) {
                    $byMonth[$r->month][] = $percent;
                }
            }

            $data = [];
            foreach (range(1, 12) as $month) {
                $vals = $byMonth[$month];
                $data[] = $vals === [] ? null : round(array_sum($vals) / count($vals), 1);
            }

            return ['label' => $group->code, 'data' => $data];
        })->all();

        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            'datasets' => $datasets,
        ];
    }

    protected function verifiedReportsQuery()
    {
        return Report::query()
            ->where('status', 'Terverifikasi')
            ->when($this->filterPeriod, fn ($q) => $q->where('reporting_period_id', $this->filterPeriod))
            ->when($this->scopeUnitId, fn ($q) => $q->where('unit_id', $this->scopeUnitId));
    }

    protected function openRisksQuery()
    {
        return Risk::query()
            ->where('status', '!=', Risk::STATUS_SELESAI)
            ->when($this->scopeUnitId, fn ($q) => $q->whereHas('report', fn ($r) => $r->where('unit_id', $this->scopeUnitId)));
    }

    /**
     * Capaian dicatat sebagai narasi bebas (mis. "3 Spesies", "60%"). Ambil angka pertama
     * dan bandingkan terhadap target indikator untuk mendapatkan persentase capaian.
     * Fase 21: target diambil dari setting per-periode (fallback ke dasar).
     */
    protected function achievementPercent(Report $report): ?float
    {
        $target = (float) ($report->indicator->resolvedTarget($report->reporting_period_id) ?? $report->indicator->target);

        if ($target <= 0 || ! preg_match('/-?\d+(?:[.,]\d+)?/', (string) $report->target_achievement, $matches)) {
            return null;
        }

        $value = (float) str_replace(',', '.', $matches[0]);

        return min(100.0, max(0.0, round(($value / $target) * 100, 1)));
    }
};
?>

<div class="space-y-6">
    <x-mary-header title="Dashboard" subtitle="Ringkasan capaian, anggaran, dan risiko dari laporan terverifikasi." separator>
        <x-slot:actions>
            <x-mary-select wire:model.live="filterPeriod" :options="$this->periods" option-value="id" option-label="label" placeholder="Pilih periode" class="max-w-[200px] select-sm" />
            <x-mary-button
                label="Ekspor Ringkasan"
                icon="o-arrow-down-tray"
                class="btn-sm border border-base-300 bg-base-100 text-base-content hover:bg-base-200"
                link="{{ route('laporan.export.performance-pdf') }}"
                no-wire-navigate
            />
            @can('create', Report::class)
                <x-mary-button
                    label="Input Pelaporan"
                    icon="o-plus"
                    class="btn-primary btn-sm"
                    link="{{ route('realisasi.create') }}"
                />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-mary-stat class="border border-base-300" title="Indikator Aktif" :value="$this->activeIndicatorCount" description="IKU, IKP, IKK, dan RO" />
        <x-mary-stat
            class="border border-base-300"
            title="Rata-rata Capaian"
            :value="$this->averageAchievementPercent !== null ? number_format($this->averageAchievementPercent, 1, ',', '.').' %' : '—'"
            description="Dari laporan terverifikasi"
        />
        <x-mary-stat
            class="border border-base-300"
            title="Realisasi Anggaran"
            :value="$this->budgetRealizationPercent !== null ? number_format($this->budgetRealizationPercent, 1, ',', '.').' %' : '—'"
            description="Realisasi vs pagu terverifikasi"
        />
        <x-mary-stat class="border border-base-300" title="Risiko Terbuka" :value="$this->openRiskCount" description="Butuh tindak lanjut" icon="o-exclamation-triangle" color="text-warning" />
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        @foreach (['Draft', 'Diajukan', 'Dikembalikan', 'Terverifikasi'] as $status)
            @php
                $badgeClass = match ($status) {
                    'Terverifikasi' => 'badge-success',
                    'Dikembalikan' => 'badge-error',
                    default => 'badge-warning',
                };
            @endphp
            <x-mary-card class="border border-base-300">
                <x-mary-badge class="{{ $badgeClass }} badge-sm">{{ $status }}</x-mary-badge>
                <strong class="mt-2 block text-2xl font-semibold text-base-content">{{ $this->statusCounts[$status] }}</strong>
                <small class="text-xs text-base-content/60">laporan</small>
            </x-mary-card>
        @endforeach
    </div>

    <x-mary-card class="border border-base-300">
        <h3 class="mb-3 text-sm font-semibold text-base-content">Capaian per Kelompok</h3>
        <div class="space-y-3">
            @foreach ($this->groupAchievements as $group)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-base-content">{{ $group['code'] }} — {{ $group['name'] }}</span>
                        <span class="text-base-content/60">{{ number_format($group['percent'], 1, ',', '.') }}% ({{ $group['count'] }} laporan)</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-base-300">
                        <div class="h-full rounded-full bg-secondary" style="width: {{ $group['percent'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-mary-card>

    <x-mary-card class="border border-base-300">
        <h3 class="mb-4 text-sm font-semibold text-base-content">Capaian per Bulan per Kelompok</h3>

        @if (collect($this->monthlyChartData['datasets'])->every(fn ($set) => empty(array_filter($set['data'], fn ($v) => $v !== null))))
            <p class="text-sm text-base-content/60">Belum ada laporan terverifikasi pada periode ini.</p>
        @else
            <div wire:ignore x-data="monthlyBarChart(@js($this->monthlyChartData))" class="h-64">
                <canvas x-ref="canvas"></canvas>
            </div>
        @endif
    </x-mary-card>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-mary-card class="border border-base-300">
            <h3 class="mb-3 text-sm font-semibold text-base-content">Risiko Prioritas</h3>
            <ul class="space-y-3">
                @forelse ($this->priorityRisks as $risk)
                    <li class="border-b border-base-300 pb-3 last:border-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2">
                            <strong class="text-sm text-base-content">{{ $risk->report->indicator->name }}</strong>
                            <x-mary-badge class="badge-secondary badge-sm">{{ $risk->report->indicator->group->code }}</x-mary-badge>
                        </div>
                        <small class="text-xs text-base-content/60">{{ $risk->report->unit->name }} — {{ $risk->category }}</small>
                        <p class="mt-1 line-clamp-2 text-sm text-base-content">{{ $risk->narrative }}</p>
                    </li>
                @empty
                    <p class="text-sm text-base-content/60">Tidak ada risiko yang membutuhkan tindak lanjut.</p>
                @endforelse
            </ul>
        </x-mary-card>

        <x-mary-card class="border border-base-300">
            <h3 class="mb-3 text-sm font-semibold text-base-content">Indikator yang Membutuhkan Perhatian</h3>
            <x-mary-table :headers="[['key' => 'indicator', 'label' => 'Indikator'], ['key' => 'unit', 'label' => 'Unit'], ['key' => 'alasan', 'label' => 'Alasan']]" :rows="$this->attentionIndicators" class="table-sm" no-hover>
                @scope('cell_indicator', $item)
                    <span class="font-medium">{{ $item['indicator']->name }}</span>
                @endscope
                @scope('cell_unit', $item)
                    {{ $item['unit']->name }}
                @endscope
                @scope('cell_alasan', $item)
                    <span class="text-sm font-medium text-error">{{ $item['reason'] }}</span>
                @endscope
                @slot('empty')
                    <p class="text-sm text-base-content/60">Semua indikator dalam kondisi baik.</p>
                @endslot
            </x-mary-table>
        </x-mary-card>
    </div>
</div>
