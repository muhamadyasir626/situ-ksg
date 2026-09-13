<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ringkasan Kinerja</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 2px; color: #123141; }
        p.subtitle { margin-top: 0; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        th { background-color: #123141; color: #ffffff; }
        tr:nth-child(even) td { background-color: #f3f4f6; }
        .muted { color: #6b7280; font-size: 10px; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Ringkasan Kinerja</h1>
    <p class="subtitle">Periode: {{ $period?->label ?? 'Semua periode' }}</p>

    <table>
        <thead>
            <tr>
                <th>Indikator</th>
                <th>Kelompok</th>
                <th>Unit</th>
                <th>Target</th>
                <th>Capaian</th>
                <th>Realisasi Capaian</th>
                <th>Keterangan Proses</th>
                <th>Tindak Lanjut</th>
                <th>Realisasi Anggaran</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td>{{ $report->indicator->name }}</td>
                    <td>{{ $report->indicator->group->code }}</td>
                    <td>{{ $report->unit->name }}</td>
                    <td>{{ $report->indicator->target }} {{ $report->indicator->unit }}</td>
                    <td>{{ $report->target_achievement ?? '—' }}</td>
                    <td>{{ $report->realization_achievement ?? '—' }}</td>
                    <td>{{ $report->process_note ?? '—' }}</td>
                    <td>{{ $report->follow_up ?? '—' }}</td>
                    <td>{{ $report->indicator->group->code === 'RO' && $report->budget_realization !== null ? 'Rp '.number_format((float) $report->budget_realization, 2, ',', '.') : '—' }}</td>
                    <td>{{ $report->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Tidak ada laporan terverifikasi pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted">Dibuat pada {{ now()->translatedFormat('d F Y H:i') }}.</p>
</body>
</html>
