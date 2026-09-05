<?php

namespace App\Models;

use Database\Factories\IndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $indicator_group_id
 * @property string $name
 * @property string $target
 * @property string $unit
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read IndicatorGroup $group
 */
#[Fillable(['indicator_group_id', 'name', 'target', 'unit', 'sort_order', 'is_active'])]
class Indicator extends Model
{
    /** @use HasFactory<IndicatorFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<IndicatorGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(IndicatorGroup::class, 'indicator_group_id');
    }

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'indicator_assignments')
            ->using(IndicatorAssignment::class)
            ->withTimestamps();
    }

    /**
     * Pengaturan per-periode: target, satuan, dan pagu anggaran (Fase 21).
     *
     * @return HasMany<IndicatorPeriodSetting, $this>
     */
    public function periodSettings(): HasMany
    {
        return $this->hasMany(IndicatorPeriodSetting::class);
    }

    /**
     * Ambil setting untuk sebuah periode. Memakai relasi yang sudah di-eager-load
     * bila tersedia, sehingga tidak menambah N+1 pada daftar indikator.
     */
    public function settingFor(?int $periodId): ?IndicatorPeriodSetting
    {
        if (! $periodId) {
            return null;
        }

        if ($this->relationLoaded('periodSettings')) {
            return $this->periodSettings->firstWhere('reporting_period_id', $periodId);
        }

        return $this->periodSettings()->where('reporting_period_id', $periodId)->first();
    }

    /**
     * Target untuk periode tertentu, fallback ke nilai dasar indikator bila belum
     * ditetapkan khusus untuk periode itu.
     */
    public function resolvedTarget(?int $periodId): ?string
    {
        return $this->settingFor($periodId)?->target ?? $this->target;
    }

    public function resolvedUnit(?int $periodId): ?string
    {
        return $this->settingFor($periodId)?->unit ?? $this->unit;
    }

    /**
     * Pagu anggaran untuk periode tertentu (hanya bermakna bagi kelompok RO).
     */
    public function paguFor(?int $periodId): ?string
    {
        return $this->settingFor($periodId)?->pagu_amount;
    }
}
