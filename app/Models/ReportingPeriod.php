<?php

namespace App\Models;

use Database\Factories\ReportingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year
 * @property string $label
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year', 'label', 'start_date', 'end_date', 'is_active'])]
class ReportingPeriod extends Model
{
    /** @use HasFactory<ReportingPeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Periode yang sedang aktif — dipakai sebagai default input pelaporan &
     * filter periode di seluruh halaman (Fase 21).
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * @return HasMany<Indicator, $this>
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class);
    }
}
