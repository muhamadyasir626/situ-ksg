<?php

namespace Tests\Feature;

use App\Actions\AccountInvitation;
use App\Jobs\SendEmailDelivery;
use App\Mail\SystemEmail;
use App\Models\EmailDelivery;
use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportEmails;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Queue::fake();
        Mail::fake();
    }

    private function user(string $role, ?Unit $unit = null): User
    {
        $user = User::factory()->create(['unit_id' => $unit?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function invitationUrl(User $user): string
    {
        return EmailDelivery::where('user_id', $user->id)->latest('id')->firstOrFail()->content['url'];
    }

    public function test_admin_creates_account_without_password_and_records_encrypted_invitation(): void
    {
        $this->actingAs($this->user('Admin'));
        Livewire::test('admin.manage-users')->call('createUser')
            ->set('name', 'Pelapor Baru')->set('email', 'baru@example.test')
            ->set('roleId', Role::findByName('Verifikator')->id)
            ->call('save')->assertHasNoErrors();

        $user = User::where('email', 'baru@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Verifikator'));
        $this->assertNotNull($user->invitation_token_hash);
        $this->assertArrayNotHasKey('invitation_token_hash', $user->toArray());
        $delivery = EmailDelivery::firstOrFail();
        $this->assertSame($user->id, $delivery->user_id);
        $this->assertStringNotContainsString($delivery->content['url'], $delivery->getRawOriginal('content'));
        $this->assertStringNotContainsString('password', json_encode($delivery->content));
        Queue::assertPushed(SendEmailDelivery::class, fn ($job) => $job->deliveryId === $delivery->id && $job->afterCommit === true);
    }

    public function test_invitation_has_no_time_limit_and_is_only_consumed_by_successful_post(): void
    {
        $user = $this->user('Pelapor Unit', Unit::factory()->create());
        app(AccountInvitation::class)->send($user);
        $url = $this->invitationUrl($user);
        $this->travel(800)->days();
        $this->get($url)->assertOk()->assertSee('Buat kata sandi');
        $this->post($url, ['password' => 'short', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');
        $this->get($url)->assertOk();
        $this->post($url, ['password' => 'Password-aman-123!', 'password_confirmation' => 'Password-aman-123!'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Password-aman-123!', $user->fresh()->password));
        $this->assertNull($user->fresh()->invitation_token_hash);
        $this->get($url)->assertNotFound();
        $this->post($url, ['password' => 'Password-lain-123!', 'password_confirmation' => 'Password-lain-123!'])->assertNotFound();
    }

    public function test_reissuing_invitation_revokes_old_link_and_any_password_change_revokes_new_link(): void
    {
        $user = $this->user('Verifikator');
        app(AccountInvitation::class)->send($user);
        $oldUrl = $this->invitationUrl($user);
        $this->actingAs($this->user('Admin'));
        Livewire::test('admin.manage-users')->call('resendInvitation', $user->id)->assertHasNoErrors();
        $newUrl = $this->invitationUrl($user);
        $this->assertNotSame($oldUrl, $newUrl);
        $this->get($oldUrl)->assertNotFound();
        $this->get($newUrl)->assertOk();
        $user->refresh()->update(['password' => 'Password-baru-123!']);
        $this->get($newUrl)->assertNotFound();
    }

    public function test_non_admin_cannot_resend_invitation(): void
    {
        $user = $this->user('Verifikator');
        app(AccountInvitation::class)->send($user);
        $admin = $this->user('Admin');
        $this->actingAs($admin);
        $component = Livewire::test('admin.manage-users');
        $admin->syncRoles(['Auditor']);
        $component->call('resendInvitation', $user->id)->assertForbidden();
    }

    public function test_outbox_sends_html_and_text_only_once(): void
    {
        $user = $this->user('Verifikator');
        app(AccountInvitation::class)->send($user);
        $delivery = EmailDelivery::firstOrFail();
        $job = new SendEmailDelivery($delivery->id);
        $job->handle();
        $job->handle();
        Mail::assertSent(SystemEmail::class, 1);
        $this->assertNotNull($delivery->fresh()->sent_at);
        Mail::assertSent(SystemEmail::class, function (SystemEmail $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('SITU-KSG', $html);
            $this->assertStringContainsString('Buat kata sandi', $html);
            $this->assertStringContainsString('Buat kata sandi', view('mail.system-text', $mail->content()->with)->render());

            return true;
        });
    }

    public function test_stale_invitation_is_not_sent_after_password_is_set(): void
    {
        $user = $this->user('Verifikator');
        app(AccountInvitation::class)->send($user);
        $user->update(['password' => 'Password-baru-123!']);
        (new SendEmailDelivery(EmailDelivery::firstOrFail()->id))->handle();
        Mail::assertNothingSent();
    }

    public function test_outbox_and_invitation_are_rolled_back_together(): void
    {
        $user = $this->user('Verifikator');
        DB::beginTransaction();
        try {
            app(AccountInvitation::class)->send($user);
        } finally {
            DB::rollBack();
        }
        $this->assertNull($user->fresh()->invitation_token_hash);
        $this->assertSame(0, EmailDelivery::count());
    }

    public function test_status_emails_snapshot_recipients_and_return_note(): void
    {
        $unit = Unit::factory()->create();
        $reporter = $this->user('Pelapor Unit', $unit);
        $other = $this->user('Pelapor Unit', Unit::factory()->create());
        $verifier = $this->user('Verifikator');
        $report = Report::factory()->create(['unit_id' => $unit->id, 'status' => 'Diajukan', 'month' => 10]);
        app(ReportEmails::class)->statusChanged($report);
        $this->assertSame([$verifier->id], EmailDelivery::pluck('user_id')->all());
        $this->assertSame('Diajukan', EmailDelivery::firstOrFail()->content['status']);

        $this->actingAs($verifier);
        Livewire::test('pages.verifikasi-list')->call('openReturnModal', $report->id)
            ->set('returnNote', 'Mohon perbaiki angka <script>contoh</script>.')
            ->call('returnReport')->assertHasNoErrors();
        $returned = EmailDelivery::latest('id')->firstOrFail();
        $this->assertSame($reporter->id, $returned->user_id);
        $this->assertSame('Dikembalikan', $returned->content['status']);
        $this->assertStringContainsString('Mohon perbaiki angka', $returned->content['note']);
        $this->assertStringNotContainsString('<script>', (new SystemEmail($returned->content))->render());
        $this->assertSame(0, EmailDelivery::where('user_id', $other->id)->count());

        $report->refresh()->update(['status' => 'Diajukan']);
        Livewire::test('pages.verifikasi-list')->call('approve', $report->id)->assertHasNoErrors();
        $this->assertSame('Terverifikasi', EmailDelivery::latest('id')->firstOrFail()->content['status']);
        $count = EmailDelivery::count();
        Livewire::test('pages.verifikasi-list')->call('approve', $report->id);
        $this->assertSame($count, EmailDelivery::count());
    }

    public function test_reminders_use_previous_month_old_year_and_are_deduplicated_per_schedule(): void
    {
        $period = ReportingPeriod::factory()->create(['year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_active' => false]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $reporter = $this->user('Pelapor Unit', $unit);
        $indicator = Indicator::factory()->create(['is_active' => true]);
        $indicator->units()->attach($unit);
        $this->user('Pimpinan', $unit);
        $this->user('Pelapor Unit', Unit::factory()->create());
        foreach (['2026-01-01', '2026-01-15', '2026-01-31'] as $date) {
            app(ReportEmails::class)->remind(CarbonImmutable::parse($date, 'Asia/Jakarta'));
            app(ReportEmails::class)->remind(CarbonImmutable::parse($date, 'Asia/Jakarta'));
        }
        $this->assertSame(3, EmailDelivery::count());
        $this->assertSame([$reporter->id], EmailDelivery::pluck('user_id')->unique()->values()->all());
        $this->assertStringContainsString('Desember 2025', EmailDelivery::firstOrFail()->content['period']);
        app(ReportEmails::class)->remind(CarbonImmutable::parse('2026-01-10', 'Asia/Jakarta'));
        $this->assertSame(3, EmailDelivery::count());
        Report::factory()->create(['unit_id' => $unit->id, 'indicator_id' => $indicator->id, 'reporting_period_id' => $period->id, 'month' => 12, 'status' => 'Diajukan']);
        EmailDelivery::query()->delete();
        app(ReportEmails::class)->remind(CarbonImmutable::parse('2026-01-31', 'Asia/Jakarta'));
        $this->assertSame(0, EmailDelivery::count());
    }

    public function test_reminders_include_drafts_and_returns_but_exclude_completed_and_inactive_assignments(): void
    {
        $period = ReportingPeriod::factory()->create(['year' => 2028, 'start_date' => '2028-01-01', 'end_date' => '2028-12-31']);
        $unit = Unit::factory()->create(['is_active' => true]);
        $this->user('Pelapor Unit', $unit);
        foreach (['Draft', 'Dikembalikan', 'Diajukan', 'Terverifikasi'] as $status) {
            $indicator = Indicator::factory()->create(['name' => "Indikator $status", 'is_active' => true]);
            $indicator->units()->attach($unit);
            Report::factory()->create(['unit_id' => $unit->id, 'indicator_id' => $indicator->id, 'reporting_period_id' => $period->id, 'month' => 1, 'status' => $status]);
        }
        $inactive = Indicator::factory()->create(['is_active' => false]);
        $inactive->units()->attach($unit);
        app(ReportEmails::class)->remind(CarbonImmutable::parse('2028-02-29', 'Asia/Jakarta'));
        $delivery = EmailDelivery::firstOrFail();
        $this->assertSame(['Indikator Draft', 'Indikator Dikembalikan'], $delivery->content['indicators']);
        $unit->update(['is_active' => false]);
        EmailDelivery::query()->delete();
        app(ReportEmails::class)->remind(CarbonImmutable::parse('2028-02-29', 'Asia/Jakarta'));
        $this->assertSame(0, EmailDelivery::count());
    }

    public function test_submit_queues_email_but_draft_and_duplicate_submit_do_not(): void
    {
        $period = ReportingPeriod::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create();
        $this->actingAs($this->user('Pelapor Unit', $unit));
        $verifier = $this->user('Verifikator');
        $group = IndicatorGroup::factory()->create(['code' => 'IKK']);
        $indicator = Indicator::factory()->for($group, 'group')->create(['is_active' => true]);
        $indicator->units()->attach($unit);
        $component = Livewire::test('pages.report-form')
            ->set('selectedGroupId', $group->id)->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 10)->set('targetAchievement', '5')
            ->set('realizationAchievement', '5')->set('progressNarrative', 'Selesai')->set('problemNarrative', '-')
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame(0, EmailDelivery::count());
        $component->call('submit')->assertHasNoErrors();
        $this->assertSame([$verifier->id], EmailDelivery::pluck('user_id')->all());
        $component->call('submit')->assertForbidden();
        $this->assertSame(1, EmailDelivery::count());
        $this->assertSame('Diajukan', Report::where('reporting_period_id', $period->id)->firstOrFail()->status);
    }

    public function test_smtp_failure_leaves_outbox_pending_and_retry_can_send(): void
    {
        app(AccountInvitation::class)->send($this->user('Verifikator'));
        $delivery = EmailDelivery::firstOrFail();
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));
        try {
            (new SendEmailDelivery($delivery->id))->handle();
            $this->fail('SMTP failure must propagate to the queue retry handler.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }
        $this->assertNull($delivery->fresh()->sent_at);
        Mail::swap($mailFake);
        $this->artisan('emails:retry')->assertSuccessful();
        Queue::assertPushed(SendEmailDelivery::class, fn ($job) => $job->deliveryId === $delivery->id);
        (new SendEmailDelivery($delivery->id))->handle();
        Mail::assertSent(SystemEmail::class, 1);
    }

    public function test_delayed_reminder_is_cancelled_if_team_has_completed_reporting(): void
    {
        $period = ReportingPeriod::factory()->create(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $unit = Unit::factory()->create(['is_active' => true]);
        $this->user('Pelapor Unit', $unit);
        $indicator = Indicator::factory()->create(['is_active' => true]);
        $indicator->units()->attach($unit);
        app(ReportEmails::class)->remind(CarbonImmutable::parse('2026-11-01', 'Asia/Jakarta'));
        $delivery = EmailDelivery::firstOrFail();
        Report::factory()->create(['unit_id' => $unit->id, 'indicator_id' => $indicator->id, 'reporting_period_id' => $period->id, 'month' => 10, 'status' => 'Terverifikasi']);
        (new SendEmailDelivery($delivery->id))->handle();
        Mail::assertNothingSent();
        $this->assertNotNull($delivery->fresh()->cancelled_at);
    }

    public function test_delayed_status_email_is_not_sent_to_user_who_lost_role(): void
    {
        $verifier = $this->user('Verifikator');
        $report = Report::factory()->create(['status' => 'Diajukan']);
        app(ReportEmails::class)->statusChanged($report);
        $verifier->syncRoles(['Auditor']);
        (new SendEmailDelivery(EmailDelivery::firstOrFail()->id))->handle();
        Mail::assertNothingSent();
    }

    public function test_replaced_invitation_is_not_delivered_from_old_queue_job(): void
    {
        $user = $this->user('Verifikator');
        app(AccountInvitation::class)->send($user);
        $old = EmailDelivery::firstOrFail();
        app(AccountInvitation::class)->send($user);
        (new SendEmailDelivery($old->id))->handle();
        Mail::assertNothingSent();
        $this->assertNotNull($old->fresh()->cancelled_at);
    }

    public function test_verification_and_outbox_roll_back_if_email_cannot_be_recorded(): void
    {
        $unit = Unit::factory()->create();
        $this->user('Pelapor Unit', $unit);
        $this->actingAs($this->user('Verifikator'));
        $report = Report::factory()->create(['unit_id' => $unit->id, 'status' => 'Diajukan']);
        EmailDelivery::creating(function (): void {
            throw new RuntimeException('Outbox unavailable');
        });
        try {
            Livewire::test('pages.verifikasi-list')->call('approve', $report->id);
            $this->fail('The transition must not commit without recording its email.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Outbox unavailable', $exception->getMessage());
        }
        $this->assertSame('Diajukan', $report->fresh()->status);
        $this->assertSame(0, $report->verificationNotes()->count());
        $this->assertSame(0, EmailDelivery::count());
    }

    public function test_password_change_on_stale_user_also_invalidates_persisted_invitation(): void
    {
        $user = $this->user('Verifikator')->fresh();
        $this->assertNull($user->getRawOriginal('invitation_token_hash'));
        app(AccountInvitation::class)->send($user);
        $url = $this->invitationUrl($user);
        $user->update(['password' => 'Password-baru-123!']);
        $this->assertNull($user->fresh()->invitation_token_hash);
        $this->get($url)->assertNotFound();
    }

    public function test_admin_resubmitting_already_submitted_report_does_not_send_another_email(): void
    {
        $unit = Unit::factory()->create();
        $this->actingAs($this->user('Admin', $unit));
        $this->user('Verifikator');
        $group = IndicatorGroup::factory()->create(['code' => 'IKK']);
        $indicator = Indicator::factory()->for($group, 'group')->create(['is_active' => true]);
        $report = Report::factory()->create([
            'unit_id' => $unit->id, 'indicator_id' => $indicator->id, 'status' => 'Diajukan',
            'target_achievement' => '5', 'realization_achievement' => '5',
            'progress_narrative' => 'Selesai', 'problem_narrative' => '-',
        ]);
        Livewire::test('pages.report-form', ['report' => $report->id])->call('submit')->assertHasNoErrors();
        $this->assertSame(0, EmailDelivery::count());
    }
}
