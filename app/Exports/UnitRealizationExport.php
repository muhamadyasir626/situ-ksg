<?php

namespace App\Exports;

use App\Exports\Concerns\WritesSpreadsheetExport;
use App\Models\Report;
use Illuminate\Support\Collection;

class UnitRealizationExport
{
    use WritesSpreadsheetExport;

    /**
     * @param  Collection<int, Report>  $reports
     */
    public function __construct(protected Collection $reports)
    {
        //
    }

    public function headings(): array
    {
        return ['Unit', 'Indikator', 'Kelompok', 'Periode', 'Triwulan', 'Capaian', 'Realisasi Capaian', 'Keterangan Proses', 'Tindak Lanjut', 'Realisasi Anggaran', 'Status Pelaporan', 'Status Verifikasi'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        return $this->reports
            ->map(fn (Report $report) => [
                $report->unit->name,
                $report->indicator->name,
                $report->indicator->group->code,
                $report->reportingPeriod->label,
                $report->quarter ? 'Q'.$report->quarter : '—',
                (string) ($report->target_achievement ?? '—'),
                (string) ($report->realization_achievement ?? '—'),
                (string) ($report->process_note ?? '—'),
                (string) ($report->follow_up ?? '—'),
                $report->indicator->group->code === 'RO' ? (string) ($report->budget_realization ?? '—') : '—',
                $report->status,
                $report->status === 'Terverifikasi' ? 'Terverifikasi' : 'Belum terverifikasi',
            ])
            ->values()
            ->all();
    }

    protected function sheetTitle(): string
    {
        return 'Realisasi per Unit';
    }
}
