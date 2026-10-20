<?php

use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] #[Title('Realisasi')] class extends Component {
    use WithPagination, Toast;

    public string $filterStatus = '';

    #[Computed]
    public function statusOptions(): array
    {
        return [
            ['id' => '', 'name' => 'Semua status'],
            ['id' => 'Draft', 'name' => 'Draft'],
            ['id' => 'Diajukan', 'name' => 'Diajukan'],
            ['id' => 'Dikembalikan', 'name' => 'Dikembalikan'],
            ['id' => 'Terverifikasi', 'name' => 'Terverifikasi'],
        ];
    }

    #[Computed]
    public function tableHeaders(): array
    {
        return [
            ['key' => 'indicator.name', 'label' => 'Indikator'],
            ['key' => 'group', 'label' => 'Kelompok'],
            ['key' => 'reportingPeriod.label', 'label' => 'Periode'],
            ['key' => 'target_achievement', 'label' => 'Capaian'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    #[Computed]
    public function reports()
    {
        $query = Report::with(['indicator.group', 'reportingPeriod']);

        if (! Auth::user()->hasRole('Admin') && ! Auth::user()->hasRole('Verifikator') && ! Auth::user()->hasRole('Pimpinan')) {
            $query->where('unit_id', Auth::user()->unit_id);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->latest()->paginate(15);
    }

    public function delete(int $id): void
    {
        $report = Report::findOrFail($id);
        $this->authorize('delete', $report);
        $report->delete();

        $this->success('Laporan dihapus.');
        $this->dispatch('$refresh');
    }
};
?>

<div>
    <x-mary-header title="Pelaporan Capaian" subtitle="Kelola laporan capaian indikator bulanan sesuai penugasan unit kerja." separator>
        <x-slot:actions>
            @can('create', Report::class)
                <x-mary-button link="{{ route('realisasi.create') }}" icon="o-plus" label="Input Laporan" class="btn-primary btn-sm" />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    <div class="mb-4">
        <x-mary-select wire:model.live="filterStatus" :options="$this->statusOptions" option-value="id" option-label="name" class="max-w-[200px] select-sm" />
    </div>

    <x-mary-card>
        <x-mary-table :headers="$this->tableHeaders" :rows="$this->reports" with-pagination class="table-sm">
            @scope('cell_group', $report)
                <x-mary-badge class="badge-secondary badge-sm">{{ $report->indicator->group->code }}</x-mary-badge>
            @endscope

            @scope('cell_target_achievement', $report)
                {{ $report->target_achievement ?? '—' }}
            @endscope

            @scope('cell_status', $report)
                @php
                    $variant = match ($report->status) {
                        'Terverifikasi' => 'ok',
                        'Diajukan' => 'pending',
                        'Dikembalikan' => 'alert',
                        default => 'pending',
                    };
                @endphp
                <x-status-badge :variant="$variant">{{ $report->status }}</x-status-badge>
            @endscope

            @scope('actions', $report)
                <div class="flex justify-end gap-1">
                    @can('update', $report)
                        <x-mary-button link="{{ route('realisasi.edit', $report) }}" icon="o-pencil" class="btn-ghost btn-sm" />
                    @endcan
                    @can('delete', $report)
                        <x-mary-button wire:click="delete({{ $report->id }})" wire:confirm="Hapus laporan ini?" icon="o-trash" class="btn-ghost btn-sm" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>
</div>
