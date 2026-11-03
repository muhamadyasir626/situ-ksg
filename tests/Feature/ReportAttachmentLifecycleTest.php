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

class ReportAttachmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Unit $unit;

    protected User $user;

    protected Report $report;

    protected ReportAttachment $attachment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolePermissionSeeder::class);

        $period = ReportingPeriod::factory()->create(['is_active' => true]);
        $this->unit = Unit::factory()->create();
        $this->user = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->user->assignRole('Pelapor Unit');

        $group = IndicatorGroup::factory()->create(['code' => 'RO']);
        $indicator = Indicator::factory()->for($group, 'group')->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->report = Report::factory()->create([
            'indicator_id' => $indicator->id,
            'unit_id' => $this->unit->id,
            'reporting_period_id' => $period->id,
            'month' => 1,
            'status' => 'Draft',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        Storage::disk('local')->put('attachments/existing.pdf', 'dummy-content');

        $this->attachment = ReportAttachment::factory()->create([
            'report_id' => $this->report->id,
            'path' => 'attachments/existing.pdf',
            'original_name' => 'bukti-dukung.pdf',
        ]);
    }

    public function test_existing_attachments_are_listed_when_editing_a_report(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages.report-form', ['report' => $this->report->id])
            ->assertSee('bukti-dukung.pdf');
    }

    public function test_saving_draft_without_new_uploads_preserves_existing_attachments(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages.report-form', ['report' => $this->report->id])
            ->set('progressNarrative', 'Progres diperbarui')
            ->call('saveDraft');

        $this->assertDatabaseHas('report_attachments', [
            'id' => $this->attachment->id,
            'report_id' => $this->report->id,
        ]);
        $this->assertTrue(Storage::disk('local')->exists('attachments/existing.pdf'));
    }

    public function test_uploading_a_new_file_appends_without_removing_existing_attachment(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages.report-form', ['report' => $this->report->id])
            ->set('attachments', [UploadedFile::fake()->create('tambahan.pdf', 100, 'application/pdf')])
            ->call('saveDraft');

        $this->assertDatabaseHas('report_attachments', ['id' => $this->attachment->id]);
        $this->assertDatabaseHas('report_attachments', [
            'report_id' => $this->report->id,
            'original_name' => 'tambahan.pdf',
        ]);
        $this->assertSame(2, ReportAttachment::where('report_id', $this->report->id)->count());
    }

    public function test_deleting_an_attachment_removes_only_that_file(): void
    {
        $this->actingAs($this->user);

        $second = ReportAttachment::factory()->create([
            'report_id' => $this->report->id,
            'path' => 'attachments/second.pdf',
            'original_name' => 'kedua.pdf',
        ]);
        Storage::disk('local')->put('attachments/second.pdf', 'more-dummy-content');

        Livewire::test('pages.report-form', ['report' => $this->report->id])
            ->call('deleteAttachment', $this->attachment->id);

        $this->assertDatabaseMissing('report_attachments', ['id' => $this->attachment->id]);
        $this->assertDatabaseHas('report_attachments', ['id' => $second->id]);
        $this->assertFalse(Storage::disk('local')->exists('attachments/existing.pdf'));
        $this->assertTrue(Storage::disk('local')->exists('attachments/second.pdf'));
    }

    public function test_user_from_another_unit_cannot_delete_an_attachment(): void
    {
        $otherUnit = Unit::factory()->create();
        $otherUser = User::factory()->create(['unit_id' => $otherUnit->id]);
        $otherUser->assignRole('Pelapor Unit');
        $this->actingAs($otherUser);

        Livewire::test('pages.report-form', ['report' => $this->report->id])
            ->assertForbidden();

        $this->assertDatabaseHas('report_attachments', ['id' => $this->attachment->id]);
    }

    public function test_attachment_download_route_requires_authorization(): void
    {
        $otherUnit = Unit::factory()->create();
        $otherUser = User::factory()->create(['unit_id' => $otherUnit->id]);
        $this->actingAs($otherUser);

        $this->get(route('realisasi.attachments.download', $this->attachment))->assertForbidden();

        $this->actingAs($this->user);
        $this->get(route('realisasi.attachments.download', $this->attachment))->assertOk();
    }
}
