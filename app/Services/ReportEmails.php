<?php

namespace App\Services;

use App\Models\EmailDelivery;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportEmails
{
    public function statusChanged(Report $report, ?string $note = null): void
    {
        $status = $report->status;
        if (! in_array($status, ['Diajukan', 'Terverifikasi', 'Dikembalikan'], true)) {
            return;
        }

        $report->loadMissing(['indicator', 'unit', 'reportingPeriod']);
        $submitted = $report->status === 'Diajukan';
        $recipients = $submitted
            ? User::role('Verifikator')->get()
            : User::role('Pelapor Unit')->where('unit_id', $report->unit_id)->get();
        $period = Report::monthLabel($report->month).' '.$report->reportingPeriod->year;
        $intro = match ($status) {
            'Diajukan' => 'Laporan baru telah diajukan oleh tim kerja dan menunggu pemeriksaan Anda.',
            'Terverifikasi' => 'Laporan tim kerja Anda telah diperiksa dan disetujui oleh verifikator.',
            'Dikembalikan' => 'Laporan tim kerja Anda dikembalikan. Perbaiki sesuai catatan verifikator, lalu ajukan kembali.',
        };
        $eventId = (string) Str::uuid();

        foreach ($recipients as $recipient) {
            EmailDelivery::enqueue('report:'.$eventId.':'.$recipient->id, $recipient, [
                'kind' => 'report-status',
                'subject' => 'Laporan '.$report->status.' — '.$period.' | SITU-KSG',
                'title' => 'Laporan '.strtolower($report->status),
                'intro' => $intro,
                'unit' => $report->unit->name,
                'unit_id' => $report->unit_id,
                'period' => $period,
                'indicator' => $report->indicator->name,
                'status' => $report->status,
                'note' => $note,
                'action' => $submitted ? 'Tinjau laporan' : 'Lihat pelaporan',
                'url' => route($submitted ? 'verifikasi' : 'realisasi'),
            ]);
        }
    }

    public function remind(CarbonImmutable $date): void
    {
        $date = $date->setTimezone('Asia/Jakarta');
        if (! in_array($date->day, [1, 15, $date->daysInMonth], true)) {
            return;
        }

        $month = $date->startOfMonth()->subMonth();
        $periods = ReportingPeriod::where('year', $month->year)
            ->whereDate('start_date', '<=', $month->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $month->toDateString())->get();

        foreach ($periods as $period) {
            Unit::where('is_active', true)->with(['indicators' => fn ($query) => $query->where('is_active', true)])
                ->each(function (Unit $unit) use ($period, $month, $date): void {
                    $missing = $this->missingIndicators($unit, $period->id, $month->month);
                    if ($missing === []) {
                        return;
                    }

                    DB::transaction(function () use ($unit, $period, $month, $date, $missing): void {
                        $label = Report::monthLabel($month->month).' '.$month->year;
                        foreach (User::role('Pelapor Unit')->where('unit_id', $unit->id)->get() as $recipient) {
                            EmailDelivery::enqueue('reminder:'.$date->toDateString().':'.$period->id.':'.$recipient->id, $recipient, [
                                'kind' => 'reminder',
                                'subject' => 'Pengingat pelaporan '.$label.' | SITU-KSG',
                                'title' => 'Lengkapi laporan '.$label,
                                'intro' => 'Masih ada indikator tim kerja Anda yang belum diajukan atau perlu diperbaiki untuk bulan sebelumnya. Mohon lengkapi dan ajukan laporan melalui SITU-KSG.',
                                'unit' => $unit->name,
                                'unit_id' => $unit->id,
                                'period' => $label,
                                'period_id' => $period->id,
                                'month' => $month->month,
                                'indicators' => $missing,
                                'action' => 'Lengkapi pelaporan',
                                'url' => route('realisasi'),
                            ]);
                        }
                    });
                });
        }
    }

    /** @return array<int, string> */
    public function missingIndicators(Unit $unit, int $periodId, int $month): array
    {
        $completed = Report::where('unit_id', $unit->id)
            ->where('reporting_period_id', $periodId)->where('month', $month)
            ->whereIn('status', ['Diajukan', 'Terverifikasi'])->pluck('indicator_id');

        return $unit->indicators->where('is_active', true)->whereNotIn('id', $completed)->pluck('name')->values()->all();
    }
}
