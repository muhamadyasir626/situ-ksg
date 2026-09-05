<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\IndicatorPeriodSetting;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\ReportRevision;
use App\Models\Risk;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerificationNote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DummyDataSeeder extends Seeder
{
    private const PASSWORD = 'Demo12345!';

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UnitSeeder::class,
            IndicatorGroupSeeder::class,
            IndicatorSeeder::class,
            ReportingPeriodSeeder::class,
        ]);

        Model::withoutEvents(function (): void {
            DB::transaction(function (): void {
                $units = Unit::query()->whereIn('code', ['DKSG', 'SKS', 'SKG'])->get()->keyBy('code');
                $period = ReportingPeriod::query()->where('year', 2026)->firstOrFail();
                $users = $this->seedUsers($units);
                $indicators = Indicator::query()->with('group')->orderBy('sort_order')->get();

                foreach ($indicators as $index => $indicator) {
                    $unit = $units->get(['DKSG', 'SKS', 'SKG'][$index % 3]);

                    IndicatorAssignment::query()->firstOrCreate([
                        'indicator_id' => $indicator->id,
                        'unit_id' => $unit->id,
                    ]);

                    $budgetCeiling = $indicator->group->code === 'RO'
                        ? 100_000_000 + ($index * 25_000_000)
                        : null;

                    IndicatorPeriodSetting::query()->updateOrCreate(
                        [
                            'indicator_id' => $indicator->id,
                            'reporting_period_id' => $period->id,
                        ],
                        [
                            'target' => $indicator->target,
                            'unit' => $indicator->unit,
                            'pagu_amount' => $budgetCeiling,
                        ],
                    );

                    $reporter = $users->get('pelapor.'.strtolower($unit->code).'@demo.situksg.test');

                    foreach ([1, 2, 3] as $month) {
                        $this->seedReport(
                            indicator: $indicator,
                            unit: $unit,
                            period: $period,
                            reporter: $reporter,
                            verifier: $users->get('verifikator@demo.situksg.test'),
                            index: $index,
                            month: $month,
                            budgetCeiling: $budgetCeiling,
                        );
                    }
                }
            });
        });
    }

    private function seedUsers($units)
    {
        $definitions = [
            ['Administrator Demo', 'admin@demo.situksg.test', 'Admin', 'DKSG'],
            ['Pelapor Direktorat KSG', 'pelapor.dksg@demo.situksg.test', 'Pelapor Unit', 'DKSG'],
            ['Pelapor Konservasi Spesies', 'pelapor.sks@demo.situksg.test', 'Pelapor Unit', 'SKS'],
            ['Pelapor Konservasi Genetik', 'pelapor.skg@demo.situksg.test', 'Pelapor Unit', 'SKG'],
            ['Verifikator Demo', 'verifikator@demo.situksg.test', 'Verifikator', null],
            ['Pimpinan Demo', 'pimpinan@demo.situksg.test', 'Pimpinan', null],
            ['Auditor Demo', 'auditor@demo.situksg.test', 'Auditor', null],
        ];

        $users = collect();

        foreach ($definitions as [$name, $email, $roleName, $unitCode]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => self::PASSWORD,
                    'unit_id' => $unitCode ? $units->get($unitCode)->id : null,
                ],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([Role::findByName($roleName)]);
            $users->put($email, $user);
        }

        return $users;
    }

    private function seedReport(
        Indicator $indicator,
        Unit $unit,
        ReportingPeriod $period,
        User $reporter,
        User $verifier,
        int $index,
        int $month,
        ?int $budgetCeiling,
    ): void {
        $statuses = match ($month) {
            1 => ['Terverifikasi'],
            2 => ['Terverifikasi', 'Dikembalikan', 'Diajukan'],
            default => ['Draft', 'Diajukan', 'Terverifikasi', 'Dikembalikan'],
        };
        $status = $statuses[$index % count($statuses)];
        $submittedAt = $status === 'Draft' ? null : Carbon::create($period->year, $month, 18, 9);
        $verifiedAt = $status === 'Terverifikasi' ? Carbon::create($period->year, $month, 20, 14) : null;
        $achievement = round(((float) $indicator->target / 12) * (0.8 + (($index % 4) * 0.1)), 2);
        $isIku = $indicator->group->code === 'IKU';

        $attributes = [
            'quarter' => (int) ceil($month / 3),
            'ro_budget_ceiling' => $budgetCeiling,
            'budget_realization' => $budgetCeiling ? round($budgetCeiling * (0.18 + ($month * 0.04)), 2) : null,
            'target_achievement' => $achievement.' '.$indicator->unit,
            'progress_narrative' => "Pelaksanaan bulan {$month} berjalan sesuai rencana kerja dan target unit.",
            'problem_narrative' => $status === 'Dikembalikan'
                ? 'Bukti pendukung dan rincian capaian perlu dilengkapi.'
                : 'Tidak terdapat kendala utama pada periode pelaporan ini.',
            'risk_external_narrative' => $isIku ? 'Koordinasi dengan mitra eksternal belum optimal.' : null,
            'risk_strategic_narrative' => $isIku ? 'Perubahan prioritas dapat memengaruhi pencapaian target.' : null,
            'risk_fraud_narrative' => $isIku ? 'Validasi berjenjang diperlukan untuk menjaga integritas data.' : null,
            'risk_policy_narrative' => $isIku ? 'Penyesuaian kebijakan dapat mengubah jadwal pelaksanaan.' : null,
            'status' => $status,
            'submitted_at' => $submittedAt,
            'verified_at' => $verifiedAt,
            'verified_by' => $verifiedAt ? $verifier->id : null,
            'created_by' => $reporter->id,
            'updated_by' => $status === 'Terverifikasi' ? $verifier->id : $reporter->id,
        ];

        $report = Report::query()->where([
            'indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'reporting_period_id' => $period->id,
            'month' => $month,
        ])->first();

        if ($report && $report->created_by !== $reporter->id) {
            return;
        }

        if ($report) {
            $report->update($attributes);
        } else {
            $report = Report::query()->create($attributes + [
                'indicator_id' => $indicator->id,
                'unit_id' => $unit->id,
                'reporting_period_id' => $period->id,
                'month' => $month,
            ]);
        }

        $this->seedVerification($report, $verifier, $reporter);

        if ($isIku) {
            $this->seedRisks($report, $verifier, $index);
        }
    }

    private function seedVerification(Report $report, User $verifier, User $reporter): void
    {
        if ($report->status === 'Terverifikasi') {
            VerificationNote::query()->updateOrCreate(
                ['report_id' => $report->id, 'verifier_id' => $verifier->id, 'action' => 'Disetujui'],
                ['note' => 'Data dan bukti pendukung telah diperiksa dan dinyatakan sesuai.'],
            );
        }

        if ($report->status === 'Dikembalikan') {
            VerificationNote::query()->updateOrCreate(
                ['report_id' => $report->id, 'verifier_id' => $verifier->id, 'action' => 'Dikembalikan'],
                ['note' => 'Mohon lengkapi bukti pendukung dan perjelas narasi capaian.'],
            );

            ReportRevision::query()->firstOrCreate(
                ['report_id' => $report->id, 'revised_by' => $reporter->id],
                ['payload' => ['status' => 'Dikembalikan', 'note' => 'Snapshot data sebelum perbaikan']],
            );
        }
    }

    private function seedRisks(Report $report, User $verifier, int $index): void
    {
        foreach (Report::riskCategoryMap() as $column => $category) {
            $categoryIndex = array_search($category, Risk::categories(), true);
            $status = Risk::statuses()[($index + $categoryIndex) % count(Risk::statuses())];

            Risk::query()->updateOrCreate(
                ['report_id' => $report->id, 'category' => $category],
                [
                    'narrative' => $report->{$column},
                    'status' => $status,
                    'pic' => $status === Risk::STATUS_BARU ? null : 'Tim Pengendalian Internal',
                    'due_date' => $status === Risk::STATUS_BARU ? null : Carbon::create(2026, 6, 30),
                    'resolved_at' => $status === Risk::STATUS_SELESAI ? Carbon::create(2026, 7, 5, 10) : null,
                    'updated_by' => $status === Risk::STATUS_BARU ? null : $verifier->id,
                ],
            );
        }
    }
}
