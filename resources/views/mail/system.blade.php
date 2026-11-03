<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $messageData['subject'] }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f3;color:#20362d;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;">{{ $messageData['title'] }} — Direktorat KSG</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f3;">
        <tr><td align="center" style="padding:32px 12px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background-color:#ffffff;">
                <tr><td style="padding:28px 32px;background-color:#163e32;border-bottom:5px solid #c6dfca;">
                    <p style="margin:0;color:#ffffff;font-size:24px;font-weight:bold;letter-spacing:2px;">SITU-KSG</p>
                    <p style="margin:8px 0 0;color:#c6dfca;font-size:12px;letter-spacing:1px;">DIREKTORAT KSG · PELAPORAN INTERNAL</p>
                </td></tr>
                <tr><td style="padding:32px;">
                    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">Yth. {{ $messageData['recipient_name'] }},</p>
                    <h1 style="margin:0 0 18px;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:1.25;font-weight:normal;color:#163e32;">{{ $messageData['title'] }}</h1>
                    <p style="margin:0 0 24px;font-size:15px;line-height:1.7;">{{ $messageData['intro'] }}</p>

                    @if (isset($messageData['period']))
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:24px;background-color:#f1f5f3;border-left:3px solid #163e32;">
                            @foreach (['Tim kerja' => 'unit', 'Bulan laporan' => 'period', 'Indikator' => 'indicator', 'Status' => 'status'] as $label => $key)
                                @if (isset($messageData[$key]))
                                    <tr><td style="padding:10px 16px 0;color:#52695e;font-size:12px;">{{ $label }}</td></tr>
                                    <tr><td style="padding:4px 16px 12px;font-size:14px;font-weight:bold;line-height:1.5;">{{ $messageData[$key] }}</td></tr>
                                @endif
                            @endforeach
                        </table>
                    @endif

                    @if (! empty($messageData['note']))
                        <h2 style="margin:0 0 8px;font-size:15px;">Catatan perbaikan</h2>
                        <p style="margin:0 0 24px;font-size:14px;line-height:1.7;white-space:pre-line;">{{ $messageData['note'] }}</p>
                    @endif

                    @if (! empty($messageData['indicators']))
                        <h2 style="margin:0 0 8px;font-size:15px;">Indikator yang perlu ditindaklanjuti</h2>
                        <ul style="margin:0 0 24px;padding-left:20px;font-size:14px;line-height:1.8;">
                            @foreach ($messageData['indicators'] as $indicator)
                                <li>{{ $indicator }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#163e32" style="border-radius:4px;">
                        <a href="{{ $messageData['url'] }}" style="display:inline-block;padding:15px 24px;border:1px solid #163e32;border-radius:4px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;">{{ $messageData['action'] }}</a>
                    </td></tr></table>

                    @if ($messageData['kind'] === 'invitation')
                        <p style="margin:24px 0 0;font-size:13px;line-height:1.7;color:#52695e;">Tautan ini tidak memiliki batas waktu dan hanya dapat digunakan sekali. Setelah kata sandi dibuat atau undangan baru diterbitkan, tautan ini tidak berlaku. Jangan teruskan email ini kepada orang lain.</p>
                    @else
                        <p style="margin:24px 0 0;font-size:13px;line-height:1.7;color:#52695e;">Masuk ke aplikasi untuk melihat detail. Akses tetap mengikuti peran dan tim kerja Anda.</p>
                    @endif
                    <p style="margin:24px 0 6px;font-size:12px;color:#52695e;">Jika tombol tidak dapat dibuka, salin tautan berikut:</p>
                    <p style="margin:0;font-size:12px;line-height:1.6;word-break:break-all;"><a href="{{ $messageData['url'] }}" style="color:#163e32;">{{ $messageData['url'] }}</a></p>
                </td></tr>
                <tr><td style="padding:20px 32px;border-top:1px solid #dce7df;font-size:12px;line-height:1.7;color:#52695e;">
                    Email otomatis dari SITU-KSG · Direktorat KSG.<br>
                    Jika Anda tidak mengenali aktivitas ini, hubungi administrator aplikasi. Jangan membalas email ini.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
