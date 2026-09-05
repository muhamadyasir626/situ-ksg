<?php

namespace App\Exports;

use App\Exports\Concerns\WritesSpreadsheetExport;
use App\Models\Report;
use Illuminate\Support\Collection;

/**
 * Dump data mentah lengkap satu bulan tertentu (Fase 22) — mencakup seluruh kolom
 * yang diinput di form Input Pelaporan, bukan hanya ringkasan kinerja resmi
 * (yang sudah dilayani `PerformanceSummaryExport`, khusus laporan Terverifikasi).
 */
class MonthlyReportExport
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
        return [
            'Kelompok', 'Indikator', 'Unit Kerja', 'Tahun', 'Bulan', 'Triwulan',
            'Target', 'Satuan', 'Pagu Anggaran RO', 'Capaian', 'Realisasi Anggaran',
            'Narasi Progres', 'Permasalahan',
            'Risiko Eksternal/Kemitraan', 'Risiko Strategis', 'Risiko Kecurangan', 'Risiko Kebijakan Internal/Eksternal',
            'Status', 'Diajukan', 'Diverifikasi',
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        return $this->reports
            ->map(function (Report $report) {
                $indicator = $report->indicator;
                $isRo = $indicator->group->code === 'RO';
                $pagu = $isRo ? $indicator->paguFor($report->reporting_period_id) : null;

                return [
                    $indicator->group->code,
                    $indicator->name,
                    $report->unit->name,
                    (string) $report->reportingPeriod->year,
                    Report::monthLabel($report->month) ?? '—',
                    $report->quarter ? 'Q'.$report->quarter : '—',
                    (string) $indicator->resolvedTarget($report->reporting_period_id),
                    (string) $indicator->resolvedUnit($report->reporting_period_id),
                    $pagu !== null ? 'Rp '.number_format((float) $pagu, 2, ',', '.') : '—',
                    (string) ($report->target_achievement ?? '—'),
                    $isRo ? (string) ($report->budget_realization ?? '—') : '—',
                    (string) ($report->progress_narrative ?? '—'),
                    (string) ($report->problem_narrative ?? '—'),
                    (string) ($report->risk_external_narrative ?? '—'),
                    (string) ($report->risk_strategic_narrative ?? '—'),
                    (string) ($report->risk_fraud_narrative ?? '—'),
                    (string) ($report->risk_policy_narrative ?? '—'),
                    $report->status,
                    $report->submitted_at?->format('d-m-Y H:i') ?? '—',
                    $report->verified_at?->format('d-m-Y H:i') ?? '—',
                ];
            })
            ->values()
            ->all();
    }

    protected function sheetTitle(): string
    {
        return 'Laporan Bulanan';
    }
}
