<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds an optional metadata block (title, period, download stamp) above the
 * header row of a PhpSpreadsheet export, and centralises the streamed download
 * response so every export shares identical file-building logic.
 */
trait WritesSpreadsheetExport
{
    /**
     * @var array{title: string, period: string, generated: string}|array{}
     */
    protected array $meta = [];

    /**
     * Stamp the export with a metadata header rendered above the table.
     *
     * @return $this
     */
    public function withMeta(string $title, ?string $periodLabel, ?string $generatedBy = null, ?string $generatedAt = null): static
    {
        $this->meta = [
            'title' => $title,
            'period' => 'Periode: '.($periodLabel ?? 'Semua periode'),
            'generated' => sprintf(
                'Diunduh: %s oleh %s',
                $generatedAt ?? now()->format('d-m-Y H:i'),
                $generatedBy ?? '—',
            ),
        ];

        return $this;
    }

    abstract public function headings(): array;

    /**
     * @return array<int, array<int, string>>
     */
    abstract public function rows(): array;

    abstract protected function sheetTitle(): string;

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle());

        $row = 1;

        if ($this->meta !== []) {
            $sheet->setCellValue('A'.$row, $this->meta['title']);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
            $sheet->setCellValue('A'.$row, $this->meta['period']);
            $row++;
            $sheet->setCellValue('A'.$row, $this->meta['generated']);
            $row++;
            $row++;
        }

        $sheet->fromArray([$this->headings(), ...$this->rows()], null, 'A'.$row);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
