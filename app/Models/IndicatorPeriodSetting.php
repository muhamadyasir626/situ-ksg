<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $indicator_id
 * @property int $reporting_period_id
 * @property string|null $target
 * @property string|null $unit
 * @property string|null $pagu_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['indicator_id', 'reporting_period_id', 'target', 'unit', 'pagu_amount'])]
class IndicatorPeriodSetting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('indicator-setting')
            ->logOnly(['target', 'unit', 'pagu_amount'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Pengaturan indikator periode dibuat',
                'updated' => 'Pengaturan indikator periode diperbarui',
                'deleted' => 'Pengaturan indikator periode dihapus',
                default => $eventName,
            });
    }

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'pagu_amount' => 'decimal:2',
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
     * @return BelongsTo<ReportingPeriod, $this>
     */
    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }
}
