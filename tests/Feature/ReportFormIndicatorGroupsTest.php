<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportFormIndicatorGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected $period;
    protected $unit;
    protected $user;
    protected $ikuGroup;
    protected $ikpGroup;
    protected $ikkGroup;
    protected $roGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = ReportingPeriod::factory()->create(['is_active' => true]);
        $this->unit = Unit::factory()->create();
        $this->user = User::factory()->create(['unit_id' => $this->unit->id]);

        $this->ikuGroup = IndicatorGroup::factory()->create(['code' => 'IKU', 'name' => 'Indikator Kinerja Utama']);
        $this->ikpGroup = IndicatorGroup::factory()->create(['code' => 'IKP', 'name' => 'Indikator Kinerja Program']);
        $this->ikkGroup = IndicatorGroup::factory()->create(['code' => 'IKK', 'name' => 'Indikator Kinerja Kegiatan']);
        $this->roGroup = IndicatorGroup::factory()->create(['code' => 'RO', 'name' => 'Rincian Output']);
    }

    public function test_IKU_form_shows_correct_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikuGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikuGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->assertSee('Capaian Target Bulan')
            ->assertSee('Keterangan Proses')
            ->assertSee('Upaya/Strategi Pencapaian')
            ->assertSee('Permasalahan')
            ->assertDontSee('Narasi Progres')
            ->assertDontSee('Realisasi Capaian')
            ->assertDontSee('Tindak Lanjut')
            ->assertDontSee('Realisasi Anggaran')
            ->assertDontSee('Kendala');
    }

    public function test_IKP_form_shows_correct_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikpGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikpGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->assertSee('Capaian Target Bulan')
            ->assertSee('Keterangan Proses')
            ->assertSee('Permasalahan')
            ->assertSee('Tindak Lanjut')
            ->assertDontSee('Narasi Progres')
            ->assertDontSee('Upaya/Strategi Pencapaian')
            ->assertDontSee('Realisasi Capaian')
            ->assertDontSee('Realisasi Anggaran')
            ->assertDontSee('Kendala');
    }

    public function test_IKK_form_shows_correct_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikkGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikkGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->assertSee('Capaian Target Bulan')
            ->assertSee('Realisasi Capaian')
            ->assertSee('Narasi Proses')
            ->assertSee('Permasalahan')
            ->assertDontSee('Keterangan Proses')
            ->assertDontSee('Tindak Lanjut')
            ->assertDontSee('Upaya/Strategi Pencapaian')
            ->assertDontSee('Realisasi Anggaran')
            ->assertDontSee('Kendala');
    }

    public function test_RO_form_shows_correct_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->roGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->roGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->assertSee('Capaian Target Bulan')
            ->assertSee('Realisasi Anggaran Bulan Ini (Rp)')
            ->assertSee('Narasi Progres')
            ->assertSee('Kendala')
            ->assertDontSee('Keterangan Proses')
            ->assertDontSee('Tindak Lanjut')
            ->assertDontSee('Upaya/Strategi Pencapaian')
            ->assertDontSee('Realisasi Capaian')
            ->assertDontSee('Narasi Proses');
    }

    public function test_IKU_submit_requires_all_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikuGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikuGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 1)
            ->set('targetAchievement', '5 Spesies')
            ->call('submit')
            ->assertHasErrors([
                'processNote' => 'required',
                'achievementStrategy' => 'required',
                'problemNarrative' => 'required',
            ]);
    }

    public function test_IKP_submit_requires_all_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikpGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikpGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 1)
            ->set('targetAchievement', '3 Spesies')
            ->call('submit')
            ->assertHasErrors([
                'processNote' => 'required',
                'problemNarrative' => 'required',
                'followUp' => 'required',
            ]);
    }

    public function test_IKK_submit_requires_all_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikkGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikkGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 1)
            ->call('submit')
            ->assertHasErrors([
                'targetAchievement' => 'required',
                'realizationAchievement' => 'required',
                'progressNarrative' => 'required',
                'problemNarrative' => 'required',
            ]);
    }

    public function test_RO_submit_requires_all_fields(): void
    {
        $indicator = Indicator::factory()
            ->for($this->roGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->roGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 1)
            ->call('submit')
            ->assertHasErrors([
                'targetAchievement' => 'required',
                'budgetRealization' => 'required',
                'progressNarrative' => 'required',
                'problemNarrative' => 'required',
            ]);
    }

    public function test_IKU_fields_nulled_on_save(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikuGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikuGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 1)
            ->set('targetAchievement', '5 Spesies')
            ->set('processNote', 'Proses berjalan')
            ->set('achievementStrategy', 'Strategi...')
            ->set('problemNarrative', 'Tidak ada')
            ->call('submit')
            ->assertHasNoErrors();

        $report = Report::latest()->first();
        $this->assertNotNull($report);
        $this->assertEquals('5 Spesies', $report->target_achievement);
        $this->assertEquals('Proses berjalan', $report->process_note);
        $this->assertEquals('Strategi...', $report->achievement_strategy);
        $this->assertEquals('Tidak ada', $report->problem_narrative);
        $this->assertNull($report->follow_up);
        $this->assertNull($report->realization_achievement);
        $this->assertNull($report->budget_realization);
    }

    public function test_draft_saves_without_required_validation(): void
    {
        $indicator = Indicator::factory()
            ->for($this->ikuGroup, 'group')
            ->create(['is_active' => true]);
        $indicator->units()->attach($this->unit);

        $this->actingAs($this->user);

        Livewire::test('pages.report-form')
            ->set('selectedGroupId', $this->ikuGroup->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->set('selectedMonth', 1)
            ->set('targetAchievement', '5 Spesies')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $report = Report::latest()->first();
        $this->assertNotNull($report);
        $this->assertEquals('Draft', $report->status);
        $this->assertEquals('5 Spesies', $report->target_achievement);
    }
}
