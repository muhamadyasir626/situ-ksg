<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $indicator_id
 * @property int $unit_id
 * @property int $reporting_period_id
 * @property int|null $quarter
 * @property int|null $month
 * @property string|null $ro_budget_ceiling
 * @property string|null $budget_realization
 * @property string|null $target_achievement
 * @property string|null $progress_narrative
 * @property string|null $problem_narrative
 * @property string|null $risk_external_narrative
 * @property string|null $risk_strategic_narrative
 * @property string|null $risk_fraud_narrative
 * @property string|null $risk_policy_narrative
 * @property string|null $process_note
 * @property string|null $follow_up
 * @property string|null $realization_achievement
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'indicator_id', 'unit_id', 'reporting_period_id', 'quarter', 'month',
    'ro_budget_ceiling', 'budget_realization', 'target_achievement',
    'progress_narrative', 'problem_narrative', 'achievement_strategy',
    'risk_external_narrative', 'risk_strategic_narrative',
    'risk_fraud_narrative', 'risk_policy_narrative',
    'process_note', 'follow_up', 'realization_achievement',
    'status', 'submitted_at', 'verified_at', 'verified_by',
    'created_by', 'updated_by',
])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Nama bulan berbahasa Indonesia (Fase 22) — tidak dari Carbon::translatedFormat()
     * karena `app.locale` baku proyek ini adalah `en`, sementara seluruh UI berbahasa
     * Indonesia (konsisten dengan label lain seperti status "Diajukan"/"Draft").
     *
     * @var array<int, string>
     */
    private const MONTH_LABELS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function monthLabel(?int $month): ?string
    {
        return $month ? (self::MONTH_LABELS[$month] ?? null) : null;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function monthOptions(): array
    {
        return collect(self::MONTH_LABELS)
            ->map(fn (string $label, int $month) => ['id' => $month, 'name' => $label])
            ->values()
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('report')
            ->logFillable()
            ->logExcept(['created_by', 'updated_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created', 'updated' => match ($this->status) {
                    'Diajukan' => 'Laporan diajukan',
                    'Dikembalikan' => 'Laporan dikembalikan',
                    'Terverifikasi' => 'Laporan diverifikasi',
                    default => $eventName === 'created' ? 'Laporan dibuat' : 'Laporan diperbarui',
                },
                'deleted' => 'Laporan dihapus',
                default => $eventName,
            });
    }

    protected function casts(): array
    {
        return [
            'ro_budget_ceiling' => 'decimal:2',
            'budget_realization' => 'decimal:2',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Indicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<ReportingPeriod, $this>
     */
    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    /**
     * @return HasMany<ReportAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    /**
     * @return HasMany<ReportRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ReportRevision::class);
    }

    /**
     * @return HasMany<VerificationNote, $this>
     */
    public function verificationNotes(): HasMany
    {
        return $this->hasMany(VerificationNote::class);
    }

    /**
     * @return HasMany<Risk, $this>
     */
    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    /**
     * @return array<int, string>
     */
    public static function riskNarrativeColumns(): array
    {
        return ['risk_external_narrative', 'risk_strategic_narrative', 'risk_fraud_narrative', 'risk_policy_narrative'];
    }

    /**
     * Narasi "-" atau kosong dianggap tidak ada risiko yang dicatat (§13.5).
     */
    public static function isMeaningfulNarrative(?string $value): bool
    {
        return $value !== null && trim($value) !== '' && trim($value) !== '-';
    }

    public function hasMeaningfulRiskNarrative(): bool
    {
        foreach (self::riskNarrativeColumns() as $column) {
            if (self::isMeaningfulNarrative($this->{$column})) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithMeaningfulRisk($query)
    {
        return $query->where(function ($query) {
            foreach (self::riskNarrativeColumns() as $column) {
                $query->orWhere(fn ($sub) => $sub->whereNotNull($column)->where($column, '!=', '')->where($column, '!=', '-'));
            }
        });
    }

    /**
     * Peta kolom narasi Identifikasi Risiko ke kategori `Risk` (§14.4).
     *
     * @return array<string, string>
     */
    public static function riskCategoryMap(): array
    {
        return [
            'risk_external_narrative' => Risk::CATEGORY_EXTERNAL,
            'risk_strategic_narrative' => Risk::CATEGORY_STRATEGIC,
            'risk_fraud_narrative' => Risk::CATEGORY_FRAUD,
            'risk_policy_narrative' => Risk::CATEGORY_POLICY,
        ];
    }

    /**
     * Capaian per triwulan (Q1–Q4) untuk sekumpulan indikator pada unit & periode
     * tertentu (Fase 22): laporan kini per BULAN, tiap bulan mewakili kontribusinya
     * sendiri (bukan lagi kumulatif s.d. triwulan seperti Fase 21) — nilai triwulan
     * adalah PENJUMLAHAN numerik seluruh bulan yang termasuk triwulan itu (Q2 =
     * Apr+Mei+Jun). Satu query untuk seluruh indikator (anti N+1). Mengembalikan
     * map [indicator_id => [1..4 => capaian|null]].
     *
     * @param  array<int, int>  $indicatorIds
     * @return array<int, array<int, string|null>>
     */
    public static function quarterlyAchievementByIndicator(array $indicatorIds, int $unitId, int $periodId): array
    {
        $map = [];
        foreach ($indicatorIds as $id) {
            $map[$id] = [1 => null, 2 => null, 3 => null, 4 => null];
        }

        if ($indicatorIds === []) {
            return $map;
        }

        $rows = static::whereIn('indicator_id', $indicatorIds)
            ->where('unit_id', $unitId)
            ->where('reporting_period_id', $periodId)
            ->whereNotNull('month')
            ->get(['indicator_id', 'month', 'target_achievement']);

        /** @var array<int, array<int, array{sum: float, suffix: string|null}>> $sums */
        $sums = [];

        foreach ($rows as $report) {
            $parsed = self::parseAchievementNumber($report->target_achievement);
            if ($parsed === null) {
                continue;
            }

            $quarter = (int) ceil($report->month / 3);
            $sums[$report->indicator_id][$quarter]['sum'] = ($sums[$report->indicator_id][$quarter]['sum'] ?? 0.0) + $parsed['value'];
            $sums[$report->indicator_id][$quarter]['suffix'] ??= $parsed['suffix'];
        }

        foreach ($sums as $indicatorId => $quarters) {
            foreach ($quarters as $quarter => $data) {
                $formatted = rtrim(rtrim(number_format($data['sum'], 2, '.', ''), '0'), '.');
                $map[$indicatorId][$quarter] = $data['suffix'] ? "{$formatted} {$data['suffix']}" : $formatted;
            }
        }

        return $map;
    }

    /**
     * Ekstrak angka pertama dan sisa teks (satuan/suffix) dari narasi capaian bebas
     * (mis. "3 Spesies" → nilai 3, suffix "Spesies"; "60%" → nilai 60, suffix "%").
     * Dipakai untuk menjumlahkan capaian bulanan menjadi total triwulan (Fase 22).
     *
     * @return array{value: float, suffix: string|null}|null
     */
    public static function parseAchievementNumber(?string $value): ?array
    {
        if ($value === null || ! preg_match('/(-?\d+(?:[.,]\d+)?)\s*(.*)$/', trim($value), $matches)) {
            return null;
        }

        return [
            'value' => (float) str_replace(',', '.', $matches[1]),
            'suffix' => trim($matches[2]) !== '' ? trim($matches[2]) : null,
        ];
    }

    /**
     * Nilai capaian kumulatif terbaru (triwulan tertinggi yang sudah dilaporkan)
     * dari map quarterly. Dipakai sebagai "progress pencapaian" ringkas.
     *
     * @param  array<int, string|null>  $quarters
     */
    public static function latestAchievementFromQuarters(array $quarters): ?string
    {
        for ($q = 4; $q >= 1; $q--) {
            if (! empty($quarters[$q])) {
                return $quarters[$q];
            }
        }

        return null;
    }

    /**
     * Periksa apakah kelompok indikator ini memerlukan Keterangan Proses (IKU, IKP).
     */
    public function groupHasProcessNote(): bool
    {
        return in_array($this->indicator?->group?->code, ['IKU', 'IKP'], true);
    }

    /**
     * Periksa apakah kelompok indikator ini memerlukan Tindak Lanjut (IKP).
     */
    public function groupHasFollowUp(): bool
    {
        return $this->indicator?->group?->code === 'IKP';
    }

    /**
     * Periksa apakah kelompok indikator ini memerlukan Realisasi Capaian (IKK).
     */
    public function groupHasRealizationAchievement(): bool
    {
        return $this->indicator?->group?->code === 'IKK';
    }

    /**
     * Periksa apakah kelompok indikator ini memerlukan Identifikasi Risiko (IKU).
     */
    public function groupHasRiskNarratives(): bool
    {
        return $this->indicator?->group?->code === 'IKU';
    }

    /**
     * Sinkronkan entri `Risk` dari narasi Identifikasi Risiko — hanya untuk kelompok IKU (§14.4).
     * Idempotent per kategori (satu entri per report_id+category): kategori bermakna membuat
     * atau memperbarui entri, kategori yang dikosongkan menghapus entrinya selama laporan masih
     * bisa diedit (Draft/Dikembalikan) — riwayat dipertahankan bila laporan sudah pernah diajukan.
     */
    public function syncRisks(): void
    {
        if ($this->indicator?->group?->code !== 'IKU') {
            return;
        }

        foreach (self::riskCategoryMap() as $column => $category) {
            $narrative = $this->{$column};
            $existing = $this->risks()->where('category', $category)->first();

            if (self::isMeaningfulNarrative($narrative)) {
                if ($existing) {
                    $existing->update(['narrative' => $narrative]);
                } else {
                    $this->risks()->create(['category' => $category, 'narrative' => $narrative]);
                }
            } elseif ($existing && in_array($this->status, ['Draft', 'Dikembalikan'], true)) {
                $existing->delete();
            }
        }
    }
}
