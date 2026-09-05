<?php

namespace App\Exports;

use App\Exports\Concerns\WritesSpreadsheetExport;
use App\Models\Risk;
use Illuminate\Support\Collection;

class RiskRegisterExport
{
    use WritesSpreadsheetExport;

    /**
     * @param  Collection<int, Risk>  $risks
     */
    public function __construct(protected Collection $risks)
    {
        //
    }

    public function headings(): array
    {
        return [
            'Indikator', 'Kelompok', 'Unit', 'Periode', 'Triwulan',
            'Kategori', 'Narasi', 'Status', 'PIC', 'Tenggat',
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        return $this->risks
            ->map(fn (Risk $risk) => [
                $risk->report->indicator->name,
                $risk->report->indicator->group->code,
                $risk->report->unit->name,
                $risk->report->reportingPeriod->label,
                $risk->report->quarter ? 'Q'.$risk->report->quarter : '—',
                $risk->category,
                $risk->narrative,
                $risk->status,
                $risk->pic ?? '—',
                $risk->due_date?->format('d-m-Y') ?? '—',
            ])
            ->values()
            ->all();
    }

    protected function sheetTitle(): string
    {
        return 'Register Risiko';
    }
}
