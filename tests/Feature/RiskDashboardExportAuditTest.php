<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Risk;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Risiko & Kendala (FR-35..38), Dashboard (FR-40), Ekspor (FR-42..46), Audit Trail (FR-50..51).
 */
class RiskDashboardExportAuditTest extends TestCase
{
    use RefreshDatabase;

    protected ReportingPeriod $period;

    protected Unit $unitA;

    protected Unit $unitB;

    protected Indicator $iku;

    protected Indicator $ro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->period = ReportingPeriod::factory()->create(['is_active' => true, 'year' => 2026, 'label' => 'TA 2026']);
        $this->unitA = Unit::factory()->create(['code' => 'ALF', 'name' => 'Unit Alfa']);
        $this->unitB = Unit::factory()->create(['code' => 'BET', 'name' => 'Unit Beta']);

        $ikuGroup = IndicatorGroup::factory()->create(['code' => 'IKU', 'sort_order' => 1]);
        $roGroup = IndicatorGroup::factory()->create(['code' => 'RO', 'sort_order' => 4]);
        $this->iku = Indicator::factory()->for($ikuGroup, 'group')->create(['name' => 'Indikator IKU Uji', 'target' => 10]);
        $this->ro = Indicator::factory()->for($roGroup, 'group')->create(['name' => 'Indikator RO Uji', 'target' => 4]);
        foreach ([$this->iku, $this->ro] as $i) {
            $i->units()->attach([$this->unitA->id, $this->unitB->id]);
        }
        $this->ro->periodSettings()->create(['reporting_period_id' => $this->period->id, 'target' => 4, 'unit' => 'Data', 'pagu_amount' => 1000000]);
    }

    protected function user(string $role, ?Unit $unit = null): User
    {
        $u = User::factory()->create(['unit_id' => $unit?->id]);
        $u->assignRole($role);

        return $u;
    }

    protected function report(Unit $unit, Indicator $indicator, string $status, array $extra = []): Report
    {
        return Report::factory()->create(array_merge([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $this->period->id,
            'month' => 1,
            'quarter' => 1,
            'status' => $status,
            'target_achievement' => '5',
            'budget_realization' => null,
        ], $extra));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function xlsxRows($response): array
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $response->streamedContent());
        $rows = IOFactory::load($file)->getActiveSheet()->toArray();
        unlink($file);

        return $rows;
    }

    // ---- Risiko -----------------------------------------------------------

    public function test_manage_risk_lifecycle_sets_and_clears_resolved_at(): void
    {
        $risk = Risk::factory()->create(['report_id' => $this->report($this->unitA, $this->iku, 'Terverifikasi')->id]);
        $this->actingAs($this->user('Verifikator'));

        Livewire::test('pages.risiko-list')
            ->call('openEdit', $risk->id)
            ->set('editStatus', 'Ditindaklanjuti')
            ->set('editPic', 'Tim A')
            ->set('editDueDate', '2026-12-31')
            ->call('saveEdit')
            ->assertHasNoErrors();
        $risk->refresh();
        $this->assertSame('Ditindaklanjuti', $risk->status);
        $this->assertSame('Tim A', $risk->pic);
        $this->assertSame('2026-12-31', $risk->due_date->format('Y-m-d'));
        $this->assertNull($risk->resolved_at);

        Livewire::test('pages.risiko-list')->call('openEdit', $risk->id)->set('editStatus', 'Selesai')->call('saveEdit');
        $this->assertNotNull($risk->refresh()->resolved_at);

        Livewire::test('pages.risiko-list')->call('openEdit', $risk->id)->set('editStatus', 'Baru')->call('saveEdit');
        $this->assertNull($risk->refresh()->resolved_at);
    }

    public function test_manage_risk_validation(): void
    {
        $risk = Risk::factory()->create(['report_id' => $this->report($this->unitA, $this->iku, 'Terverifikasi')->id]);
        $this->actingAs($this->user('Pimpinan'));

        Livewire::test('pages.risiko-list')
            ->call('openEdit', $risk->id)
            ->set('editStatus', 'Hilang')
            ->set('editDueDate', 'bukan-tanggal')
            ->set('editPic', str_repeat('x', 256))
            ->call('saveEdit')
            ->assertHasErrors(['editStatus', 'editDueDate', 'editPic']);

        $this->assertSame('Baru', $risk->refresh()->status);
    }

    public function test_read_only_roles_cannot_manage_risk_via_action(): void
    {
        $risk = Risk::factory()->create(['report_id' => $this->report($this->unitA, $this->iku, 'Terverifikasi')->id]);

        foreach ([$this->user('Pelapor Unit', $this->unitA), $this->user('Auditor')] as $user) {
            $this->actingAs($user);
            Livewire::test('pages.risiko-list')->call('openEdit', $risk->id)->assertForbidden();
        }
    }

    public function test_pelapor_cannot_bypass_unit_scope_with_filter(): void
    {
        Risk::factory()->create(['report_id' => $this->report($this->unitB, $this->iku, 'Terverifikasi')->id, 'narrative' => 'Rahasia Beta']);
        $this->actingAs($this->user('Pelapor Unit', $this->unitA));

        Livewire::test('pages.risiko-list')
            ->assertDontSee('Rahasia Beta')
            ->set('filterUnitId', $this->unitB->id)
            ->assertDontSee('Rahasia Beta')
            ->set('filterStatus', 'Baru')
            ->assertDontSee('Rahasia Beta');
    }

    public function test_risk_filters(): void
    {
        Risk::factory()->create(['report_id' => $this->report($this->unitA, $this->iku, 'Terverifikasi')->id, 'category' => Risk::CATEGORY_FRAUD, 'narrative' => 'Risiko Fraud A']);
        Risk::factory()->create(['report_id' => $this->report($this->unitB, $this->iku, 'Terverifikasi')->id, 'category' => Risk::CATEGORY_POLICY, 'status' => 'Selesai', 'narrative' => 'Risiko Kebijakan B']);
        $this->actingAs($this->user('Admin', $this->unitA));

        Livewire::test('pages.risiko-list')
            ->set('filterCategory', Risk::CATEGORY_FRAUD)->assertSee('Risiko Fraud A')->assertDontSee('Risiko Kebijakan B')
            ->set('filterCategory', '')->set('filterStatus', 'Selesai')->assertSee('Risiko Kebijakan B')->assertDontSee('Risiko Fraud A')
            ->set('filterStatus', '')->set('filterUnitId', $this->unitA->id)->assertSee('Risiko Fraud A')->assertDontSee('Risiko Kebijakan B');
    }

    // ---- Dashboard -----------------------------------------------------------

    public function test_dashboard_kpis_use_only_verified_reports_and_scope_pelapor(): void
    {
        $this->report($this->unitA, $this->iku, 'Terverifikasi', ['target_achievement' => '5']);
        $this->report($this->unitA, $this->iku, 'Draft', ['month' => 2, 'quarter' => 1, 'target_achievement' => '10']);
        $this->report($this->unitB, $this->ro, 'Terverifikasi', ['target_achievement' => '4', 'budget_realization' => 250000]);
        Risk::factory()->create(['report_id' => Report::first()->id, 'status' => 'Baru']);
        Risk::factory()->create(['report_id' => Report::latest('id')->first()->id, 'status' => 'Selesai']);

        $this->actingAs($this->user('Pimpinan'));
        $c = Livewire::test('pages.dashboard');
        $this->assertSame(75.0, $c->get('averageAchievementPercent') ?? $c->instance()->averageAchievementPercent);
        $this->assertSame(25.0, $c->instance()->budgetRealizationPercent);
        $this->assertSame(1, $c->instance()->openRiskCount);

        $this->actingAs($this->user('Pelapor Unit', $this->unitA));
        $p = Livewire::test('pages.dashboard');
        $this->assertSame(50.0, $p->instance()->averageAchievementPercent);
        $this->assertNull($p->instance()->budgetRealizationPercent);
        $this->assertSame(['Draft' => 1, 'Diajukan' => 0, 'Dikembalikan' => 0, 'Terverifikasi' => 1], $p->instance()->statusCounts);
    }

    public function test_dashboard_pelapor_scope_survives_livewire_roundtrip(): void
    {
        $other = ReportingPeriod::factory()->create(['is_active' => false, 'year' => 2025, 'label' => 'TA 2025']);
        $this->report($this->unitB, $this->iku, 'Terverifikasi', ['reporting_period_id' => $other->id, 'target_achievement' => '10']);
        $this->report($this->unitA, $this->iku, 'Terverifikasi', ['reporting_period_id' => $other->id, 'target_achievement' => '2']);

        $this->actingAs($this->user('Pelapor Unit', $this->unitA));
        $c = Livewire::test('pages.dashboard')->set('filterPeriod', $other->id);

        $this->assertSame(20.0, $c->instance()->averageAchievementPercent, 'Setelah ganti filter, Pelapor tetap hanya melihat unit sendiri');
        $this->assertSame(['Draft' => 0, 'Diajukan' => 0, 'Dikembalikan' => 0, 'Terverifikasi' => 1], $c->instance()->statusCounts);
    }

    public function test_risk_scope_survives_livewire_roundtrip(): void
    {
        Risk::factory()->create(['report_id' => $this->report($this->unitB, $this->iku, 'Terverifikasi')->id, 'narrative' => 'Rahasia Beta']);
        $this->actingAs($this->user('Pelapor Unit', $this->unitA));

        Livewire::test('pages.risiko-list')
            ->set('filterCategory', '')
            ->call('$refresh')
            ->assertDontSee('Rahasia Beta');
    }

    public function test_dashboard_open_risks_follow_period_filter(): void
    {
        $other = ReportingPeriod::factory()->create(['is_active' => false, 'year' => 2025, 'label' => 'TA 2025']);
        Risk::factory()->create(['report_id' => $this->report($this->unitA, $this->iku, 'Terverifikasi', ['reporting_period_id' => $other->id])->id]);

        $this->actingAs($this->user('Pimpinan'));
        $c = Livewire::test('pages.dashboard');
        $this->assertSame(0, $c->instance()->openRiskCount, 'KPI risiko terbuka harus mengikuti periode terpilih');
    }

    public function test_dashboard_renders_for_every_role(): void
    {
        $this->report($this->unitA, $this->iku, 'Terverifikasi');
        foreach (['Admin', 'Verifikator', 'Pimpinan', 'Auditor'] as $role) {
            $this->actingAs($this->user($role))->get('/dashboard')->assertOk()->assertSee('Rata-rata Capaian');
        }
        $this->actingAs($this->user('Pelapor Unit', $this->unitA))->get('/dashboard')->assertOk();
    }

    public function test_dashboard_export_button_uses_selected_period(): void
    {
        $this->actingAs($this->user('Pimpinan'));
        Livewire::test('pages.dashboard')
            ->assertSee(route('laporan.export.performance-pdf', ['period_id' => $this->period->id]), false);
    }

    // ---- Ekspor -----------------------------------------------------------

    public function test_performance_export_contains_only_verified_and_respects_unit_scope(): void
    {
        $this->report($this->unitA, $this->iku, 'Terverifikasi', ['target_achievement' => 'VERIF-A']);
        $this->report($this->unitB, $this->iku, 'Terverifikasi', ['target_achievement' => 'VERIF-B']);
        $this->report($this->unitA, $this->ro, 'Draft', ['target_achievement' => 'DRAFT-A']);

        $res = $this->actingAs($this->user('Pimpinan'))->get(route('laporan.export.performance-excel', ['period_id' => $this->period->id]));
        $res->assertOk();
        $this->assertStringContainsString('ringkasan-kinerja_ta-2026', $res->headers->get('content-disposition'));
        $flat = json_encode($this->xlsxRows($res));
        $this->assertStringContainsString('VERIF-A', $flat);
        $this->assertStringContainsString('VERIF-B', $flat);
        $this->assertStringNotContainsString('DRAFT-A', $flat);

        $res = $this->actingAs($this->user('Pelapor Unit', $this->unitA))->get(route('laporan.export.performance-excel', ['period_id' => $this->period->id]));
        $flat = json_encode($this->xlsxRows($res));
        $this->assertStringContainsString('VERIF-A', $flat);
        $this->assertStringNotContainsString('VERIF-B', $flat);
    }

    public function test_pdf_export_downloads_for_all_roles(): void
    {
        $this->report($this->unitA, $this->iku, 'Terverifikasi');
        foreach (['Admin', 'Verifikator', 'Pimpinan', 'Auditor'] as $role) {
            $res = $this->actingAs($this->user($role))->get(route('laporan.export.performance-pdf', ['period_id' => $this->period->id]));
            $res->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('content-type'));
        }
    }

    public function test_unit_and_risk_exports_respect_scope(): void
    {
        $rA = $this->report($this->unitA, $this->iku, 'Draft', ['target_achievement' => 'CAP-A']);
        $rB = $this->report($this->unitB, $this->iku, 'Diajukan', ['target_achievement' => 'CAP-B']);
        Risk::factory()->create(['report_id' => $rA->id, 'narrative' => 'RISK-A']);
        Risk::factory()->create(['report_id' => $rB->id, 'narrative' => 'RISK-B']);

        $pelapor = $this->user('Pelapor Unit', $this->unitA);

        $flat = json_encode($this->xlsxRows($this->actingAs($pelapor)->get(route('laporan.export.unit-excel'))));
        $this->assertStringContainsString('CAP-A', $flat);
        $this->assertStringNotContainsString('CAP-B', $flat);

        $flat = json_encode($this->xlsxRows($this->actingAs($pelapor)->get(route('laporan.export.risk-excel'))));
        $this->assertStringContainsString('RISK-A', $flat);
        $this->assertStringNotContainsString('RISK-B', $flat);

        $flat = json_encode($this->xlsxRows($this->actingAs($this->user('Auditor'))->get(route('laporan.export.risk-excel'))));
        $this->assertStringContainsString('RISK-B', $flat);
    }

    public function test_monthly_export_has_all_group_fields_and_validates_input(): void
    {
        $this->report($this->unitA, $this->iku, 'Draft', [
            'process_note' => 'PROSES-X', 'achievement_strategy' => 'STRATEGI-X', 'problem_narrative' => 'KENDALA-X',
        ]);
        $admin = $this->user('Admin', $this->unitA);

        $res = $this->actingAs($admin)->get(route('laporan.export.monthly-excel', ['period_id' => $this->period->id, 'month' => 1]));
        $res->assertOk();
        $rows = $this->xlsxRows($res);
        $flat = json_encode($rows);
        foreach (['PROSES-X', 'STRATEGI-X', 'KENDALA-X', 'Keterangan Proses', 'Upaya\/Strategi Pencapaian', 'Tindak Lanjut', 'Realisasi Capaian'] as $needle) {
            $this->assertStringContainsString($needle, $flat);
        }
        $this->assertStringNotContainsString('Risiko Kecurangan', $flat);

        $this->actingAs($admin)->get(route('laporan.export.monthly-excel', ['period_id' => $this->period->id]))->assertSessionHasErrors('month');
        $this->actingAs($admin)->get(route('laporan.export.monthly-excel', ['period_id' => $this->period->id, 'month' => 13]))->assertSessionHasErrors('month');
        $this->actingAs($admin)->get(route('laporan.export.monthly-excel', ['period_id' => 99999, 'month' => 1]))->assertSessionHasErrors('period_id');
    }

    public function test_laporan_page_renders_and_links_carry_period(): void
    {
        $this->actingAs($this->user('Auditor'));
        Livewire::test('pages.laporan')
            ->assertSee(route('laporan.export.risk-excel', ['period_id' => $this->period->id]), false)
            ->set('selectedPeriodId', null)
            ->assertSee('Pilih tahun anggaran terlebih dahulu');
    }

    // ---- Audit trail ---------------------------------------------------------

    public function test_audit_trail_records_report_and_risk_changes_with_old_and_new_values(): void
    {
        $admin = $this->user('Admin', $this->unitA);
        $this->actingAs($admin);

        $report = $this->report($this->unitA, $this->iku, 'Draft', ['target_achievement' => 'LAMA']);
        $report->update(['target_achievement' => 'BARU']);
        $risk = Risk::factory()->create(['report_id' => $report->id]);
        $risk->update(['pic' => 'PIC-AUDIT']);

        $this->assertTrue(Activity::where('subject_type', Report::class)->where('subject_id', $report->id)->exists());

        $this->actingAs($this->user('Auditor'));
        Livewire::test('pages.audit-trail')
            ->assertSee('LAMA')
            ->assertSee('BARU')
            ->assertSee('PIC-AUDIT')
            ->set('filterSubjectType', Risk::class)
            ->assertSee('PIC-AUDIT')
            ->assertDontSee('LAMA')
            ->set('filterSubjectType', '')
            ->set('filterCauserId', $admin->id)
            ->assertSee('BARU')
            ->set('filterFrom', now()->addDay()->format('Y-m-d'))
            ->assertDontSee('BARU')
            ->assertSee('Belum ada entri audit trail');
    }

    public function test_audit_trail_records_master_data_changes(): void
    {
        $admin = $this->user('Admin', $this->unitA);
        $this->actingAs($admin);

        Livewire::test('admin.manage-units')->call('create')->set('name', 'Unit Audit')->set('code', 'AUD')->call('save')->assertHasNoErrors();
        Livewire::test('admin.manage-users')->call('createUser')
            ->set('name', 'Pengguna Audit')->set('email', 'audit@x.test')->set('password', 'password123')
            ->set('roleId', Role::findByName('Auditor')->id)
            ->call('save')->assertHasNoErrors();

        $this->assertTrue(Activity::where('subject_type', Unit::class)->exists(), 'FR-50: perubahan Unit dicatat');
        $this->assertTrue(Activity::where('subject_type', User::class)->exists(), 'FR-50: perubahan User dicatat');
        $this->iku->update(['name' => 'Indikator IKU Diubah']);
        $this->assertTrue(Activity::where('subject_type', Indicator::class)->exists(), 'FR-50: perubahan Indikator dicatat');

        $userActivity = Activity::where('subject_type', User::class)->latest('id')->first();
        $this->assertArrayNotHasKey('password', $userActivity->attribute_changes?->get('attributes', []) ?? []);

        $this->actingAs($this->user('Auditor'));
        Livewire::test('pages.audit-trail')->assertSee('Unit Audit')->assertSee('Pengguna Audit')->assertSee('Indikator IKU Diubah');
    }
}
