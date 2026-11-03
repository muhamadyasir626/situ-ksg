<?php

namespace App\Jobs;

use App\Mail\SystemEmail;
use App\Models\EmailDelivery;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportEmails;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendEmailDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        // Keep the row locked until SMTP completes: duplicate jobs cannot send concurrently.
        DB::transaction(function (): void {
            $delivery = EmailDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $delivery || $delivery->sent_at || $delivery->cancelled_at) {
                return;
            }

            $user = User::find($delivery->user_id);
            $content = $delivery->content;
            $staleInvitation = ($content['kind'] === 'invitation')
                && (! $user?->invitation_token_hash || ! hash_equals($user->invitation_token_hash, $content['token_hash']));

            $eligible = $user && $user->email === $content['recipient_email'] && ! $staleInvitation;
            if ($eligible && $content['kind'] === 'report-status') {
                $eligible = $content['status'] === 'Diajukan'
                    ? $user->hasRole('Verifikator')
                    : $user->hasRole('Pelapor Unit') && $user->unit_id === $content['unit_id'];
            }
            if ($eligible && $content['kind'] === 'reminder') {
                $unit = Unit::find($content['unit_id']);
                $eligible = $user->hasRole('Pelapor Unit') && $user->unit_id === $content['unit_id'] && $unit?->is_active;
                if ($eligible) {
                    $content['indicators'] = app(ReportEmails::class)->missingIndicators($unit, $content['period_id'], $content['month']);
                    $eligible = $content['indicators'] !== [];
                }
            }

            if (! $eligible) {
                $delivery->update(['cancelled_at' => now()]);

                return;
            }

            Mail::to($content['recipient_email'])->send(new SystemEmail($content));
            $delivery->update(['sent_at' => now()]);
        });
    }
}
