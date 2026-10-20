<?php

use App\Models\Indicator;
use App\Models\Unit;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Penugasan Indikator')] class extends Component {
    use Toast;

    public ?int $selectedUnitId = null;

    public function mount(): void
    {
        $this->authorize('create', Indicator::class);
    }

    #[Computed]
    public function units()
    {
        return Unit::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function assignedIds()
    {
        if (! $this->selectedUnitId) {
            return collect();
        }

        return Indicator::whereHas('units', fn ($q) => $q->where('units.id', $this->selectedUnitId))
            ->pluck('id');
    }

    public function toggleAssignment(int $indicatorId): void
    {
        $this->authorize('create', Indicator::class);

        $indicator = Indicator::findOrFail($indicatorId);

        if (! $this->selectedUnitId) {
            return;
        }

        if ($indicator->units()->where('units.id', $this->selectedUnitId)->exists()) {
            $indicator->units()->detach($this->selectedUnitId);
            $this->success('Penugasan dicabut.');
        } else {
            $indicator->units()->attach($this->selectedUnitId);
            $this->success('Indikator ditugaskan.');
        }
    }

    #[Computed]
    public function indicatorsByGroup()
    {
        return \App\Models\IndicatorGroup::with(['indicators' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }
};
?>

<div>
    <x-mary-header title="Penugasan Indikator" subtitle="Tetapkan indikator yang menjadi tanggung jawab setiap unit kerja." separator />

    <div class="mb-6">
        <x-mary-select wire:model.live="selectedUnitId" :options="$this->units" option-value="id" option-label="name" placeholder="— Pilih unit —" label="Pilih Unit Kerja" class="max-w-md" />
    </div>

    @if ($this->selectedUnitId)
        @foreach ($this->indicatorsByGroup as $group)
            <div class="mb-6">
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-base-content">
                    <x-mary-badge class="badge-secondary badge-sm">{{ $group->code }}</x-mary-badge>
                    {{ $group->name }}
                </h3>
                <div class="grid gap-2">
                    @foreach ($group->indicators as $indicator)
                        @php $assigned = $this->assignedIds->contains($indicator->id); @endphp
                        <div class="flex items-center justify-between rounded-lg border border-base-300 bg-base-100 px-4 py-3">
                            <div>
                                <span class="text-sm font-medium text-base-content">{{ $indicator->name }}</span>
                                <span class="ml-2 text-xs text-base-content/60">Target: {{ rtrim(rtrim(number_format($indicator->target, 2, ',', '.'), '0'), ',') }} {{ $indicator->unit }}</span>
                            </div>
                            <x-mary-button
                                :label="$assigned ? 'Ditugaskan' : 'Tugaskan'"
                                :icon="$assigned ? 'o-check' : 'o-plus'"
                                class="{{ $assigned ? 'btn-primary' : 'btn-ghost' }} btn-sm"
                                wire:click="toggleAssignment({{ $indicator->id }})"
                            />
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <x-mary-alert icon="o-information-circle" description="Pilih unit kerja di atas untuk mengelola penugasan indikator." class="alert-info" />
    @endif
</div>
