<?php

namespace App\Exports;

use App\Exports\Concerns\WritesSpreadsheetExport;
use App\Models\Report;
use Illuminate\Support\Collection;

class PerformanceSummaryExport
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
        return ['Indikator', 'Kelompok', 'Unit', 'Periode', 'Triwulan', 'Target', 'Capaian', 'Realisasi Capaian', 'Keterangan Proses', 'Tindak Lanjut', 'Realisasi Anggaran', 'Status'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        return $this->reports
            ->map(fn (Report $report) => [
                $report->indicator->name,
                $report->indicator->group->code,
                $report->unit->name,
                $report->reportingPeriod->label,
                $report->quarter ? 'Q'.$report->quarter : '—',
                (string) $report->indicator->resolvedTarget($report->reporting_period_id),
                (string) ($report->target_achievement ?? '—'),
                (string) ($report->realization_achievement ?? '—'),
                (string) ($report->process_note ?? '—'),
                (string) ($report->follow_up ?? '—'),
                $report->indicator->group->code === 'RO' ? (string) ($report->budget_realization ?? '—') : '—',
                $report->status,
            ])
            ->values()
            ->all();
    }

    protected function sheetTitle(): string
    {
        return 'Ringkasan Kinerja';
    }
}
