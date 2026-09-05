<?php

namespace App\Http\Controllers;

use App\Exports\MonthlyReportExport;
use App\Exports\PerformanceSummaryExport;
use App\Exports\RiskRegisterExport;
use App\Exports\UnitRealizationExport;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Risk;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function performanceExcel(Request $request): StreamedResponse
    {
        $period = $this->resolvePeriod($request);

        $reports = $this->scopedReportsQuery($period)
            ->where('status', 'Terverifikasi')
            ->with(['indicator.group', 'unit', 'reportingPeriod'])
            ->get();

        return (new PerformanceSummaryExport($reports))
            ->withMeta('Ringkasan Kinerja', $period?->label, Auth::user()->name)
            ->download($this->exportFilename('ringkasan-kinerja', 'xlsx', $period));
    }

    public function performancePdf(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        $reports = $this->scopedReportsQuery($period)
            ->where('status', 'Terverifikasi')
            ->with(['indicator.group', 'unit', 'reportingPeriod'])
            ->get();

        return Pdf::loadView('exports.performance-summary-pdf', [
            'reports' => $reports,
            'period' => $period,
        ])->download($this->exportFilename('ringkasan-kinerja', 'pdf', $period));
    }

    public function riskRegisterExcel(Request $request): StreamedResponse
    {
        $period = $this->resolvePeriod($request);

        $risks = Risk::query()
            ->whereHas('report', fn ($q) => $q
                ->when($period, fn ($q2) => $q2->where('reporting_period_id', $period->id))
                ->when(Auth::user()->hasRole('Pelapor Unit'), fn ($q2) => $q2->where('unit_id', Auth::user()->unit_id)))
            ->with(['report.indicator.group', 'report.unit', 'report.reportingPeriod'])
            ->get();

        return (new RiskRegisterExport($risks))
            ->withMeta('Register Risiko', $period?->label, Auth::user()->name)
            ->download($this->exportFilename('register-risiko', 'xlsx', $period));
    }

    public function unitRealizationExcel(Request $request): StreamedResponse
    {
        $period = $this->resolvePeriod($request);

        $reports = $this->scopedReportsQuery($period)
            ->with(['indicator.group', 'unit', 'reportingPeriod'])
            ->get();

        return (new UnitRealizationExport($reports))
            ->withMeta('Realisasi per Unit', $period?->label, Auth::user()->name)
            ->download($this->exportFilename('realisasi-per-unit', 'xlsx', $period));
    }

    /**
     * Dump data mentah lengkap satu bulan tertentu (Fase 22) — wajib pilih bulan,
     * mencakup seluruh kolom yang diinput di form Input Pelaporan (bukan hanya
     * ringkasan resmi Terverifikasi seperti `performanceExcel`).
     */
    public function monthlyReportExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer', 'exists:reporting_periods,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ], [
            'month.required' => 'Pilih bulan terlebih dahulu sebelum mengunduh.',
        ]);

        $period = ReportingPeriod::findOrFail($validated['period_id']);
        $month = (int) $validated['month'];
        $monthLabel = Report::monthLabel($month);

        $reports = Report::query()
            ->where('reporting_period_id', $period->id)
            ->where('month', $month)
            ->when(Auth::user()->hasRole('Pelapor Unit'), fn ($q) => $q->where('unit_id', Auth::user()->unit_id))
            ->with(['indicator.group', 'unit', 'reportingPeriod'])
            ->get();

        return (new MonthlyReportExport($reports))
            ->withMeta('Laporan Bulanan Lengkap', "{$monthLabel} {$period->year}", Auth::user()->name)
            ->download($this->exportFilename('laporan-bulanan-lengkap', 'xlsx', $period, $monthLabel));
    }

    /**
     * Resolve the requested reporting period. A missing/null `period_id` means
     * "Semua periode" (no period filter) — it deliberately does NOT fall back to
     * the active period, which carries no reports when monthly sub-periods are
     * in use and would silently produce empty exports.
     */
    protected function resolvePeriod(Request $request): ?ReportingPeriod
    {
        $periodId = $request->integer('period_id');

        return $periodId ? ReportingPeriod::find($periodId) : null;
    }

    /**
     * Build a download filename prefixed with a timestamp so the newest export
     * always sorts to the top of a file listing, followed by the export slug, a
     * slug of the resolved period (or "semua-periode" when unfiltered), and an
     * optional extra segment (mis. nama bulan) for exports scoped further.
     */
    protected function exportFilename(string $slug, string $extension, ?ReportingPeriod $period, ?string $extra = null): string
    {
        $periodSlug = $period ? Str::slug($period->label) : 'semua-periode';

        $segments = array_filter([
            now()->format('Ymd-Hi'),
            $slug,
            $periodSlug,
            $extra ? Str::slug($extra) : null,
        ]);

        return implode('_', $segments).'.'.$extension;
    }

    /**
     * @return Builder<Report>
     */
    protected function scopedReportsQuery(?ReportingPeriod $period): Builder
    {
        return Report::query()
            ->when($period, fn ($q) => $q->where('reporting_period_id', $period->id))
            ->when(Auth::user()->hasRole('Pelapor Unit'), fn ($q) => $q->where('unit_id', Auth::user()->unit_id));
    }
}
