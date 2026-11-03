<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alur lengkap pelaporan → verifikasi (FR-20..32, BR-01..07, BR-16..17).
 */
class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected ReportingPeriod $period;

    protected Unit $unitA;

    protected Unit $unitB;

    protected User $pelaporA;

    protected User $pelaporB;

    protected User $verifikator;

    protected User $admin;

    /** @var array<string, Indicator> */
    protected array $indicators = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $this->period = ReportingPeriod::factory()->create(['is_active' => true, 'year' => 2026, 'label' => 'TA 2026']);
        $this->unitA = Unit::factory()->create(['code' => 'ALF', 'name' => 'Unit Alfa']);
        $this->unitB = Unit::factory()->create(['code' => 'BET', 'name' => 'Unit Beta']);

        $this->pelaporA = $this->user('Pelapor Unit', $this->unitA);
        $this->pelaporB = $this->user('Pelapor Unit', $this->unitB);
        $this->verifikator = $this->user('Verifikator');
        $this->admin = $this->user('Admin', $this->unitA);

        foreach (['IKU', 'IKP', 'IKK', 'RO'] as $i => $code) {
            $group = IndicatorGroup::factory()->create(['code' => $code, 'name' => "Kelompok $code", 'sort_order' => $i]);
            $indicator = Indicator::factory()->for($group, 'group')->create(['name' => "Indikator $code", 'target' => 10, 'is_active' => true]);
            $indicator->units()->attach([$this->unitA->id, $this->unitB->id]);
            $this->indicators[$code] = $indicator;
        }
    }

    protected function user(string $role, ?Unit $unit = null): User
    {
        $user = User::factory()->create(['unit_id' => $unit?->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function report(Unit $unit, string $code, string $status = 'Draft', int $month = 1): Report
    {
        return Report::factory()->create([
            'indicator_id' => $this->indicators[$code]->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $this->period->id,
            'month' => $month,
            'quarter' => (int) ceil($month / 3),
            'status' => $status,
            'target_achievement' => '5 Spesies',
            'process_note' => 'Catatan proses uji',
            'achievement_strategy' => 'Strategi uji',
            'follow_up' => 'Tindak lanjut uji',
            'realization_achievement' => 'Realisasi uji',
            'progress_narrative' => 'Narasi progres uji',
            'problem_narrative' => 'Kendala uji',
            'submitted_at' => $status === 'Diajukan' ? now() : null,
        ]);
    }

    protected function fillValid($component, string $code)
    {
        $component->set('selectedGroupId', $this->indicators[$code]->indicator_group_id)
            ->set('selectedIndicatorId', $this->indicators[$code]->id)
            ->set('selectedMonth', 2)
            ->set('targetAchievement', '4 Spesies')
            ->set('problemNarrative', '-');

        return match ($code) {
            'IKU' => $component->set('processNote', 'Proses')->set('achievementStrategy', 'Strategi'),
            'IKP' => $component->set('processNote', 'Proses')->set('followUp', 'Tindak'),
            'IKK' => $component->set('realizationAchievement', '3')->set('progressNarrative', 'Narasi'),
            'RO' => $component->set('budgetRealization', '1500000')->set('progressNarrative', 'Narasi'),
        };
    }

    // ---- Input pelaporan -------------------------------------------------

    public function test_each_group_can_be_submitted_with_its_required_fields(): void
    {
        foreach (['IKU', 'IKP', 'IKK', 'RO'] as $code) {
            $this->actingAs($this->pelaporA);
            $this->fillValid(Livewire::test('pages.report-form'), $code)
                ->call('submit')
                ->assertHasNoErrors()
                ->assertRedirect(route('realisasi'));

            $report = Report::where('indicator_id', $this->indicators[$code]->id)->where('unit_id', $this->unitA->id)->firstOrFail();
            $this->assertSame('Diajukan', $report->status);
            $this->assertNotNull($report->submitted_at);
            $this->assertSame(2, (int) $report->month);
            $this->assertSame(1, (int) $report->quarter, 'BR-03 quarter = ceil(month/3)');
            $this->assertSame($this->period->id, $report->reporting_period_id);
        }
    }

    public function test_submit_rejects_negative_budget_and_non_numeric_budget(): void
    {
        $this->actingAs($this->pelaporA);

        $this->fillValid(Livewire::test('pages.report-form'), 'RO')
            ->set('budgetRealization', '-5')
            ->call('submit')
            ->assertHasErrors(['budgetRealization' => 'min']);

        $this->fillValid(Livewire::test('pages.report-form'), 'RO')
            ->set('budgetRealization', 'abc')
            ->call('submit')
            ->assertHasErrors(['budgetRealization' => 'numeric']);
    }

    public function test_month_boundaries_are_validated(): void
    {
        $this->actingAs($this->pelaporA);

        foreach ([0, 13] as $month) {
            $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
                ->set('selectedMonth', $month)
                ->call('saveDraft')
                ->assertHasErrors(['selectedMonth']);
        }

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->set('selectedMonth', 12)
            ->call('saveDraft')
            ->assertHasNoErrors();
        $this->assertSame(4, (int) Report::first()->quarter);
    }

    public function test_narrative_max_length_is_enforced(): void
    {
        $this->actingAs($this->pelaporA);

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->set('problemNarrative', str_repeat('a', 5001))
            ->call('saveDraft')
            ->assertHasErrors(['problemNarrative' => 'max']);
    }

    public function test_duplicate_report_for_same_unit_indicator_period_month_is_rejected_with_friendly_message(): void
    {
        $this->report($this->unitA, 'IKU', 'Draft', 2);
        $this->actingAs($this->pelaporA);

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->call('saveDraft')
            ->assertHasErrors(['selectedIndicatorId' => 'unique'])
            ->assertSee('Sudah ada laporan');

        // Unit lain untuk indikator & bulan yang sama tetap boleh (BR-02 per unit).
        $this->actingAs($this->pelaporB);
        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->call('saveDraft')
            ->assertHasNoErrors();
    }

    public function test_pelapor_cannot_report_indicator_not_assigned_to_unit(): void
    {
        $other = Indicator::factory()->for($this->indicators['IKU']->group, 'group')->create(['name' => 'Tidak ditugaskan', 'is_active' => true]);
        $this->actingAs($this->pelaporA);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $other->indicator_group_id)
            ->assertDontSee('Tidak ditugaskan')
            ->set('selectedIndicatorId', $other->id)
            ->set('targetAchievement', '1')
            ->call('saveDraft')
            ->assertHasErrors(['selectedIndicatorId']);

        $this->assertSame(0, Report::count());
    }

    public function test_inactive_indicator_is_not_offered(): void
    {
        $this->indicators['IKU']->update(['is_active' => false]);
        $this->actingAs($this->pelaporA);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->indicators['IKU']->indicator_group_id)
            ->assertDontSee('Indikator IKU');
    }

    public function test_changing_group_resets_indicator(): void
    {
        $this->actingAs($this->pelaporA);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->indicators['IKU']->indicator_group_id)
            ->set('selectedIndicatorId', $this->indicators['IKU']->id)
            ->set('selectedGroupId', $this->indicators['RO']->indicator_group_id)
            ->assertSet('selectedIndicatorId', null);
    }

    public function test_ro_form_shows_period_pagu(): void
    {
        $this->indicators['RO']->periodSettings()->create([
            'reporting_period_id' => $this->period->id, 'target' => 10, 'unit' => 'Data', 'pagu_amount' => 2500000,
        ]);
        $this->actingAs($this->pelaporA);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->indicators['RO']->indicator_group_id)
            ->set('selectedIndicatorId', $this->indicators['RO']->id)
            ->assertSee('Rp 2.500.000,00');
    }

    public function test_reporting_period_is_locked_to_active_period(): void
    {
        $old = ReportingPeriod::factory()->create(['is_active' => false, 'year' => 2025, 'label' => 'TA 2025']);
        $this->actingAs($this->pelaporA);

        try {
            $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
                ->set('reportingPeriodId', $old->id)
                ->call('saveDraft');
        } catch (\Throwable) {
            // Locked property tampering throws — expected.
        }

        $this->assertSame(0, Report::where('reporting_period_id', $old->id)->count(), 'BR-01: input selalu ke periode aktif');
    }

    public function test_editing_report_id_cannot_be_tampered_to_hijack_other_unit_report(): void
    {
        $victim = $this->report($this->unitB, 'IKU', 'Draft', 3);
        $this->actingAs($this->pelaporA);

        try {
            $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
                ->set('editingReportId', $victim->id)
                ->call('saveDraft');
        } catch (\Throwable) {
            // Locked property tampering throws — expected.
        }

        $victim->refresh();
        $this->assertSame($this->unitB->id, $victim->unit_id);
        $this->assertSame('5 Spesies', $victim->target_achievement);
    }

    public function test_admin_editing_other_unit_report_keeps_original_unit(): void
    {
        $report = $this->report($this->unitB, 'IKU', 'Draft', 3);
        $this->actingAs($this->admin);

        Livewire::test('pages.report-form', ['report' => $report->id])
            ->set('targetAchievement', '9 Spesies')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $report->refresh();
        $this->assertSame($this->unitB->id, $report->unit_id, 'Edit oleh Admin tidak boleh memindahkan laporan ke unit Admin');
        $this->assertSame('9 Spesies', $report->target_achievement);
    }

    public function test_admin_without_unit_gets_validation_error_instead_of_crash(): void
    {
        $admin = $this->user('Admin');
        $this->actingAs($admin);

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->call('saveDraft')
            ->assertHasErrors();

        $this->assertSame(0, Report::count());
    }

    public function test_returned_report_resubmission_creates_revision_snapshot(): void
    {
        $report = $this->report($this->unitA, 'IKU', 'Dikembalikan');
        $this->actingAs($this->pelaporA);

        Livewire::test('pages.report-form', ['report' => $report->id])
            ->set('targetAchievement', '7 Spesies')
            ->call('submit')
            ->assertHasNoErrors();

        $report->refresh();
        $this->assertSame('Diajukan', $report->status);
        $this->assertSame(1, $report->revisions()->count());
        $this->assertSame('5 Spesies', $report->revisions()->first()->payload['target_achievement']);
    }

    public function test_attachment_upload_validation(): void
    {
        $this->actingAs($this->pelaporA);

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->set('attachments', [UploadedFile::fake()->create('virus.exe', 10)])
            ->call('saveDraft')
            ->assertHasErrors(['attachments.0' => 'mimes']);

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->set('attachments', [UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasErrors(['attachments.0' => 'max']);

        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')
            ->set('attachments', [UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $attachment = ReportAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('bukti.pdf', $attachment->original_name);
    }

    public function test_attachment_download_is_isolated_per_unit(): void
    {
        $report = $this->report($this->unitA, 'IKU');
        Storage::disk('local')->put('attachments/a.pdf', 'x');
        $attachment = ReportAttachment::factory()->create(['report_id' => $report->id, 'path' => 'attachments/a.pdf', 'original_name' => 'a.pdf']);

        $this->actingAs($this->pelaporA)->get(route('realisasi.attachments.download', $attachment))->assertOk();
        $this->actingAs($this->pelaporB)->get(route('realisasi.attachments.download', $attachment))->assertForbidden();
        $this->actingAs($this->pelaporB)->get(route('realisasi.attachments.preview', $attachment))->assertForbidden();
        $this->actingAs($this->verifikator)->get(route('realisasi.attachments.preview', $attachment))->assertOk();

        Storage::disk('local')->delete('attachments/a.pdf');
        $this->actingAs($this->pelaporA)->get(route('realisasi.attachments.download', $attachment))->assertNotFound();
    }

    public function test_cannot_delete_attachment_of_report_no_longer_editable(): void
    {
        $report = $this->report($this->unitA, 'IKU');
        Storage::disk('local')->put('attachments/a.pdf', 'x');
        $attachment = ReportAttachment::factory()->create(['report_id' => $report->id, 'path' => 'attachments/a.pdf']);

        $this->actingAs($this->pelaporA);
        $component = Livewire::test('pages.report-form', ['report' => $report->id]);

        $report->update(['status' => 'Diajukan']);

        $component->call('deleteAttachment', $attachment->id)->assertForbidden();
        $this->assertDatabaseHas('report_attachments', ['id' => $attachment->id]);
    }

    public function test_draft_delete_permissions(): void
    {
        $draft = $this->report($this->unitA, 'IKU', 'Draft');
        $submitted = $this->report($this->unitA, 'IKP', 'Diajukan');
        $otherUnit = $this->report($this->unitB, 'IKU', 'Draft');

        $this->actingAs($this->pelaporA);
        Livewire::test('pages.realisasi-list')->call('delete', $submitted->id)->assertForbidden();
        Livewire::test('pages.realisasi-list')->call('delete', $otherUnit->id)->assertForbidden();
        Livewire::test('pages.realisasi-list')->call('delete', $draft->id)->assertHasNoErrors();

        $this->assertModelMissing($draft);
        $this->assertModelExists($submitted);
        $this->assertModelExists($otherUnit);
    }

    public function test_realisasi_status_filter(): void
    {
        $this->report($this->unitA, 'IKU', 'Draft');
        $this->report($this->unitA, 'IKP', 'Diajukan');
        $this->actingAs($this->pelaporA);

        Livewire::test('pages.realisasi-list')
            ->set('filterStatus', 'Diajukan')
            ->assertSee('Indikator IKP')
            ->assertDontSee('Indikator IKU');
    }

    // ---- Verifikasi -------------------------------------------------------

    public function test_verification_list_only_shows_submitted_reports(): void
    {
        $this->report($this->unitA, 'IKU', 'Draft');
        $this->report($this->unitA, 'IKP', 'Diajukan');
        $this->report($this->unitB, 'IKK', 'Terverifikasi');

        $this->actingAs($this->verifikator);
        Livewire::test('pages.verifikasi-list')
            ->assertSee('Indikator IKP')
            ->assertDontSee('Indikator IKU')
            ->assertDontSee('Indikator IKK');
    }

    public function test_verification_detail_shows_group_specific_fields_and_attachment_links(): void
    {
        $iku = $this->report($this->unitA, 'IKU', 'Diajukan');
        Storage::disk('local')->put('attachments/b.pdf', 'x');
        $attachment = ReportAttachment::factory()->create(['report_id' => $iku->id, 'path' => 'attachments/b.pdf', 'original_name' => 'bukti-iku.pdf']);

        $this->actingAs($this->verifikator);
        Livewire::test('pages.verifikasi-list')
            ->call('showDetail', $iku->id)
            ->assertSee('Catatan proses uji')
            ->assertSee('Strategi uji')
            ->assertSee('Kendala uji')
            ->assertSee('bukti-iku.pdf')
            ->assertSee(route('realisasi.attachments.download', $attachment), false)
            ->assertDontSee('Tindak lanjut uji');

        $ikp = $this->report($this->unitA, 'IKP', 'Diajukan');
        Livewire::test('pages.verifikasi-list')
            ->call('showDetail', $ikp->id)
            ->assertSee('Tindak lanjut uji')
            ->assertSee('Catatan proses uji');

        $ikk = $this->report($this->unitA, 'IKK', 'Diajukan');
        Livewire::test('pages.verifikasi-list')
            ->call('showDetail', $ikk->id)
            ->assertSee('Realisasi uji')
            ->assertSee('Narasi progres uji');
    }

    public function test_verification_detail_shows_previous_return_notes(): void
    {
        $report = $this->report($this->unitA, 'IKU', 'Diajukan');
        $report->verificationNotes()->create(['verifier_id' => $this->verifikator->id, 'action' => 'Dikembalikan', 'note' => 'Lengkapi bukti dukung']);

        $this->actingAs($this->verifikator);
        Livewire::test('pages.verifikasi-list')
            ->call('showDetail', $report->id)
            ->assertSee('Lengkapi bukti dukung');
    }

    public function test_approve_flow(): void
    {
        $report = $this->report($this->unitA, 'IKU', 'Diajukan');
        $this->actingAs($this->verifikator);

        Livewire::test('pages.verifikasi-list')->call('approve', $report->id)->assertHasNoErrors();

        $report->refresh();
        $this->assertSame('Terverifikasi', $report->status);
        $this->assertSame($this->verifikator->id, $report->verified_by);
        $this->assertNotNull($report->verified_at);
        $this->assertDatabaseHas('verification_notes', ['report_id' => $report->id, 'action' => 'Disetujui']);

        // BR-05: terkunci.
        $this->actingAs($this->pelaporA)->get(route('realisasi.edit', $report))->assertForbidden();
    }

    public function test_return_requires_note_and_moves_to_dikembalikan(): void
    {
        $report = $this->report($this->unitA, 'IKU', 'Diajukan');
        $this->actingAs($this->verifikator);

        Livewire::test('pages.verifikasi-list')
            ->call('openReturnModal', $report->id)
            ->set('returnNote', '')
            ->call('returnReport')
            ->assertHasErrors(['returnNote' => 'required'])
            ->set('returnNote', '  ')
            ->call('returnReport')
            ->assertHasErrors(['returnNote'])
            ->set('returnNote', 'Perbaiki capaian')
            ->call('returnReport')
            ->assertHasNoErrors();

        $report->refresh();
        $this->assertSame('Dikembalikan', $report->status);
        $this->assertDatabaseHas('verification_notes', ['report_id' => $report->id, 'action' => 'Dikembalikan', 'note' => 'Perbaiki capaian']);
    }

    public function test_cannot_approve_or_return_non_submitted_report(): void
    {
        $draft = $this->report($this->unitA, 'IKU', 'Draft');
        $this->actingAs($this->verifikator);

        Livewire::test('pages.verifikasi-list')->call('approve', $draft->id)->assertHasNoErrors();
        Livewire::test('pages.verifikasi-list')
            ->call('openReturnModal', $draft->id)
            ->set('returnNote', 'Catatan')
            ->call('returnReport')
            ->assertHasNoErrors();

        $this->assertSame('Draft', $draft->refresh()->status);
        $this->assertSame(0, $draft->verificationNotes()->count());
    }

    public function test_non_verifier_roles_cannot_call_verification_actions(): void
    {
        $report = $this->report($this->unitA, 'IKU', 'Diajukan');

        foreach ([$this->pelaporA, $this->user('Pimpinan'), $this->user('Auditor')] as $user) {
            $this->actingAs($user);
            Livewire::test('pages.verifikasi-list')->assertForbidden();
        }

        $this->assertSame('Diajukan', $report->refresh()->status);
    }

    public function test_full_cycle_draft_submit_return_fix_resubmit_approve(): void
    {
        $this->actingAs($this->pelaporA);
        $this->fillValid(Livewire::test('pages.report-form'), 'IKU')->set('achievementStrategy', '')->call('saveDraft')->assertHasNoErrors();
        $report = Report::firstOrFail();
        $this->assertSame('Draft', $report->status);

        Livewire::test('pages.report-form', ['report' => $report->id])
            ->call('submit')
            ->assertHasErrors(['achievementStrategy' => 'required'])
            ->set('achievementStrategy', 'Strategi lengkap')
            ->call('submit')
            ->assertHasNoErrors();
        $this->assertSame('Diajukan', $report->refresh()->status);

        $this->actingAs($this->verifikator);
        Livewire::test('pages.verifikasi-list')->call('openReturnModal', $report->id)->set('returnNote', 'Tambah bukti')->call('returnReport');
        $this->assertSame('Dikembalikan', $report->refresh()->status);

        $this->actingAs($this->pelaporA);
        Livewire::test('pages.report-form', ['report' => $report->id])->set('targetAchievement', '6 Spesies')->call('submit')->assertHasNoErrors();
        $this->assertSame('Diajukan', $report->refresh()->status);

        $this->actingAs($this->verifikator);
        Livewire::test('pages.verifikasi-list')->call('approve', $report->id);
        $this->assertSame('Terverifikasi', $report->refresh()->status);
        $this->assertSame(2, $report->verificationNotes()->count());
        $this->assertSame(1, $report->revisions()->count());
    }
}
