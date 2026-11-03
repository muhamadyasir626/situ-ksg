<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\IndicatorPeriodSetting;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Administrasi (FR-10..14, BR-01, BR-08/09) dan otorisasi aksi Livewire (NFR-04).
 */
class AdminMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Unit $unit;

    protected ReportingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->unit = Unit::factory()->create(['code' => 'DKSG', 'name' => 'Direktorat KSG']);
        $this->period = ReportingPeriod::factory()->create(['is_active' => true, 'year' => 2026, 'label' => 'TA 2026']);
        $this->admin = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->admin->assignRole('Admin');
        $this->actingAs($this->admin);
    }

    protected function roleId(string $name): int
    {
        return Role::findByName($name)->id;
    }

    // ---- Pengguna ----------------------------------------------------------

    public function test_create_user_with_role_and_unit(): void
    {
        Livewire::test('admin.manage-users')
            ->call('createUser')
            ->set('name', 'Pelapor Baru')
            ->set('email', 'baru@x.test')
            ->set('password', 'rahasia123')
            ->set('roleId', $this->roleId('Pelapor Unit'))
            ->set('unitId', $this->unit->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::firstWhere('email', 'baru@x.test');
        $this->assertTrue($user->hasRole('Pelapor Unit'));
        $this->assertSame($this->unit->id, $user->unit_id);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_user_validation(): void
    {
        User::factory()->create(['email' => 'ada@x.test']);

        Livewire::test('admin.manage-users')
            ->call('createUser')
            ->set('name', '')
            ->set('email', 'ada@x.test')
            ->set('password', 'short')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'password' => 'min', 'roleId' => 'required']);

        Livewire::test('admin.manage-users')
            ->call('createUser')
            ->set('name', 'X')
            ->set('email', 'bukan-email')
            ->set('password', 'rahasia123')
            ->set('roleId', 99999)
            ->call('save')
            ->assertHasErrors(['email' => 'email', 'roleId' => 'exists']);
    }

    public function test_pelapor_unit_requires_unit(): void
    {
        Livewire::test('admin.manage-users')
            ->call('createUser')
            ->set('name', 'Pelapor Tanpa Unit')
            ->set('email', 'tanpa@x.test')
            ->set('password', 'rahasia123')
            ->set('roleId', $this->roleId('Pelapor Unit'))
            ->call('save')
            ->assertHasErrors(['unitId']);

        $this->assertNull(User::firstWhere('email', 'tanpa@x.test'));

        // Peran lain boleh tanpa unit (FR-10).
        Livewire::test('admin.manage-users')
            ->call('createUser')
            ->set('name', 'Verifikator Baru')
            ->set('email', 'ver@x.test')
            ->set('password', 'rahasia123')
            ->set('roleId', $this->roleId('Verifikator'))
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_change_role_and_unit_inline(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Auditor');

        Livewire::test('admin.manage-users')->call('assignRole', $user->id, $this->roleId('Pimpinan'));
        $this->assertTrue($user->fresh()->hasRole('Pimpinan'));
        $this->assertFalse($user->fresh()->hasRole('Auditor'), 'Satu pengguna satu peran');

        Livewire::test('admin.manage-users')->call('assignUnit', $user->id, $this->unit->id);
        $this->assertSame($this->unit->id, $user->fresh()->unit_id);
    }

    public function test_unit_can_be_cleared_from_non_pelapor_user(): void
    {
        $user = User::factory()->create(['unit_id' => $this->unit->id]);
        $user->assignRole('Pimpinan');

        Livewire::test('admin.manage-users')->call('assignUnit', $user->id, null)->assertHasNoErrors();
        $this->assertNull($user->fresh()->unit_id);
    }

    public function test_admin_cannot_demote_self(): void
    {
        Livewire::test('admin.manage-users')->call('assignRole', $this->admin->id, $this->roleId('Auditor'));
        $this->assertTrue($this->admin->fresh()->hasRole('Admin'), 'Admin tidak boleh mengunci dirinya sendiri keluar dari Administrasi');
    }

    // ---- Unit kerja --------------------------------------------------------

    public function test_unit_crud_and_validation(): void
    {
        Livewire::test('admin.manage-units')
            ->call('create')->set('name', 'Subdit Baru')->set('code', 'DKSG')->call('save')
            ->assertHasErrors(['code' => 'unique']);

        Livewire::test('admin.manage-units')
            ->call('create')->set('name', 'Subdit Baru')->set('code', 'SBR')->call('save')
            ->assertHasNoErrors();
        $unit = Unit::firstWhere('code', 'SBR');

        Livewire::test('admin.manage-units')
            ->call('edit', $unit->id)->set('name', 'Subdit Diubah')->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Subdit Diubah', $unit->fresh()->name);

        Livewire::test('admin.manage-units')->call('toggleActive', $unit->id);
        $this->assertFalse($unit->fresh()->is_active);
    }

    public function test_deleting_unit_with_reports_is_blocked(): void
    {
        $group = IndicatorGroup::factory()->create(['code' => 'IKU']);
        $indicator = Indicator::factory()->for($group, 'group')->create();
        Report::factory()->create(['indicator_id' => $indicator->id, 'unit_id' => $this->unit->id, 'reporting_period_id' => $this->period->id, 'month' => 1]);

        Livewire::test('admin.manage-units')->call('delete', $this->unit->id)->assertForbidden();

        $this->assertModelExists($this->unit);
        $this->assertSame(1, Report::count(), 'Menghapus unit tidak boleh menghapus laporan secara cascade');
    }

    public function test_unused_unit_can_be_deleted(): void
    {
        $unit = Unit::factory()->create();
        Livewire::test('admin.manage-units')->call('delete', $unit->id);
        $this->assertModelMissing($unit);
    }

    // ---- Periode -----------------------------------------------------------

    public function test_activating_period_deactivates_others(): void
    {
        Livewire::test('admin.manage-periods')
            ->call('create')
            ->set('year', '2027')->set('label', 'TA 2027')
            ->set('start_date', '2027-01-01')->set('end_date', '2027-12-31')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, ReportingPeriod::where('is_active', true)->count(), 'BR-01: hanya satu periode aktif');
        $this->assertSame('TA 2027', ReportingPeriod::active()->label);
        $this->assertFalse($this->period->fresh()->is_active);
    }

    public function test_period_validation(): void
    {
        Livewire::test('admin.manage-periods')
            ->call('create')
            ->set('year', '1999')->set('label', '')
            ->set('start_date', '2027-12-31')->set('end_date', '2027-01-01')
            ->call('save')
            ->assertHasErrors(['year' => 'min', 'label' => 'required', 'end_date' => 'after_or_equal']);
    }

    public function test_deleting_period_with_reports_is_blocked(): void
    {
        $group = IndicatorGroup::factory()->create(['code' => 'IKU']);
        $indicator = Indicator::factory()->for($group, 'group')->create();
        Report::factory()->create(['indicator_id' => $indicator->id, 'unit_id' => $this->unit->id, 'reporting_period_id' => $this->period->id, 'month' => 1]);

        Livewire::test('admin.manage-periods')->call('delete', $this->period->id)->assertForbidden();

        $this->assertModelExists($this->period);
        $this->assertSame(1, Report::count());
    }

    // ---- Indikator ---------------------------------------------------------

    public function test_indicator_create_with_period_settings_and_pagu_only_for_ro(): void
    {
        $iku = IndicatorGroup::factory()->create(['code' => 'IKU']);
        $ro = IndicatorGroup::factory()->create(['code' => 'RO']);

        Livewire::test('admin.manage-indicators')
            ->call('create')
            ->set('name', 'RO Baru')->set('groupId', $ro->id)->set('target', '12')->set('unit', 'Dokumen')->set('pagu', '5000000')
            ->call('save')->assertHasNoErrors();
        $roIndicator = Indicator::firstWhere('name', 'RO Baru');
        $this->assertSame('5000000.00', $roIndicator->paguFor($this->period->id));

        Livewire::test('admin.manage-indicators')
            ->call('create')
            ->set('name', 'IKU Baru')->set('groupId', $iku->id)->set('target', '1')->set('unit', 'Poin')->set('pagu', '999')
            ->call('save')->assertHasNoErrors();
        $this->assertNull(Indicator::firstWhere('name', 'IKU Baru')->paguFor($this->period->id), 'BR-08: pagu hanya RO');
    }

    public function test_indicator_edit_per_period_does_not_leak_to_other_period(): void
    {
        $ro = IndicatorGroup::factory()->create(['code' => 'RO']);
        $indicator = Indicator::factory()->for($ro, 'group')->create(['target' => 10, 'unit' => 'Data']);
        $old = ReportingPeriod::factory()->create(['is_active' => false, 'year' => 2025, 'label' => 'TA 2025']);

        Livewire::test('admin.manage-indicators')
            ->call('edit', $indicator->id)
            ->set('target', '20')->set('pagu', '100')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('20.00', $indicator->fresh()->resolvedTarget($this->period->id));
        $this->assertSame('10.00', $indicator->fresh()->resolvedTarget($old->id), 'BR-09 fallback tidak tertimpa');
    }

    public function test_indicator_validation(): void
    {
        Livewire::test('admin.manage-indicators')
            ->call('create')
            ->set('name', '')->set('target', '-1')->set('unit', '')->set('pagu', 'abc')
            ->call('save')
            ->assertHasErrors(['name', 'target' => 'min', 'unit', 'groupId', 'pagu' => 'numeric']);
    }

    public function test_indicator_toggle_active(): void
    {
        $indicator = Indicator::factory()->create(['is_active' => true]);
        Livewire::test('admin.manage-indicators')->call('toggleActive', $indicator->id);
        $this->assertFalse($indicator->fresh()->is_active);
    }

    // ---- Penugasan ---------------------------------------------------------

    public function test_assignment_toggle(): void
    {
        $indicator = Indicator::factory()->create(['is_active' => true]);

        Livewire::test('admin.manage-assignments')
            ->set('selectedUnitId', $this->unit->id)
            ->call('toggleAssignment', $indicator->id);
        $this->assertTrue($indicator->units()->where('units.id', $this->unit->id)->exists());

        Livewire::test('admin.manage-assignments')
            ->set('selectedUnitId', $this->unit->id)
            ->call('toggleAssignment', $indicator->id);
        $this->assertFalse($indicator->units()->where('units.id', $this->unit->id)->exists());

        // Tanpa unit terpilih: tidak terjadi apa-apa.
        Livewire::test('admin.manage-assignments')->call('toggleAssignment', $indicator->id);
        $this->assertSame(0, $indicator->units()->count());
    }

    // ---- Otorisasi aksi (NFR-04) --------------------------------------------

    public function test_non_admin_cannot_mount_admin_components(): void
    {
        foreach (['Pelapor Unit', 'Verifikator', 'Pimpinan', 'Auditor'] as $role) {
            $user = User::factory()->create(['unit_id' => $this->unit->id]);
            $user->assignRole($role);
            $this->actingAs($user);

            foreach (['admin.manage-users', 'admin.manage-units', 'admin.manage-periods', 'admin.manage-indicators', 'admin.manage-assignments'] as $component) {
                Livewire::test($component)->assertForbidden();
            }
        }
    }

    public function test_settings_persist_after_admin_save_audit(): void
    {
        $ro = IndicatorGroup::factory()->create(['code' => 'RO']);
        $indicator = Indicator::factory()->for($ro, 'group')->create();

        Livewire::test('admin.manage-indicators')
            ->call('edit', $indicator->id)->set('pagu', '750')->call('save');

        $this->assertSame(1, IndicatorPeriodSetting::count());
    }
}
