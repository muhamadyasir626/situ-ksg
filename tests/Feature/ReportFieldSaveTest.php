<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFieldSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_IKU_saves_correct_fields(): void
    {
        $period = ReportingPeriod::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $ikuGroup = IndicatorGroup::factory()->create(['code' => 'IKU']);
        $indicator = Indicator::factory()->for($ikuGroup, 'group')->create();
        $indicator->units()->attach($unit);

        $report = Report::create([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $period->id,
            'month' => 1,
            'quarter' => 1,
            'target_achievement' => '5 Spesies',
            'process_note' => 'Proses berjalan',
            'achievement_strategy' => 'Strategi...',
            'problem_narrative' => 'Tidak ada',
            'status' => 'Diajukan',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertNotNull($report);
        $this->assertEquals('5 Spesies', $report->target_achievement);
        $this->assertEquals('Proses berjalan', $report->process_note);
        $this->assertEquals('Strategi...', $report->achievement_strategy);
        $this->assertEquals('Tidak ada', $report->problem_narrative);
        $this->assertNull($report->follow_up);
        $this->assertNull($report->realization_achievement);
        $this->assertNull($report->budget_realization);
    }

    public function test_IKP_saves_correct_fields(): void
    {
        $period = ReportingPeriod::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $ikpGroup = IndicatorGroup::factory()->create(['code' => 'IKP']);
        $indicator = Indicator::factory()->for($ikpGroup, 'group')->create();
        $indicator->units()->attach($unit);

        $report = Report::create([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $period->id,
            'month' => 1,
            'quarter' => 1,
            'target_achievement' => '3 Spesies',
            'process_note' => 'Proses pelaksanaan...',
            'problem_narrative' => 'Kendala: keterbatasan data',
            'follow_up' => 'Akan ditindaklanjuti',
            'status' => 'Diajukan',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertNotNull($report);
        $this->assertEquals('3 Spesies', $report->target_achievement);
        $this->assertEquals('Proses pelaksanaan...', $report->process_note);
        $this->assertEquals('Kendala: keterbatasan data', $report->problem_narrative);
        $this->assertEquals('Akan ditindaklanjuti', $report->follow_up);
        $this->assertNull($report->achievement_strategy);
        $this->assertNull($report->realization_achievement);
        $this->assertNull($report->budget_realization);
    }

    public function test_IKK_saves_correct_fields(): void
    {
        $period = ReportingPeriod::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $ikkGroup = IndicatorGroup::factory()->create(['code' => 'IKK']);
        $indicator = Indicator::factory()->for($ikkGroup, 'group')->create();
        $indicator->units()->attach($unit);

        $report = Report::create([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $period->id,
            'month' => 1,
            'quarter' => 1,
            'target_achievement' => '10 Produk',
            'realization_achievement' => '8 Produk terealisasi',
            'progress_narrative' => 'Kegiatan sudah 80% selesai',
            'problem_narrative' => 'Tidak ada hambatan',
            'status' => 'Diajukan',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertNotNull($report);
        $this->assertEquals('10 Produk', $report->target_achievement);
        $this->assertEquals('8 Produk terealisasi', $report->realization_achievement);
        $this->assertEquals('Kegiatan sudah 80% selesai', $report->progress_narrative);
        $this->assertEquals('Tidak ada hambatan', $report->problem_narrative);
        $this->assertNull($report->process_note);
        $this->assertNull($report->follow_up);
        $this->assertNull($report->achievement_strategy);
        $this->assertNull($report->budget_realization);
    }

    public function test_RO_saves_correct_fields(): void
    {
        $period = ReportingPeriod::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $roGroup = IndicatorGroup::factory()->create(['code' => 'RO']);
        $indicator = Indicator::factory()->for($roGroup, 'group')->create();
        $indicator->units()->attach($unit);

        $report = Report::create([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $period->id,
            'month' => 1,
            'quarter' => 1,
            'target_achievement' => '100 Miliar',
            'budget_realization' => 75000000,
            'progress_narrative' => 'Pengadaan sedang berlangsung',
            'problem_narrative' => 'Proses tender tertunda',
            'status' => 'Diajukan',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertNotNull($report);
        $this->assertEquals('100 Miliar', $report->target_achievement);
        $this->assertEquals(75000000, $report->budget_realization);
        $this->assertEquals('Pengadaan sedang berlangsung', $report->progress_narrative);
        $this->assertEquals('Proses tender tertunda', $report->problem_narrative);
        $this->assertNull($report->achievement_strategy);
        $this->assertNull($report->process_note);
        $this->assertNull($report->follow_up);
        $this->assertNull($report->realization_achievement);
    }
}
