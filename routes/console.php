<?php

use App\Jobs\SendEmailDelivery;
use App\Models\EmailDelivery;
use App\Services\ReportEmails;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:remind', function () {
    app(ReportEmails::class)->remind(CarbonImmutable::now('Asia/Jakarta'));
    $this->info('Pemeriksaan pengingat pelaporan selesai.');
})->purpose('Antrekan pengingat bulan sebelumnya pada tanggal 1, 15, dan akhir bulan');

Schedule::command('reports:remind')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping();

Artisan::command('emails:retry', function () {
    EmailDelivery::whereNull('sent_at')->whereNull('cancelled_at')->eachById(function (EmailDelivery $delivery): void {
        SendEmailDelivery::dispatch($delivery->id)->afterCommit();
    });
    $this->info('Email yang belum terkirim telah diantrikan kembali.');
})->purpose('Antrekan ulang outbox setelah gangguan antrean atau SMTP');
