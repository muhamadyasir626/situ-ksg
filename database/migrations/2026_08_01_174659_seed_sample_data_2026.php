<?php

use App\Models\Indicator;
use App\Models\IndicatorPeriodSetting;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Risk;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerificationNote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Data migration (§14.6, direvisi Fase 21) — bukan Seeder biasa: dipasang/dilepas lewat
 * `php artisan migrate` / `php artisan migrate:rollback`, bukan `db:seed`, agar tidak pernah
 * ikut terbawa saat developer menjalankan `php artisan db:seed` atau `php artisan migrate --seed`
 * untuk instalasi baku.
 *
 * Fase 21 mengubah periode jadi tahunan: migration ini kini memakai periode aktif yang SUDAH ADA
 * (dibuat `ReportingPeriodSeeder`, biasanya "Tahun Anggaran 2026") untuk data tahun berjalan
 * (Q1–Q3 saja — belum genap setahun) DAN membuat satu periode tahunan baru "Tahun Anggaran 2025"
 * (Q1–Q4 lengkap) sebagai tahun historis. Laporan per triwulan bersifat KUMULATIF (rasio capaian
 * naik dari Q1→Q4), sesuai desain terkunci di §21 dev-plan.
 *
 * Penanda unik untuk `down()`: label periode "Tahun Anggaran 2025" (periode baru, aman dihapus
 * penuh) dan `created_by`/domain email `@contoh.situksg.test` untuk laporan & user sampel yang
 * menumpang di periode aktif yang SUDAH ADA (tidak aman dihapus — hanya baris yang benar-benar
 * dibuat migration ini yang dihapus, ditandai lewat `created_by` = user sampel).
 *
 * `up()`/`down()` sengaja no-op saat `app()->environment('testing')` supaya migration ini tidak
 * pernah otomatis dijalankan oleh `RefreshDatabase` di suite Pest/CI — hanya dipasang manual oleh
 * developer di database utama.
 */
return new class extends Migration
{
    private const USER_DOMAIN = '@contoh.situksg.test';

    private const HISTORICAL_PERIOD_LABEL = 'Tahun Anggaran 2025';

    private const UNIT_CODES = ['DKSG', 'SKS', 'SKG'];

    /**
     * Status per triwulan (indeks 0-based selaras Q1..Q4). Slot `null` berarti berselang-seling
     * berdasarkan paritas indeks indikator (lihat alternateStatus()).
     *
     * @var array<int, string|null>
     */
    private const HISTORICAL_STATUS = ['Terverifikasi', 'Terverifikasi', 'Terverifikasi', null]; // Q4: Terverifikasi/Dikembalikan

    private const ACTIVE_STATUS = ['Terverifikasi', 'Terverifikasi', null]; // Q3 (berjalan): Diajukan/Draft

    /**
     * Rasio capaian kumulatif terhadap target per triwulan — naik dari Q1 ke triwulan
     * berikutnya (§21: "Nilai capaian pada laporan triwulan N merefleksikan akumulasi
     * pencapaian sampai akhir Q-N"). Sengaja ada yang di bawah 50% di Q1 supaya panel
     * "Indikator yang Membutuhkan Perhatian" di Dashboard (§6.4) punya data nyata.
     *
     * @var array<int, float>
     */
    private const HISTORICAL_RATIO = [0.35, 0.55, 0.75, 0.95];

    private const ACTIVE_RATIO = [0.30, 0.55, 0.70];

    public function up(): void
    {
        if (app()->environment('testing') || ! $this->referenceDataReady()) {
            return;
        }

        $periods = $this->seedPeriods();
        $units = Unit::whereIn('code', self::UNIT_CODES)->get()->keyBy('code');
        $users = $this->seedUsers($units);

        $this->seedAssignments($units);
        $this->seedPeriodSettings($periods);

        $reports = $this->seedReports($periods, $units, $users);
        $this->seedRisks($reports, $users);
        $this->seedVerificationNotes($reports, $users);
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $sampleUserIds = User::where('email', 'like', '%'.self::USER_DOMAIN)->pluck('id');

        // Laporan yang benar-benar dibuat migration ini — ditandai `created_by`, bukan periode,
        // karena sebagian menumpang di periode aktif yang sudah ada sebelum migration ini.
        $reportIds = Report::whereIn('created_by', $sampleUserIds)->pluck('id');

        Risk::whereIn('report_id', $reportIds)->delete();
        VerificationNote::whereIn('report_id', $reportIds)->delete();
        Report::whereIn('id', $reportIds)->delete();

        $historicalPeriodIds = ReportingPeriod::where('label', self::HISTORICAL_PERIOD_LABEL)->pluck('id');
        IndicatorPeriodSetting::whereIn('reporting_period_id', $historicalPeriodIds)->delete();
        ReportingPeriod::whereIn('id', $historicalPeriodIds)->delete();

        // Pagu yang ditambahkan ke IndicatorPeriodSetting periode AKTIF (baris itu sendiri
        // sudah ada sebelum migration ini, dibuat backfill Fase 21) sengaja TIDAK dihapus/di-null-
        // kan — sama seperti indicator_assignments, migration ini tidak bisa membedakan pagu yang
        // sudah diisi admin sungguhan dari yang ditulis migration ini setelah up() pertama kali.
        User::whereIn('id', $sampleUserIds)->delete();
    }

    /**
     * Data sampel menumpang di atas data referensi hasil `DatabaseSeeder` (peran, unit, indikator,
     * periode aktif). Pada instalasi baru `php artisan migrate` berjalan SEBELUM `php artisan
     * db:seed`, jadi lewati dengan tenang bila prasyaratnya belum ada — jangan menggagalkan
     * seluruh proses migrate (mis. `composer setup` pada clone baru). Pasang menyusul setelah
     * seeding dengan `php artisan migrate:rollback --step=1 && php artisan migrate`.
     */
    private function referenceDataReady(): bool
    {
        return Role::query()->exists()
            && Unit::whereIn('code', self::UNIT_CODES)->count() === count(self::UNIT_CODES)
            && Indicator::query()->exists()
            && ReportingPeriod::where('is_active', true)->exists();
    }

    /**
     * @return array{active: ReportingPeriod, historical: ReportingPeriod}
     */
    private function seedPeriods(): array
    {
        $active = ReportingPeriod::where('is_active', true)->first();

        $historical = ReportingPeriod::firstOrCreate(
            ['label' => self::HISTORICAL_PERIOD_LABEL],
            [
                'year' => $active->year - 1,
                'start_date' => ($active->year - 1).'-01-01',
                'end_date' => ($active->year - 1).'-12-31',
                'is_active' => false,
            ],
        );

        return ['active' => $active, 'historical' => $historical];
    }

    /**
     * @param  Collection<string, Unit>  $units
     * @return array<string, User>
     */
    private function seedUsers(Collection $units): array
    {
        $dksg = $units->get('DKSG');
        $sks = $units->get('SKS');
        $skg = $units->get('SKG');

        $definitions = [
            'admin' => ['name' => 'Admin Sampel', 'unit_id' => $dksg?->id, 'role' => 'Admin'],
            'pelapor.dksg' => ['name' => 'Pelapor Direktorat KSG', 'unit_id' => $dksg?->id, 'role' => 'Pelapor Unit'],
            'pelapor.sks' => ['name' => 'Pelapor Subdit Konservasi Spesies', 'unit_id' => $sks?->id, 'role' => 'Pelapor Unit'],
            'pelapor.skg' => ['name' => 'Pelapor Subdit Konservasi Genetik', 'unit_id' => $skg?->id, 'role' => 'Pelapor Unit'],
            'verifikator' => ['name' => 'Verifikator Sampel', 'unit_id' => null, 'role' => 'Verifikator'],
            'pimpinan' => ['name' => 'Pimpinan Sampel', 'unit_id' => null, 'role' => 'Pimpinan'],
            'auditor' => ['name' => 'Auditor Sampel', 'unit_id' => null, 'role' => 'Auditor'],
        ];

        $users = [];

        foreach ($definitions as $key => $definition) {
            $user = User::firstOrCreate(
                ['email' => $key.self::USER_DOMAIN],
                [
                    'name' => $definition['name'],
                    'password' => 'password',
                    'unit_id' => $definition['unit_id'],
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole($definition['role'])) {
                $user->assignRole($definition['role']);
            }

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * Tugaskan tiap indikator ke satu unit (rotasi DKSG/SKS/SKG) supaya user Pelapor Unit sampel
     * juga bisa memakai form pelaporan sungguhan, bukan cuma melihat data yang sudah ada.
     *
     * @param  Collection<string, Unit>  $units
     */
    private function seedAssignments(Collection $units): void
    {
        foreach (Indicator::all() as $index => $indicator) {
            $unit = $this->unitForIndex($units, $index);

            $indicator->units()->syncWithoutDetaching([$unit->id]);
        }
    }

    /**
     * Target/satuan disamakan dengan nilai dasar indikator di kedua periode (fokus sampel ini
     * ada di variasi laporan, bukan di per-periode target override). Pagu (§14.3) hanya untuk
     * kelompok RO, dihitung dari realisasi tertinggi tahun tsb + margin.
     *
     * @param  array{active: ReportingPeriod, historical: ReportingPeriod}  $periods
     */
    private function seedPeriodSettings(array $periods): void
    {
        foreach (Indicator::with('group')->get() as $index => $indicator) {
            $isRo = $indicator->group->code === 'RO';

            foreach ($periods as $period) {
                IndicatorPeriodSetting::updateOrCreate(
                    ['indicator_id' => $indicator->id, 'reporting_period_id' => $period->id],
                    [
                        'target' => $indicator->target,
                        'unit' => $indicator->unit,
                        'pagu_amount' => $isRo ? $this->paguFor($index, $period->id === $periods['historical']->id) : null,
                    ],
                );
            }
        }
    }

    /**
     * @param  array{active: ReportingPeriod, historical: ReportingPeriod}  $periods
     * @param  Collection<string, Unit>  $units
     * @param  array<string, User>  $users
     * @return Collection<int, Report>
     */
    private function seedReports(array $periods, Collection $units, array $users): Collection
    {
        $reports = collect();
        $admin = $users['admin'];
        $verifikator = $users['verifikator'];

        foreach (Indicator::with('group')->get() as $index => $indicator) {
            $unit = $this->unitForIndex($units, $index);
            $isIku = $indicator->group->code === 'IKU';
            $target = (float) $indicator->target;

            foreach ($this->quarterPlansFor($periods, $index) as [$period, $quarter, $status, $ratio]) {
                $isDraft = $status === 'Draft';
                $achievement = round($target * $ratio, 2);

                $data = [
                    'indicator_id' => $indicator->id,
                    'unit_id' => $unit->id,
                    'reporting_period_id' => $period->id,
                    'quarter' => $quarter,
                    'budget_realization' => $isDraft ? null : $this->budgetRealizationFor($index, $quarter),
                    'target_achievement' => $isDraft ? null : number_format($achievement, 2, '.', '').' '.$indicator->unit,
                    'progress_narrative' => $isDraft ? null : "Progres kumulatif \"{$indicator->name}\" sampai Q{$quarter} {$period->year}.",
                    'problem_narrative' => $quarter === 3 ? 'Keterlambatan koordinasi data lintas unit.' : null,
                    'status' => $status,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ];

                if ($isIku) {
                    $data['risk_external_narrative'] = "Ketergantungan pada data mitra eksternal untuk \"{$indicator->name}\" yang belum terintegrasi penuh.";
                    $data['risk_strategic_narrative'] = 'Perubahan prioritas strategis dapat menggeser alokasi sumber daya periode berjalan.';
                    $data['risk_fraud_narrative'] = 'Potensi ketidakkonsistenan data capaian bila proses verifikasi tidak berlapis.';
                    $data['risk_policy_narrative'] = 'Perubahan regulasi terkait berpotensi memengaruhi capaian target.';
                }

                if (in_array($status, ['Diajukan', 'Dikembalikan', 'Terverifikasi'], true)) {
                    $data['submitted_at'] = $period->start_date->copy()->addMonths(($quarter - 1) * 3)->addDays(20);
                }

                if ($status === 'Terverifikasi') {
                    $data['verified_at'] = $period->start_date->copy()->addMonths(($quarter - 1) * 3)->addDays(25);
                    $data['verified_by'] = $verifikator->id;
                }

                $report = Report::create($data);

                if ($isIku) {
                    $report->syncRisks();
                }

                $reports->push($report);
            }
        }

        return $reports;
    }

    /**
     * Rencana triwulan gabungan kedua periode untuk sebuah indikator: [period, quarter, status,
     * ratio] per baris. Status `null` di konstanta HISTORICAL_STATUS/ACTIVE_STATUS berarti
     * berselang-seling berdasarkan paritas indeks indikator (lihat alternateStatus()).
     *
     * @param  array{active: ReportingPeriod, historical: ReportingPeriod}  $periods
     * @return array<int, array{0: ReportingPeriod, 1: int, 2: string, 3: float}>
     */
    private function quarterPlansFor(array $periods, int $indicatorIndex): array
    {
        $plans = [];

        foreach (self::HISTORICAL_STATUS as $slot => $status) {
            $quarter = $slot + 1;
            $plans[] = [$periods['historical'], $quarter, $status ?? $this->alternateStatus($indicatorIndex, 'Terverifikasi', 'Dikembalikan'), self::HISTORICAL_RATIO[$slot]];
        }

        foreach (self::ACTIVE_STATUS as $slot => $status) {
            $quarter = $slot + 1;
            $plans[] = [$periods['active'], $quarter, $status ?? $this->alternateStatus($indicatorIndex, 'Diajukan', 'Draft'), self::ACTIVE_RATIO[$slot]];
        }

        return $plans;
    }

    private function alternateStatus(int $indicatorIndex, string $even, string $odd): string
    {
        return $indicatorIndex % 2 === 0 ? $even : $odd;
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @param  array<string, User>  $users
     */
    private function seedRisks(Collection $reports, array $users): void
    {
        $verifikator = $users['verifikator'];

        $risks = Risk::whereIn('report_id', $reports->pluck('id'))->get();

        foreach ($risks as $index => $risk) {
            if ($index % 3 === 1) {
                $risk->update([
                    'status' => Risk::STATUS_DITINDAKLANJUTI,
                    'pic' => 'Kepala Subdit Terkait',
                    'due_date' => now()->addMonth()->format('Y-m-d'),
                    'updated_by' => $verifikator->id,
                ]);
            } elseif ($index % 3 === 2) {
                $risk->update([
                    'status' => Risk::STATUS_SELESAI,
                    'pic' => 'Kepala Subdit Terkait',
                    'due_date' => now()->subDays(10)->format('Y-m-d'),
                    'resolved_at' => now()->subDays(5),
                    'updated_by' => $verifikator->id,
                ]);
            }
            // Sisanya (index % 3 === 0) dibiarkan default 'Baru' dari Report::syncRisks().
        }
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @param  array<string, User>  $users
     */
    private function seedVerificationNotes(Collection $reports, array $users): void
    {
        $verifikator = $users['verifikator'];

        foreach ($reports as $report) {
            if ($report->status === 'Terverifikasi') {
                VerificationNote::create([
                    'report_id' => $report->id,
                    'verifier_id' => $verifikator->id,
                    'action' => 'Disetujui',
                    'note' => null,
                ]);
            } elseif ($report->status === 'Dikembalikan') {
                VerificationNote::create([
                    'report_id' => $report->id,
                    'verifier_id' => $verifikator->id,
                    'action' => 'Dikembalikan',
                    'note' => 'Mohon lengkapi data realisasi anggaran dan narasi progres sebelum diajukan ulang.',
                ]);
            }
        }
    }

    /**
     * @param  Collection<string, Unit>  $units
     */
    private function unitForIndex(Collection $units, int $index): Unit
    {
        return $units->get(self::UNIT_CODES[$index % count(self::UNIT_CODES)]);
    }

    private function budgetRealizationFor(int $indicatorIndex, int $quarter): string
    {
        $base = 25_000_000 + ($indicatorIndex * 1_500_000);
        $variance = $quarter * 2_000_000;

        return number_format($base + $variance, 2, '.', '');
    }

    /**
     * Pagu = realisasi triwulan tertinggi tahun tsb + margin, supaya realisasi anggaran di
     * Dashboard selalu tampil di bawah 100% (bukan pas/melebihi pagu).
     */
    private function paguFor(int $indicatorIndex, bool $historical): string
    {
        $lastQuarter = $historical ? 4 : 3;

        return bcadd($this->budgetRealizationFor($indicatorIndex, $lastQuarter), '5000000', 2);
    }
};
