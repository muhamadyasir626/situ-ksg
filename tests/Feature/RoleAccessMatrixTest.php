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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PRD §3.2 — matriks akses halaman per peran (HTTP, bukan hanya sidebar).
 */
class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Unit $unitA;

    protected Unit $unitB;

    protected ReportingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->unitA = Unit::factory()->create(['name' => 'Unit Alfa', 'code' => 'ALF']);
        $this->unitB = Unit::factory()->create(['name' => 'Unit Beta', 'code' => 'BET']);
        $this->period = ReportingPeriod::factory()->create(['is_active' => true, 'year' => 2026, 'label' => 'TA 2026']);
    }

    protected function userWithRole(string $role, ?Unit $unit = null): User
    {
        $user = User::factory()->create(['unit_id' => $unit?->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeReport(Unit $unit, string $status = 'Draft', string $groupCode = 'IKU'): Report
    {
        $group = IndicatorGroup::firstWhere('code', $groupCode) ?? IndicatorGroup::factory()->create(['code' => $groupCode]);
        $indicator = Indicator::factory()->for($group, 'group')->create(['name' => 'Indikator '.$unit->code.' '.uniqid()]);
        $indicator->units()->attach($unit);

        return Report::factory()->create([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $this->period->id,
            'month' => 1,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function matrix(): array
    {
        $admin = ['/admin/users', '/admin/units', '/admin/periods', '/admin/indicators', '/admin/assignments'];

        $row = fn (array $ok, array $forbidden) => array_merge(
            array_fill_keys($ok, 200),
            array_fill_keys($forbidden, 403),
        );

        $common = ['/dashboard', '/indikator', '/realisasi', '/risiko', '/laporan'];

        return [
            'Admin' => ['Admin', $row([...$common, '/realisasi/create', '/verifikasi', '/audit-trail', ...$admin], [])],
            'Pelapor Unit' => ['Pelapor Unit', $row([...$common, '/realisasi/create'], ['/verifikasi', '/audit-trail', ...$admin])],
            'Verifikator' => ['Verifikator', $row([...$common, '/verifikasi'], ['/realisasi/create', '/audit-trail', ...$admin])],
            'Pimpinan' => ['Pimpinan', $row($common, ['/realisasi/create', '/verifikasi', '/audit-trail', ...$admin])],
            'Auditor' => ['Auditor', $row([...$common, '/audit-trail'], ['/realisasi/create', '/verifikasi', ...$admin])],
        ];
    }

    /**
     * @param  array<string, int>  $expectations
     */
    #[DataProvider('matrix')]
    public function test_page_access_matrix(string $role, array $expectations): void
    {
        $user = $this->userWithRole($role, $role === 'Pelapor Unit' || $role === 'Admin' ? $this->unitA : null);

        foreach ($expectations as $uri => $status) {
            $this->actingAs($user)->get($uri)->assertStatus($status);
        }
    }

    public function test_guest_is_redirected_to_login_for_every_protected_page(): void
    {
        foreach (['/dashboard', '/realisasi', '/realisasi/create', '/verifikasi', '/risiko', '/laporan', '/audit-trail', '/admin/users', '/laporan/ekspor/kinerja.xlsx'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_sidebar_menu_matches_role(): void
    {
        $pelapor = $this->userWithRole('Pelapor Unit', $this->unitA);
        $this->actingAs($pelapor)->get('/dashboard')
            ->assertDontSee(route('verifikasi'))
            ->assertDontSee(route('audit-trail'))
            ->assertDontSee(route('admin.users'));

        $auditor = $this->userWithRole('Auditor');
        $this->actingAs($auditor)->get('/dashboard')
            ->assertSee(route('audit-trail'))
            ->assertDontSee(route('verifikasi'))
            ->assertDontSee(route('admin.users'));

        $verifikator = $this->userWithRole('Verifikator');
        $this->actingAs($verifikator)->get('/dashboard')
            ->assertSee(route('verifikasi'))
            ->assertDontSee(route('admin.users'));
    }

    public function test_pagu_legacy_url_redirects_to_indicator_management(): void
    {
        $admin = $this->userWithRole('Admin', $this->unitA);
        $this->actingAs($admin)->get('/pagu')->assertRedirect('/admin/indicators');
    }

    public function test_realisasi_list_scoping_per_role(): void
    {
        $this->makeReport($this->unitA);
        $this->makeReport($this->unitB);

        $nameA = Report::where('unit_id', $this->unitA->id)->first()->indicator->name;
        $nameB = Report::where('unit_id', $this->unitB->id)->first()->indicator->name;

        $this->actingAs($this->userWithRole('Pelapor Unit', $this->unitA))->get('/realisasi')
            ->assertSee($nameA)->assertDontSee($nameB);

        foreach (['Admin', 'Verifikator', 'Pimpinan', 'Auditor'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/realisasi')
                ->assertSee($nameA)->assertSee($nameB);
        }
    }

    public function test_auditor_realisasi_is_read_only(): void
    {
        $report = $this->makeReport($this->unitA);

        $this->actingAs($this->userWithRole('Auditor'))->get('/realisasi')
            ->assertSee($report->indicator->name)
            ->assertDontSee(route('realisasi.edit', $report))
            ->assertDontSee(route('realisasi.create'));
    }

    public function test_risk_list_scoping_and_manage_button(): void
    {
        $riskA = Risk::factory()->create(['report_id' => $this->makeReport($this->unitA)->id, 'narrative' => 'Narasi risiko Alfa']);
        Risk::factory()->create(['report_id' => $this->makeReport($this->unitB)->id, 'narrative' => 'Narasi risiko Beta']);

        $this->actingAs($this->userWithRole('Pelapor Unit', $this->unitA))->get('/risiko')
            ->assertSee('Narasi risiko Alfa')
            ->assertDontSee('Narasi risiko Beta')
            ->assertDontSee('openEdit(', false);

        $this->actingAs($this->userWithRole('Auditor'))->get('/risiko')
            ->assertSee('Narasi risiko Alfa')
            ->assertSee('Narasi risiko Beta')
            ->assertDontSee('openEdit(', false);

        foreach (['Admin', 'Verifikator', 'Pimpinan'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/risiko')
                ->assertSee('Narasi risiko Beta')
                ->assertSee('openEdit(', false);
        }

        $this->assertNotNull($riskA);
    }

    public function test_edit_url_respects_report_policy(): void
    {
        $draftA = $this->makeReport($this->unitA, 'Draft');
        $verifiedA = $this->makeReport($this->unitA, 'Terverifikasi');
        $submittedA = $this->makeReport($this->unitA, 'Diajukan');
        $returnedA = $this->makeReport($this->unitA, 'Dikembalikan');
        $draftB = $this->makeReport($this->unitB, 'Draft');

        $pelaporA = $this->userWithRole('Pelapor Unit', $this->unitA);

        $this->actingAs($pelaporA)->get(route('realisasi.edit', $draftA))->assertOk();
        $this->actingAs($pelaporA)->get(route('realisasi.edit', $returnedA))->assertOk();
        $this->actingAs($pelaporA)->get(route('realisasi.edit', $submittedA))->assertForbidden();
        $this->actingAs($pelaporA)->get(route('realisasi.edit', $verifiedA))->assertForbidden();
        $this->actingAs($pelaporA)->get(route('realisasi.edit', $draftB))->assertForbidden();

        $admin = $this->userWithRole('Admin', $this->unitA);
        $this->actingAs($admin)->get(route('realisasi.edit', $draftB))->assertOk();
        $this->actingAs($admin)->get(route('realisasi.edit', $verifiedA))->assertForbidden();

        foreach (['Verifikator', 'Pimpinan', 'Auditor'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('realisasi.edit', $draftA))->assertForbidden();
        }

        $this->actingAs($pelaporA)->get('/realisasi/999999/edit')->assertNotFound();
    }
}
