SITU-KSG | Direktorat KSG

Yth. {!! $messageData['recipient_name'] !!},

{!! $messageData['title'] !!}

{!! $messageData['intro'] !!}

@foreach (['Tim kerja' => 'unit', 'Bulan laporan' => 'period', 'Indikator' => 'indicator', 'Status' => 'status'] as $label => $key)
@if (isset($messageData[$key]))
{!! $label !!}: {!! $messageData[$key] !!}
@endif
@endforeach
@if (! empty($messageData['note']))

Catatan perbaikan:
{!! $messageData['note'] !!}
@endif
@if (! empty($messageData['indicators']))

Indikator yang perlu ditindaklanjuti:
@foreach ($messageData['indicators'] as $indicator)
- {!! $indicator !!}
@endforeach
@endif

{!! $messageData['action'] !!}:
{!! $messageData['url'] !!}

@if ($messageData['kind'] === 'invitation')
Tautan ini tidak memiliki batas waktu dan hanya dapat digunakan sekali. Setelah kata sandi dibuat atau undangan baru diterbitkan, tautan ini tidak berlaku. Jangan teruskan email ini kepada orang lain.
@else
Masuk ke aplikasi untuk melihat detail. Akses tetap mengikuti peran dan tim kerja Anda.
@endif

Email otomatis dari SITU-KSG. Jika Anda tidak mengenali aktivitas ini, hubungi administrator aplikasi. Jangan membalas email ini.
