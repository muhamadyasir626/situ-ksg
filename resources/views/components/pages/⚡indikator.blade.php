<?php

use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportingPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Indikator')] class extends Component {
    public ?int $filterPeriod = null;

    public function mount(): void
    {
        $this->filterPeriod = ReportingPeriod::active()?->id;
    }

    #[Computed]
    public function periods()
    {
        return ReportingPeriod::orderByDesc('year')->get(['id', 'label']);
    }

    #[Computed]
    public function hasUnit(): bool
    {
        return Auth::user()->unit_id !== null;
    }

    /**
     * Indikator aktif yang ditugaskan ke unit pengguna, dikelompok per kelompok,
     * dengan setting per-periode di-eager-load agar target/unit/pagu sesuai periode.
     */
    #[Computed]
    public function groups()
    {
        $unitId = Auth::user()->unit_id;

        if (! $unitId || ! $this->filterPeriod) {
            return collect();
        }

        return IndicatorGroup::with(['indicators' => function ($q) use ($unitId) {
            $q->where('is_active', true)
                ->whereHas('units', fn ($q2) => $q2->where('units.id', $unitId))
                ->with(['periodSettings' => fn ($q2) => $q2->where('reporting_period_id', $this->filterPeriod)])
                ->orderBy('sort_order');
        }])
            ->whereHas('indicators', function ($q) use ($unitId) {
                $q->where('is_active', true)
                    ->whereHas('units', fn ($q2) => $q2->where('units.id', $unitId));
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Capaian kumulatif Q1–Q4 per indikator untuk unit & periode terpilih (anti N+1).
     *
     * @return array<int, array<int, string|null>>
     */
    #[Computed]
    public function progressMap(): array
    {
        $unitId = Auth::user()->unit_id;

        if (! $unitId || ! $this->filterPeriod) {
            return [];
        }

        $indicatorIds = $this->groups->flatMap->indicators->pluck('id')->all();

        return Report::quarterlyAchievementByIndicator($indicatorIds, $unitId, $this->filterPeriod);
    }

    public function tableHeaders(): array
    {
        return [
            ['key' => 'name', 'label' => __('Indikator'), 'class' => 'w-full'],
            ['key' => 'target', 'label' => __('Target'), 'class' => 'w-20 whitespace-nowrap text-right'],
            ['key' => 'unit', 'label' => __('Satuan'), 'class' => 'w-24 whitespace-nowrap'],
            ['key' => 'pagu', 'label' => __('Pagu'), 'class' => 'w-28 whitespace-nowrap text-right'],
            ['key' => 'q1', 'label' => 'Q1', 'class' => 'w-16 whitespace-nowrap text-right'],
            ['key' => 'q2', 'label' => 'Q2', 'class' => 'w-16 whitespace-nowrap text-right'],
            ['key' => 'q3', 'label' => 'Q3', 'class' => 'w-16 whitespace-nowrap text-right'],
            ['key' => 'q4', 'label' => 'Q4', 'class' => 'w-16 whitespace-nowrap text-right'],
            ['key' => 'progress', 'label' => __('Capaian'), 'class' => 'w-20 whitespace-nowrap text-right'],
        ];
    }

    /**
     * Capaian kumulatif terbaru (triwulan tertinggi terisi) untuk sebuah indikator.
     */
    public function latestProgress(int $indicatorId): ?string
    {
        return isset($this->progressMap[$indicatorId])
            ? Report::latestAchievementFromQuarters($this->progressMap[$indicatorId])
            : null;
    }
};
?>

<div>
    <x-mary-header title="Indikator Kinerja" subtitle="Indikator yang ditugaskan kepada unit kerja Anda beserta target, pagu, dan capaian terbaru." separator>
        <x-slot:actions>
            <x-mary-select wire:model.live="filterPeriod" :options="$this->periods" option-value="id" option-label="label" placeholder="Pilih periode" class="max-w-[220px] select-sm" />
        </x-slot:actions>
    </x-mary-header>

    @if (!$this->hasUnit)
        <x-mary-alert icon="o-exclamation-triangle" class="alert-warning">
            Akun Anda belum dikaitkan dengan unit kerja. Hubungi Admin untuk mendapatkan penugasan unit.
        </x-mary-alert>
    @elseif ($this->groups->isEmpty())
        <x-mary-alert icon="o-information-circle" class="alert-info">
            Belum ada indikator yang ditugaskan ke unit Anda pada periode ini.
        </x-mary-alert>
    @else
        @foreach ($this->groups as $group)
            <div class="mb-6">
                <div class="mb-3 flex items-center gap-2">
                    <x-mary-badge :value="$group->code" class="badge-secondary badge-sm" />
                    <h2 class="text-sm font-semibold">{{ $group->name }}</h2>
                </div>
                <x-mary-card>
                    <div class="overflow-x-auto">
                        <x-mary-table :headers="$this->tableHeaders()" :rows="$group->indicators" class="table-sm">
                            @scope('cell_name', $indicator)
                                <span class="font-medium">{{ $indicator->name }}</span>
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
                                    <span class="whitespace-nowrap">Rp {{ number_format((float) $pagu, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            @endscope

                            @scope('cell_q1', $indicator)
                                @php $val = $this->progressMap[$indicator->id][1] ?? null; @endphp
                                {{ $val !== null ? rtrim(rtrim(number_format((float) $val, 2, ',', '.'), '0'), ',') : '—' }}
                            @endscope

                            @scope('cell_q2', $indicator)
                                @php $val = $this->progressMap[$indicator->id][2] ?? null; @endphp
                                {{ $val !== null ? rtrim(rtrim(number_format((float) $val, 2, ',', '.'), '0'), ',') : '—' }}
                            @endscope

                            @scope('cell_q3', $indicator)
                                @php $val = $this->progressMap[$indicator->id][3] ?? null; @endphp
                                {{ $val !== null ? rtrim(rtrim(number_format((float) $val, 2, ',', '.'), '0'), ',') : '—' }}
                            @endscope

                            @scope('cell_q4', $indicator)
                                @php $val = $this->progressMap[$indicator->id][4] ?? null; @endphp
                                {{ $val !== null ? rtrim(rtrim(number_format((float) $val, 2, ',', '.'), '0'), ',') : '—' }}
                            @endscope

                            @scope('cell_progress', $indicator)
                                @php $latest = $this->latestProgress($indicator->id); @endphp
                                {{ $latest !== null ? rtrim(rtrim(number_format((float) $latest, 2, ',', '.'), '0'), ',') : '—' }}
                            @endscope
                        </x-mary-table>
                    </div>
                </x-mary-card>
            </div>
        @endforeach
    @endif
</div>
