<?php

use App\Models\IndicatorBudget;
use App\Models\IndicatorPeriodSetting;
use App\Models\Report;
use App\Models\Risk;
use App\Models\User;
use App\Models\VerificationNote;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Layout('layouts.app')] #[Title('Audit Trail')] class extends Component {
    use WithPagination;

    public string $filterSubjectType = '';

    public ?int $filterCauserId = null;

    public ?string $filterFrom = null;

    public ?string $filterTo = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Auditor'), 403);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function subjectTypes(): array
    {
        return [
            IndicatorBudget::class => 'Pagu Anggaran',
            IndicatorPeriodSetting::class => 'Setting Indikator',
            Report::class => 'Laporan',
            VerificationNote::class => 'Catatan Verifikasi',
            Risk::class => 'Risiko',
        ];
    }

    #[Computed]
    public function causers()
    {
        return User::orderBy('name')->get();
    }

    #[Computed]
    public function subjectTypeOptions(): array
    {
        return collect($this->subjectTypes)
            ->map(fn (string $label, string $class) => ['id' => $class, 'name' => $label])
            ->values()
            ->all();
    }

    #[Computed]
    public function tableHeaders(): array
    {
        return [
            ['key' => 'waktu', 'label' => 'Waktu', 'sortable' => false],
            ['key' => 'pengguna', 'label' => 'Pengguna', 'sortable' => false],
            ['key' => 'resource', 'label' => 'Resource', 'sortable' => false],
            ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false],
            ['key' => 'perubahan', 'label' => 'Perubahan', 'sortable' => false],
        ];
    }

    #[Computed]
    public function activities()
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->filterSubjectType, fn ($q) => $q->where('subject_type', $this->filterSubjectType))
            ->when($this->filterCauserId, fn ($q) => $q->where('causer_id', $this->filterCauserId))
            ->when($this->filterFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->filterFrom))
            ->when($this->filterTo, fn ($q) => $q->whereDate('created_at', '<=', $this->filterTo))
            ->latest()
            ->paginate(20);
    }
};
?>

<div>
    <x-mary-header title="Audit Trail" subtitle="Jejak perubahan data dan aktivitas pengguna. Informasi ini hanya dapat dibaca." separator />

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-mary-select wire:model.live="filterSubjectType" :options="$this->subjectTypeOptions" option-value="id" option-label="name" placeholder="Semua jenis data" label="Jenis Data" class="select-sm" />
        <x-mary-select wire:model.live="filterCauserId" :options="$this->causers" option-value="id" option-label="name" placeholder="Semua pengguna" label="Pengguna" class="select-sm" />
        <x-mary-input type="date" wire:model.live="filterFrom" label="Dari tanggal" class="input-sm" />
        <x-mary-input type="date" wire:model.live="filterTo" label="Sampai tanggal" class="input-sm" />
    </div>

    <x-mary-card>
        <x-mary-table :headers="$this->tableHeaders" :rows="$this->activities" with-pagination class="table-sm">
            @scope('cell_waktu', $activity)
                {{ $activity->created_at?->format('d M Y H:i') }}
            @endscope
            @scope('cell_pengguna', $activity)
                {{ $activity->causer?->name ?? 'Sistem' }}
            @endscope
            @scope('cell_resource', $activity)
                {{ $this->subjectTypes[$activity->subject_type] ?? $activity->subject_type }}
            @endscope
            @scope('cell_aksi', $activity)
                {{ $activity->description }}
            @endscope
            @scope('cell_perubahan', $activity)
                @php
                    $old = $activity->attribute_changes?->get('old', []);
                    $new = $activity->attribute_changes?->get('attributes', []);
                @endphp
                @if ($new)
                    <div class="space-y-0.5 text-xs">
                        @foreach ($new as $field => $value)
                            <div>
                                <span class="font-medium text-base-content">{{ $field }}:</span>
                                @if ($old && array_key_exists($field, $old) && $old[$field] !== $value)
                                    <span class="text-base-content/60 line-through">{{ $old[$field] ?? '—' }}</span>
                                    →
                                @endif
                                <span>{{ $value ?? '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <span class="text-base-content/60">—</span>
                @endif
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Belum ada entri audit trail.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>
</div>
